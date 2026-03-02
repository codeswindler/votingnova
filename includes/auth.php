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
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: /admin/login.php');
            exit;
        }
    }

    /**
     * Login user (checks both admin_users and system_users)
     */
    public static function login($username, $password) {
        $db = getDB();
        
        // First, try admin_users table
        $stmt = $db->prepare("SELECT id, username, password_hash, full_name FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_name'] = $user['full_name'];
            $_SESSION['user_type'] = 'admin';
            
            // Update last login
            $updateStmt = $db->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            return true;
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
            SELECT id, phone, password_hash, full_name, is_active, must_change_password 
            FROM system_users 
            WHERE (phone = ? OR phone = ?) AND is_active = 1
        ");
        $stmt->execute([$username, $normalizedPhone]);
        $systemUser = $stmt->fetch();

        if ($systemUser && $systemUser['password_hash'] && password_verify($password, $systemUser['password_hash'])) {
            $_SESSION['admin_id'] = $systemUser['id'];
            $_SESSION['admin_username'] = $systemUser['phone'];
            $_SESSION['admin_name'] = $systemUser['full_name'] ?: 'User';
            $_SESSION['user_type'] = 'system_user';
            $_SESSION['must_change_password'] = (bool)$systemUser['must_change_password'];
            
            return true;
        }
        
        return false;
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
