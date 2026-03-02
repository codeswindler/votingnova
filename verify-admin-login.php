<?php
/**
 * Verify admin login credentials
 */

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';

$username = 'admin';
$password = 'admin123';

try {
    $db = Database::getInstance()->getConnection();
    
    // Get admin user
    $stmt = $db->prepare("SELECT id, username, password_hash, email, full_name FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "✗ Admin user not found!\n";
        exit(1);
    }
    
    echo "✓ Admin user found:\n";
    echo "  ID: {$user['id']}\n";
    echo "  Username: {$user['username']}\n";
    echo "  Email: {$user['email']}\n";
    echo "  Full Name: {$user['full_name']}\n";
    echo "  Password Hash: {$user['password_hash']}\n\n";
    
    // Test password verification
    echo "Testing password verification...\n";
    if (password_verify($password, $user['password_hash'])) {
        echo "✓ Password 'admin123' VERIFIES CORRECTLY!\n";
    } else {
        echo "✗ Password 'admin123' DOES NOT VERIFY!\n";
        echo "  The password hash in database is incorrect.\n\n";
        
        // Generate new hash and update
        echo "Generating new password hash...\n";
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        echo "New hash: $newHash\n\n";
        
        echo "Updating database...\n";
        $updateStmt = $db->prepare("UPDATE admin_users SET password_hash = ? WHERE username = ?");
        $updateStmt->execute([$newHash, $username]);
        
        echo "✓ Password hash updated!\n\n";
        
        // Verify again
        echo "Verifying new hash...\n";
        if (password_verify($password, $newHash)) {
            echo "✓ New password hash verifies correctly!\n";
        } else {
            echo "✗ Something went wrong with hash generation!\n";
        }
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
