<?php
/**
 * Test USSD Endpoint
 * Simulates an Advanta request
 */

// Simulate the exact request from Advanta
$_GET['SESSIONID'] = 'TEST_' . time();
$_GET['USSDCODE'] = '*519#';
$_GET['MSISDN'] = '254727839315';
$_GET['INPUT'] = '';

echo "=== Testing USSD Endpoint ===\n\n";
echo "Parameters:\n";
echo "  SESSIONID: " . $_GET['SESSIONID'] . "\n";
echo "  USSDCODE: " . $_GET['USSDCODE'] . "\n";
echo "  MSISDN: " . $_GET['MSISDN'] . "\n";
echo "  INPUT: " . ($_GET['INPUT'] ?: '(empty)') . "\n\n";

echo "Response:\n";
echo "----------------------------------------\n";

// Capture output
ob_start();
require __DIR__ . '/api/ussd.php';
$output = ob_get_clean();

echo $output;
echo "\n----------------------------------------\n\n";

echo "=== Database Check ===\n";
try {
    require_once __DIR__ . '/includes/db.php';
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM categories");
    $result = $stmt->fetch();
    echo "Categories in database: " . $result['count'] . "\n";
    
    if ($result['count'] == 0) {
        echo "⚠️  WARNING: No categories found! This will cause the USSD menu to fail.\n";
        echo "   Import categories using: mysql -u votingnova_user -p votingnova < import-categories-nominees.sql\n";
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}
