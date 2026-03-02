<?php
/**
 * USSD Callback Endpoint
 * Handles incoming USSD requests from Advanta
 */

header('Content-Type: text/plain');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ussd-handler.php';

// Get parameters from Advanta
$sessionId = $_GET['SESSIONID'] ?? $_POST['SESSIONID'] ?? '';
$ussdCode = $_GET['USSDCODE'] ?? $_POST['USSDCODE'] ?? '';
$msisdn = $_GET['MSISDN'] ?? $_POST['MSISDN'] ?? '';
$input = $_GET['INPUT'] ?? $_POST['INPUT'] ?? '';

// Validate required parameters
if (empty($sessionId) || empty($msisdn)) {
    echo "END Invalid request parameters.";
    exit;
}

try {
    $handler = new USSDHandler($sessionId, $msisdn, $input);
    $response = $handler->process();
    echo $response;
} catch (Exception $e) {
    error_log("USSD Error: " . $e->getMessage());
    echo "END An error occurred. Please try again later.";
}
