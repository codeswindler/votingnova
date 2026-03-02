<?php
/**
 * STK Push Diagnostic Script
 * Tests M-Pesa configuration and STK Push functionality
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mpesa-service.php';
require_once __DIR__ . '/includes/env.php';

echo "=== STK Push Diagnostic ===\n\n";

// Check .env file
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    echo "❌ ERROR: .env file not found!\n";
    exit(1);
}
echo "✓ .env file exists\n";

// Load config
$config = require __DIR__ . '/config/mpesa.php';

echo "\n=== M-Pesa Configuration ===\n";
echo "Environment: " . ($config['environment'] ?? 'NOT SET') . "\n";
echo "Consumer Key: " . (empty($config['consumer_key']) ? '❌ NOT SET' : '✓ SET (' . substr($config['consumer_key'], 0, 10) . '...)') . "\n";
echo "Consumer Secret: " . (empty($config['consumer_secret']) ? '❌ NOT SET' : '✓ SET') . "\n";
echo "Shortcode: " . (empty($config['shortcode']) ? '❌ NOT SET' : '✓ ' . $config['shortcode']) . "\n";
echo "Passkey: " . (empty($config['passkey']) ? '❌ NOT SET' : '✓ SET') . "\n";
echo "Callback URL: " . ($config['callback_url'] ?? 'NOT SET') . "\n";

// Check if credentials are configured
if (empty($config['consumer_key']) || empty($config['consumer_secret'])) {
    echo "\n❌ ERROR: M-Pesa credentials not configured in .env\n";
    echo "Please set:\n";
    echo "  MPESA_CONSUMER_KEY=your_consumer_key\n";
    echo "  MPESA_CONSUMER_SECRET=your_consumer_secret\n";
    echo "  MPESA_SHORTCODE=your_shortcode\n";
    echo "  MPESA_PASSKEY=your_passkey\n";
    exit(1);
}

// Test access token
echo "\n=== Testing Access Token ===\n";
$mpesaService = new MpesaService();
$reflection = new ReflectionClass($mpesaService);
$method = $reflection->getMethod('getAccessToken');
$method->setAccessible(true);

try {
    $token = $method->invoke($mpesaService);
    if ($token) {
        echo "✓ Access token obtained successfully\n";
        echo "  Token: " . substr($token, 0, 20) . "...\n";
    } else {
        echo "❌ Failed to get access token\n";
        echo "  Check your consumer_key and consumer_secret\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "❌ Error getting access token: " . $e->getMessage() . "\n";
    exit(1);
}

// Test password generation
echo "\n=== Testing Password Generation ===\n";
$passwordMethod = $reflection->getMethod('generatePassword');
$passwordMethod->setAccessible(true);
try {
    $passwordData = $passwordMethod->invoke($mpesaService);
    echo "✓ Password generated successfully\n";
    echo "  Timestamp: " . $passwordData['timestamp'] . "\n";
    echo "  Password: " . substr($passwordData['password'], 0, 20) . "...\n";
} catch (Exception $e) {
    echo "❌ Error generating password: " . $e->getMessage() . "\n";
    exit(1);
}

// Database check
echo "\n=== Database Check ===\n";
try {
    $db = getDB();
    echo "✓ Database connection successful\n";
    
    // Check if mpesa_transactions table exists
    $stmt = $db->query("SHOW TABLES LIKE 'mpesa_transactions'");
    if ($stmt->rowCount() > 0) {
        echo "✓ mpesa_transactions table exists\n";
    } else {
        echo "❌ mpesa_transactions table not found\n";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Summary ===\n";
echo "✓ Configuration looks good!\n";
echo "\nTo test STK Push, use the USSD menu and complete a vote.\n";
echo "Check logs with: sudo journalctl -u voting-nova -f\n";
echo "\nCommon issues:\n";
echo "1. Wrong environment (sandbox vs production)\n";
echo "2. Invalid shortcode or passkey\n";
echo "3. Callback URL not accessible from Safaricom servers\n";
echo "4. Phone number format (must be 254XXXXXXXXX)\n";
