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
     * Login user
     */
    public static function login($username, $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, username, password_hash, full_name FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_name'] = $user['full_name'];
            
            // Update last login
            $updateStmt = $db->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
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
