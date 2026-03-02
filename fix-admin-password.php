<?php
/**
 * Script to create/reset admin user password
 * Run: php fix-admin-password.php
 */

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';

$username = 'admin';
$password = 'admin123'; // Default password

try {
    $db = Database::getInstance()->getConnection();
    
    // Generate password hash
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if admin user exists
    $stmt = $db->prepare("SELECT id FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Update existing user
        $stmt = $db->prepare("UPDATE admin_users SET password_hash = ? WHERE username = ?");
        $stmt->execute([$passwordHash, $username]);
        echo "✓ Admin password updated successfully!\n";
        echo "Username: $username\n";
        echo "Password: $password\n";
    } else {
        // Create new admin user
        $stmt = $db->prepare("INSERT INTO admin_users (username, password_hash, email, full_name) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $passwordHash, 'admin@votingnova.com', 'System Administrator']);
        echo "✓ Admin user created successfully!\n";
        echo "Username: $username\n";
        echo "Password: $password\n";
    }
    
    // Verify the password works
    $stmt = $db->prepare("SELECT id, username, password_hash FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        echo "✓ Password verification successful!\n";
    } else {
        echo "✗ Password verification failed!\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
