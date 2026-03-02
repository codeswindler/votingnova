<?php
/**
 * Admin Authentication Handler
 */

session_start();

require_once __DIR__ . '/db.php';

class Auth {
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['admin_id']) && isset($_SESSION['admin_username']);
    }

    /**
     * Require login - redirect if not logged in
     * Also checks if password change is required
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: /admin/login.php');
            exit;
        }
        
        // Check if password change is required (for system users)
        if (isset($_SESSION['must_change_password']) && $_SESSION['must_change_password'] && 
            isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'system_user') {
            // Allow access to change-password page
            $currentPage = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($currentPage, '/admin/change-password.php') === false && 
                strpos($currentPage, '/admin/logout.php') === false) {
                header('Location: /admin/change-password.php');
                exit;
            }
        }
    }

    /**
     * Login user (checks both admin_users and system_users)
     * @deprecated Use attemptLogin() instead for OTP support
     */
    public static function login($username, $password) {
        $result = self::attemptLogin($username, $password);
        if ($result['success'] && !$result['otp_required']) {
            return true;
        }
        return false;
    }

    /**
     * Attempt login and return detailed result (supports OTP)
     */
    public static function attemptLogin($username, $password) {
        $db = getDB();
        
        // First, try admin_users table
        $stmt = $db->prepare("SELECT id, username, password_hash, full_name FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Admin users don't need OTP
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_name'] = $user['full_name'];
            $_SESSION['user_type'] = 'admin';
            
            // Update last login
            $updateStmt = $db->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            return [
                'success' => true,
                'otp_required' => false,
                'user_id' => $user['id'],
                'username' => $user['username'],
                'name' => $user['full_name'],
                'user_type' => 'admin'
            ];
        }
        
        // If not found in admin_users, try system_users table (phone as username)
        // Normalize phone number for lookup (handle different formats)
        $normalizedPhone = preg_replace('/[^0-9]/', '', $username);
        if (substr($normalizedPhone, 0, 1) === '0') {
            $normalizedPhone = '254' . substr($normalizedPhone, 1);
        } elseif (substr($normalizedPhone, 0, 3) !== '254') {
            $normalizedPhone = '254' . $normalizedPhone;
        }
        
        $stmt = $db->prepare("
            SELECT id, phone, password_hash, first_name, last_name, is_active, must_change_password, otp_enabled 
            FROM system_users 
            WHERE (phone = ? OR phone = ?) AND is_active = 1
        ");
        $stmt->execute([$username, $normalizedPhone]);
        $systemUser = $stmt->fetch();

        if ($systemUser && $systemUser['password_hash'] && password_verify($password, $systemUser['password_hash'])) {
            // Check if OTP is enabled for this user (per-user control only)
            $otpEnabled = (bool)$systemUser['otp_enabled'];
            
            $fullName = trim(($systemUser['first_name'] ?? '') . ' ' . ($systemUser['last_name'] ?? ''));
            $displayName = $systemUser['first_name'] ?? 'User';
            
            if ($otpEnabled) {
                // OTP required, don't set session yet
                return [
                    'success' => true,
                    'otp_required' => true,
                    'user_id' => $systemUser['id'],
                    'username' => $systemUser['phone'],
                    'name' => $fullName ?: 'User',
                    'first_name' => $systemUser['first_name'],
                    'phone' => $systemUser['phone'],
                    'user_type' => 'system_user',
                    'must_change_password' => (bool)$systemUser['must_change_password']
                ];
            } else {
                // No OTP required, login complete
                $_SESSION['admin_id'] = $systemUser['id'];
                $_SESSION['admin_username'] = $systemUser['phone'];
                $_SESSION['admin_name'] = $fullName ?: 'User';
                $_SESSION['admin_first_name'] = $systemUser['first_name'];
                $_SESSION['user_type'] = 'system_user';
                $_SESSION['must_change_password'] = (bool)$systemUser['must_change_password'];
                
                return [
                    'success' => true,
                    'otp_required' => false,
                    'user_id' => $systemUser['id'],
                    'username' => $systemUser['phone'],
                    'name' => $fullName ?: 'User',
                    'first_name' => $systemUser['first_name'],
                    'user_type' => 'system_user'
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => 'Invalid username or password',
            'otp_required' => false
        ];
    }

    /**
     * Logout user
     */
    public static function logout() {
        session_unset();
        session_destroy();
        header('Location: /admin/login.php');
        exit;
    }

    /**
     * Get current user ID
     */
    public static function getUserId() {
        return $_SESSION['admin_id'] ?? null;
    }

    /**
     * Get current username
     */
    public static function getUsername() {
        return $_SESSION['admin_username'] ?? null;
    }
}
