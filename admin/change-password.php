<?php
/**
 * Change Password Page - Required for users with must_change_password flag
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/user-service.php';

Auth::requireLogin();

// Only system users can change password here
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'system_user') {
    header('Location: /admin/dashboard.php');
    exit;
}

// Check if password change is actually required
if (!isset($_SESSION['must_change_password']) || !$_SESSION['must_change_password']) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'All fields are required';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New password and confirmation do not match';
    } elseif (strlen($newPassword) < 8) {
        $error = 'New password must be at least 8 characters long';
    } else {
        $userId = Auth::getUserId();
        $userService = new UserService();
        
        // Verify current password
        $verifyResult = $userService->verifyPassword($userId, $currentPassword);
        
        if ($verifyResult['success']) {
            // Change password
            $changeResult = $userService->changePassword($userId, $newPassword);
            
            if ($changeResult['success']) {
                // Clear must_change_password flag from session
                $_SESSION['must_change_password'] = false;
                
                $success = 'Password changed successfully! Redirecting to dashboard...';
                header('Refresh: 2; url=/admin/dashboard.php');
            } else {
                $error = $changeResult['message'] ?? 'Failed to change password';
            }
        } else {
            $error = 'Current password is incorrect';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Voting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="/admin/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h4 class="mb-0"><i class="bi bi-shield-exclamation"></i> Password Change Required</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-info-circle"></i> You must change your password before continuing.
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required autofocus>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                                    <small class="text-muted">Must be at least 8 characters long</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-key"></i> Change Password
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
