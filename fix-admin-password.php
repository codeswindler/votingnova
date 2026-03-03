<?php
/**
 * Script to create/reset admin user password from .env
 * Run: php fix-admin-password.php
 * Reads ADMIN_USERNAME and ADMIN_PASSWORD directly from .env so your env changes always apply.
 */

require_once __DIR__ . '/includes/db.php';

// Read .env file directly so we always use file values (env.php skips vars already set in environment)
$envFile = __DIR__ . '/.env';
$username = 'admin';
$password = 'admin123';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (strlen($value) >= 2 && (($value[0] === '"' && $value[strlen($value)-1] === '"') || ($value[0] === "'" && $value[strlen($value)-1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        $value = trim($value);
        if ($name === 'ADMIN_USERNAME') $username = $value !== '' ? $value : $username;
        if ($name === 'ADMIN_PASSWORD') $password = $value !== '' ? $value : $password;
    }
}

try {
    $db = getDB();

    echo "Using .env: " . (file_exists($envFile) ? realpath($envFile) : $envFile) . "\n";
    echo "Username from .env: " . $username . "\n";
    echo "Password length: " . strlen($password) . " chars\n\n";

    // Generate password hash (PASSWORD_DEFAULT = bcrypt, same as login uses)
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        throw new Exception("password_hash() failed");
    }

    // Check if admin user exists
    $stmt = $db->prepare("SELECT id FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user) {
        // Update existing user
        $stmt = $db->prepare("UPDATE admin_users SET password_hash = ? WHERE username = ?");
        $stmt->execute([$passwordHash, $username]);
        echo "✓ Admin password updated in DB for user: $username\n";
    } else {
        // Create new admin user
        $stmt = $db->prepare("INSERT INTO admin_users (username, password_hash, email, full_name) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $passwordHash, 'admin@votingnova.com', 'System Administrator']);
        echo "✓ New admin user created: $username\n";
    }

    // Verify the password works against the stored hash
    $stmt = $db->prepare("SELECT id, username, password_hash FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        echo "✓ Password verification OK – login with the username and password from .env\n";
    } else {
        echo "✗ Password verification failed. Check that ADMIN_PASSWORD in .env has no extra spaces or quotes.\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
