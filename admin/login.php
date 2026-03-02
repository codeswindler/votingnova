<?php
/**
 * Admin Login Page
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/otp-service.php';
require_once __DIR__ . '/../includes/db.php';

$error = '';
$otpRequired = false;
$otpSent = false;
$pendingUserId = null;
$pendingPhone = null;

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp_code'])) {
    $userId = (int)($_SESSION['pending_user_id'] ?? 0);
    $otpCode = $_POST['otp_code'] ?? '';
    
    if ($userId && $otpCode) {
        $otpService = new OTPService();
        $result = $otpService->verifyOTP($userId, $otpCode, 'login');
        
        if ($result['success']) {
            // OTP verified, complete login
            $_SESSION['admin_id'] = $_SESSION['pending_user_id'];
            $_SESSION['admin_username'] = $_SESSION['pending_username'];
            $_SESSION['admin_name'] = $_SESSION['pending_name'];
            $_SESSION['admin_first_name'] = $_SESSION['pending_first_name'] ?? null;
            $_SESSION['user_type'] = $_SESSION['pending_user_type'];
            $_SESSION['must_change_password'] = $_SESSION['pending_must_change_password'] ?? false;
            
            // Clear pending session data
            $mustChangePassword = $_SESSION['pending_must_change_password'] ?? false;
            unset($_SESSION['pending_user_id']);
            unset($_SESSION['pending_username']);
            unset($_SESSION['pending_name']);
            unset($_SESSION['pending_first_name']);
            unset($_SESSION['pending_user_type']);
            unset($_SESSION['pending_must_change_password']);
            unset($_SESSION['pending_phone']);
            
            // Check if password change is required
            if ($mustChangePassword) {
                header('Location: /admin/change-password.php');
                exit;
            }
            header('Location: /admin/dashboard.php');
            exit;
        } else {
            $error = $result['message'] ?? 'Invalid OTP code';
            $otpRequired = true;
            $pendingUserId = $userId;
            $pendingPhone = $_SESSION['pending_phone'] ?? null;
        }
    } else {
        $error = 'Invalid OTP verification request';
    }
}
// Handle initial login
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $loginResult = Auth::attemptLogin($username, $password);
    
    if ($loginResult['success']) {
        // Check if OTP is required
        if ($loginResult['otp_required']) {
            // Store pending login info in session
            $_SESSION['pending_user_id'] = $loginResult['user_id'];
            $_SESSION['pending_username'] = $loginResult['username'];
            $_SESSION['pending_name'] = $loginResult['name'];
            $_SESSION['pending_first_name'] = $loginResult['first_name'] ?? null;
            $_SESSION['pending_user_type'] = $loginResult['user_type'];
            $_SESSION['pending_must_change_password'] = $loginResult['must_change_password'] ?? false;
            $_SESSION['pending_phone'] = $loginResult['phone'] ?? null;
            
            // Generate and send OTP
            $otpService = new OTPService();
            $otpResult = $otpService->generateAndSendOTP($loginResult['user_id'], $loginResult['phone'], 'login');
            
            if ($otpResult['success']) {
                $otpRequired = true;
                $otpSent = true;
                $pendingUserId = $loginResult['user_id'];
                $pendingPhone = $loginResult['phone'];
            } else {
                $error = 'Failed to send OTP: ' . ($otpResult['message'] ?? 'Unknown error');
            }
        } else {
            // No OTP required, login complete
            // Check if password change is required
            if ($loginResult['must_change_password'] ?? false) {
                header('Location: /admin/change-password.php');
                exit;
            }
            header('Location: /admin/dashboard.php');
            exit;
        }
    } else {
        $error = $loginResult['message'] ?? 'Invalid username or password';
    }
}

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    // Check if password change is required
    if (isset($_SESSION['must_change_password']) && $_SESSION['must_change_password'] && 
        isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'system_user') {
        header('Location: /admin/change-password.php');
        exit;
    }
    header('Location: /admin/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Voting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/admin/css/style.css">
    <style>
        * {
            box-sizing: border-box;
        }

        body.login-page {
            margin: 0;
            font-family: "DM Sans", Arial, sans-serif;
            min-height: 100vh;
            overflow: hidden;
        }

        .login-hero {
            min-height: 100vh;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-bg {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            filter: blur(12px);
            transform: scale(1.08);
            opacity: 1;
            transition: opacity 3s ease-in-out;
        }

        .login-bg-next {
            opacity: 0;
            transition: opacity 3s ease-in-out;
        }

        .login-bg-next.is-visible {
            opacity: 1;
        }

        .login-bg.fade-out {
            opacity: 0;
        }

        .login-overlay {
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top, rgba(8, 15, 30, 0.75), rgba(5, 9, 18, 0.92));
        }

        .login-card {
            position: relative;
            z-index: 1;
            width: min(420px, 92vw);
            padding: 32px;
            border-radius: 20px;
            background: linear-gradient(
                145deg,
                rgba(26, 31, 28, 0.95),
                rgba(35, 40, 37, 0.9)
            );
            border: 1px solid rgba(111, 184, 127, 0.3);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(14px);
        }

        .login-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .login-header h1 {
            margin: 0;
            font-size: 24px;
            color: #e6f1ff;
            font-weight: 700;
        }

        .login-header p {
            margin: 6px 0 0;
            color: #a6b3d1;
            font-size: 14px;
        }

        .brand-mark {
            height: 44px;
            width: 44px;
            border-radius: 14px;
            background: linear-gradient(135deg, #6fb87f, #4a9d5f);
            color: #ffffff;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 4px 12px rgba(111, 184, 127, 0.3);
        }

        .login-body {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .login-body label {
            color: #cbd5f5;
            font-weight: 600;
            font-size: 14px;
        }

        .login-body input {
            background: rgba(35, 40, 37, 0.8);
            border: 1px solid rgba(111, 184, 127, 0.3);
            color: #e6f1ff;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 14px;
            width: 100%;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }

        .login-body input:focus {
            outline: none;
            border-color: rgba(111, 184, 127, 0.6);
            background: rgba(35, 40, 37, 0.95);
            box-shadow: 0 0 0 3px rgba(111, 184, 127, 0.2);
        }

        .password-field {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-field input {
            width: 100%;
            padding-right: 45px; /* Space for eye icon */
        }

        .password-toggle {
            position: absolute;
            right: 8px;
            padding: 8px;
            border: none;
            background: transparent;
            color: #cbd5f5;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .password-toggle:hover {
            background: rgba(111, 184, 127, 0.2);
            color: #6fb87f;
        }

        .password-toggle:focus {
            outline: none;
        }

        .password-toggle svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
            pointer-events: none;
        }

        .login-actions {
            margin-top: 8px;
        }

        .login-actions button {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            background: linear-gradient(135deg, #6fb87f, #4a9d5f);
            border: none;
            color: #ffffff;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(111, 184, 127, 0.3);
        }

        .login-actions button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(111, 184, 127, 0.4);
        }

        .login-actions button:active {
            transform: translateY(0);
        }

        .forgot-password {
            text-align: right;
            margin-top: 8px;
        }

        .forgot-password a {
            color: #6fb87f;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .forgot-password a:hover {
            color: #7fc89f;
            text-decoration: underline;
        }

        .login-actions button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .error-text {
            color: #ff9ba8;
            font-size: 14px;
            margin-top: 4px;
        }

        .subtle {
            color: #94a3b8;
            margin-top: 4px;
            font-size: 14px;
        }

        /* Toast Notification */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .toast {
            min-width: 300px;
            background: rgba(26, 31, 28, 0.95);
            border: 1px solid rgba(111, 184, 127, 0.3);
            color: #e6f1ff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
        }

        .toast-header {
            background: rgba(35, 40, 37, 0.8);
            border-bottom: 1px solid rgba(111, 184, 127, 0.3);
            color: #e6f1ff;
        }

        .toast-body {
            color: #cbd5f5;
        }

        .toast-success .toast-header {
            border-left: 4px solid #6fb87f;
        }

        .toast-error .toast-header {
            border-left: 4px solid #ff6b6b;
        }

        /* Modal Overlay Styling */
        .modal {
            z-index: 1055;
        }

        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1050;
        }

        .modal-dialog {
            margin: 1.75rem auto;
            max-width: 500px;
        }

        .modal-content {
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }

        @media (max-width: 576px) {
            .modal-dialog {
                margin: 0.5rem;
            }
        }
    </style>
</head>
<body class="login-page">
    <div class="login-hero">
        <div class="login-bg" id="loginBg"></div>
        <div class="login-bg login-bg-next" id="loginBgNext"></div>
        <div class="login-overlay"></div>
        <div class="login-card">
            <div class="login-header">
                <span class="brand-mark">VS</span>
                <div>
                    <h1>Voting System Admin</h1>
                    <p>Secure access for live operations.</p>
                </div>
            </div>

            <div class="login-body">
                <?php if ($otpRequired): ?>
                    <!-- OTP Verification Form -->
                    <form method="POST" id="otpForm">
                        <div class="mb-3">
                            <p class="subtle" style="color: #6fb87f; margin-bottom: 16px;">
                                <i class="bi bi-shield-check"></i> 
                                <?php if ($otpSent): ?>
                                    OTP code has been sent to your phone. Please enter it below.
                                <?php else: ?>
                                    Please enter the OTP code sent to your phone.
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <label for="otp_code">OTP Code</label>
                        <input
                            type="text"
                            id="otp_code"
                            name="otp_code"
                            placeholder="Enter 6-digit OTP"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            required
                            autofocus
                            style="text-align: center; font-size: 18px; letter-spacing: 4px;"
                        />
                        
                        <?php if ($error): ?>
                            <p class="subtle error-text"><?php echo htmlspecialchars($error); ?></p>
                        <?php endif; ?>
                        
                        <div class="login-actions" style="margin-top: 16px;">
                            <button type="submit">Verify OTP</button>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="/admin/login.php" style="color: #6fb87f; text-decoration: none; font-size: 14px;">
                                <i class="bi bi-arrow-left"></i> Back to Login
                            </a>
                        </div>
                    </form>
                <?php else: ?>
                    <!-- Regular Login Form -->
                    <form method="POST" id="loginForm">
                        <label for="username">Username</label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Enter your username"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            required
                            autofocus
                        />
                        
                        <label for="password">Password</label>
                        <div class="password-field">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="••••••••"
                                required
                            />
                            <button
                                type="button"
                                class="password-toggle"
                                onclick="togglePassword()"
                                aria-label="Show password"
                                tabindex="-1"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true" width="18" height="18">
                                    <path d="M12 5c5.05 0 9.27 3.1 10.9 7.5C21.27 16.9 17.05 20 12 20S2.73 16.9 1.1 12.5C2.73 8.1 6.95 5 12 5zm0 2c-3.79 0-7.05 2.22-8.57 5.5C4.95 15.78 8.21 18 12 18s7.05-2.22 8.57-5.5C19.05 9.22 15.79 7 12 7z" id="eyePath" fill="currentColor"/>
                                    <path d="M12 9.5A3.5 3.5 0 1 0 15.5 13 3.5 3.5 0 0 0 12 9.5z" id="eyePupil" fill="currentColor"/>
                                </svg>
                            </button>
                        </div>

                        <?php if ($error): ?>
                            <p class="subtle error-text"><?php echo htmlspecialchars($error); ?></p>
                        <?php endif; ?>

                        <div class="forgot-password">
                            <a href="#" onclick="showForgotPassword(); return false;">Forgot Password?</a>
                        </div>

                        <div class="login-actions">
                            <button type="submit">Sign In</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

    <!-- Forgot Password Modal -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background: rgba(26, 31, 28, 0.95); border: 1px solid rgba(111, 184, 127, 0.3); color: #e6f1ff;">
                <div class="modal-header" style="border-bottom: 1px solid rgba(111, 184, 127, 0.3);">
                    <h5 class="modal-title" style="color: #e6f1ff;">Reset Password</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p style="color: #cbd5f5;">Enter your registered phone number to receive a temporary password via SMS.</p>
                    <form id="forgotPasswordForm">
                        <div class="mb-3">
                            <label for="resetPhone" class="form-label" style="color: #cbd5f5;">Phone Number</label>
                            <input type="text" class="form-control" id="resetPhone" placeholder="254712345678" required style="background: rgba(35, 40, 37, 0.8); border: 1px solid rgba(111, 184, 127, 0.3); color: #e6f1ff;">
                            <small class="text-muted" style="color: #8fa08f;">Format: 254712345678</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(111, 184, 127, 0.3);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="sendResetBtn" style="background: linear-gradient(135deg, #6fb87f, #4a9d5f); border: none; color: #ffffff;">Send Reset Code</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cybernetic/Technology images only (no people) - 12 images
        const IMAGE_POOL = [
            // Circuit Boards & Electronics
            "https://images.unsplash.com/photo-1518770660439-4636190af475?w=1600&auto=format&fit=crop&q=80", // Circuit board
            "https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=1600&auto=format&fit=crop&q=80", // Electronic circuit board
            "https://images.unsplash.com/photo-1555949963-aa79dcee981c?w=1600&auto=format&fit=crop&q=80", // Technology circuit
            // Digital Networks & Data
            "https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=1600&auto=format&fit=crop&q=80", // Digital technology network
            "https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=1600&auto=format&fit=crop&q=80", // Digital data network
            "https://images.unsplash.com/photo-1504639725590-34d0984388bd?w=1600&auto=format&fit=crop&q=80", // Digital voting technology
            // Cybernetics & AI
            "https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=1600&auto=format&fit=crop&q=80", // Cybernetics technology
            "https://images.unsplash.com/photo-1555255707-c07966088b7b?w=1600&auto=format&fit=crop&q=80", // AI neural network
            "https://images.unsplash.com/photo-1555949963-ff9fe0c870eb?w=1600&auto=format&fit=crop&q=80", // Technology grid
            // Futuristic Tech
            "https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=1600&auto=format&fit=crop&q=80", // Electronic systems
            "https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=1600&auto=format&fit=crop&q=80", // Tech infrastructure
            "https://images.unsplash.com/photo-1518770660439-4636190af475?w=1600&auto=format&fit=crop&q=80", // Advanced circuits
        ];

        let activeIndex = 0;
        let nextIndex = 1;
        let isTransitioning = false;
        const fadeDuration = 3000; // 3 seconds for smooth fade
        const displayDuration = 8000; // 8 seconds display time (longer to see each image)

        function initBackground() {
            const bg = document.getElementById('loginBg');
            const bgNext = document.getElementById('loginBgNext');
            
            if (bg && bgNext) {
                bg.style.backgroundImage = `url(${IMAGE_POOL[activeIndex]})`;
                bgNext.style.backgroundImage = `url(${IMAGE_POOL[nextIndex]})`;
            }
        }

        function rotateBackground() {
            if (isTransitioning) return;
            
            const bg = document.getElementById('loginBg');
            const bgNext = document.getElementById('loginBgNext');
            
            if (!bg || !bgNext) return;
            
            isTransitioning = true;
            
            // Preload next image before transition
            const nextImageIndex = (nextIndex + 1) % IMAGE_POOL.length;
            const nextImage = new Image();
            nextImage.src = IMAGE_POOL[nextImageIndex];
            
            // Start cross-fade transition (both fade out and fade in simultaneously)
            bg.classList.add('fade-out');
            bgNext.classList.add('is-visible');
            
            // After fade completes, swap images and reset
            setTimeout(() => {
                // Update indices
                activeIndex = nextIndex;
                nextIndex = nextImageIndex;
                
                // Swap images smoothly
                bg.style.backgroundImage = bgNext.style.backgroundImage;
                bgNext.style.backgroundImage = `url(${IMAGE_POOL[nextIndex]})`;
                
                // Reset states - ensure smooth transition
                bg.classList.remove('fade-out');
                bgNext.classList.remove('is-visible');
                
                // Small delay to ensure smooth transition
                setTimeout(() => {
                    isTransitioning = false;
                }, 100);
            }, fadeDuration);
        }

        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyePath = document.getElementById('eyePath');
            const eyePupil = document.getElementById('eyePupil');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyePupil.style.display = 'none';
            } else {
                passwordInput.type = 'password';
                eyePupil.style.display = 'block';
            }
        }

        // Initialize
        initBackground();
        
        // Preload all images for smoother transitions
        IMAGE_POOL.forEach(url => {
            const img = new Image();
            img.src = url;
        });
        
        // Start rotation: display duration (fade happens during display time)
        // This ensures smooth transitions without skipping
        setTimeout(() => {
            setInterval(rotateBackground, displayDuration);
        }, 1000); // Small delay to ensure images are loaded

        // Forgot Password Modal
        let forgotPasswordModal;

        // Toast notification function
        function showToast(message, type = 'success') {
            const toastContainer = document.querySelector('.toast-container');
            const toastId = 'toast-' + Date.now();
            
            const toastHtml = `
                <div id="${toastId}" class="toast toast-${type}" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header">
                        <strong class="me-auto">${type === 'success' ? 'Success' : 'Error'}</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">${message}</div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, {
                autohide: true,
                delay: 5000
            });
            toast.show();
            
            // Remove toast element after it's hidden
            toastElement.addEventListener('hidden.bs.toast', () => {
                toastElement.remove();
            });
        }

        function showForgotPassword() {
            if (!forgotPasswordModal) {
                forgotPasswordModal = new bootstrap.Modal(document.getElementById('forgotPasswordModal'));
            }
            document.getElementById('forgotPasswordForm').reset();
            forgotPasswordModal.show();
        }

        function requestPasswordReset() {
            const phone = document.getElementById('resetPhone').value.trim();
            const sendButton = document.getElementById('sendResetBtn');
            
            if (!phone) {
                showToast('Please enter your phone number', 'error');
                return;
            }

            // Normalize phone number
            let normalizedPhone = phone.replace(/[^0-9]/g, '');
            if (normalizedPhone.substring(0, 3) !== '254') {
                if (normalizedPhone.substring(0, 1) === '0') {
                    normalizedPhone = '254' + normalizedPhone.substring(1);
                } else {
                    normalizedPhone = '254' + normalizedPhone;
                }
            }

            // Disable button during request
            const originalText = sendButton.textContent;
            sendButton.disabled = true;
            sendButton.textContent = 'Sending...';

            fetch('/api/password-reset.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ phone: normalizedPhone })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message || 'Temporary password sent to your phone. Please check your SMS and login to reset your password.', 'success');
                        document.getElementById('forgotPasswordForm').reset();
                        setTimeout(() => {
                            if (forgotPasswordModal) {
                                forgotPasswordModal.hide();
                            }
                        }, 500);
                    } else {
                        showToast(data.error || 'Failed to send reset code. Please try again.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('An error occurred. Please try again.', 'error');
                })
                .finally(() => {
                    sendButton.disabled = false;
                    sendButton.textContent = originalText;
                });
        }

        // Initialize modal and button event listener
        document.addEventListener('DOMContentLoaded', function() {
            const sendResetBtn = document.getElementById('sendResetBtn');
            if (sendResetBtn) {
                sendResetBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    requestPasswordReset();
                });
            }

            // Prevent form submission on Enter key
            const forgotPasswordForm = document.getElementById('forgotPasswordForm');
            if (forgotPasswordForm) {
                forgotPasswordForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    requestPasswordReset();
                });
            }
        });
    </script>
</body>
</html>
