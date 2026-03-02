<?php
/**
 * SMS Diagnostic Script
 * Tests Advanta SMS API configuration and sending
 */

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/config/advanta.php';

echo "=== SMS Diagnostic ===\n\n";

// Check .env file
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    echo "❌ ERROR: .env file not found!\n";
    exit(1);
}
echo "✓ .env file exists\n";

// Load config
$config = require __DIR__ . '/config/advanta.php';

echo "\n=== Advanta SMS Configuration ===\n";
echo "API Key: " . (empty($config['api_key']) ? '❌ NOT SET' : '✓ SET (' . substr($config['api_key'], 0, 10) . '...)') . "\n";
echo "Partner ID: " . (empty($config['partner_id']) ? '❌ NOT SET' : '✓ ' . $config['partner_id']) . "\n";
echo "Shortcode: " . (empty($config['shortcode']) ? '❌ NOT SET' : '✓ ' . $config['shortcode']) . "\n";
echo "SMS API URL: " . ($config['sms_api_url'] ?? 'NOT SET') . "\n";

// Check if credentials are configured
if (empty($config['api_key']) || empty($config['partner_id'])) {
    echo "\n❌ ERROR: Advanta SMS credentials not configured in .env\n";
    echo "Please set:\n";
    echo "  ADVANTA_API_KEY=your_api_key\n";
    echo "  ADVANTA_PARTNER_ID=your_partner_id\n";
    echo "  ADVANTA_SHORTCODE=your_shortcode\n";
    exit(1);
}

// Test SMS sending
echo "\n=== Testing SMS Sending ===\n";
$testPhone = '254727839315'; // Your phone number for testing
$testMessage = "Test SMS from Voting System - " . date('Y-m-d H:i:s');

$data = [
    'api_key' => $config['api_key'],
    'partner_id' => $config['partner_id'],
    'shortcode' => $config['shortcode'],
    'message' => $testMessage,
    'mobile' => $testPhone
];

echo "Sending test SMS to: $testPhone\n";
echo "Message: $testMessage\n";
echo "URL: " . $config['sms_api_url'] . "\n";
echo "Payload: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

$ch = curl_init($config['sms_api_url']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "=== SMS Test Results ===\n";
echo "HTTP Code: $httpCode\n";

if ($curlError) {
    echo "❌ cURL Error: $curlError\n";
    exit(1);
}

echo "Response: $response\n";

$result = json_decode($response, true);
if ($httpCode === 200 && isset($result['status']) && $result['status'] === 'success') {
    echo "\n✓ SMS sent successfully!\n";
    echo "Check your phone ($testPhone) for the test message.\n";
} else {
    echo "\n❌ SMS sending failed!\n";
    if (isset($result['message'])) {
        echo "Error: " . $result['message'] . "\n";
    }
    echo "\nCommon issues:\n";
    echo "1. Invalid API key or Partner ID\n";
    echo "2. Shortcode not authorized\n";
    echo "3. Insufficient SMS credits\n";
    echo "4. Phone number format incorrect\n";
    echo "5. API endpoint URL incorrect\n";
    exit(1);
}

echo "\n=== Summary ===\n";
echo "✓ SMS configuration is working!\n";
echo "\nNote: If SMS is not working, OTP will also not work since OTP codes are sent via SMS.\n";
