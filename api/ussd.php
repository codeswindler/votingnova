<?php
/**
 * USSD Callback Endpoint
 * Handles incoming USSD requests from Advanta
 */

header('Content-Type: text/plain');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ussd-handler.php';

// Get parameters from Advanta
$sessionId = $_GET['SESSIONID'] ?? $_POST['SESSIONID'] ?? '';
$ussdCode = $_GET['USSDCODE'] ?? $_POST['USSDCODE'] ?? '';
$msisdn = $_GET['MSISDN'] ?? $_POST['MSISDN'] ?? '';
$input = $_GET['INPUT'] ?? $_POST['INPUT'] ?? '';

// Log incoming request for debugging
error_log("USSD Request - SESSIONID: $sessionId, USSDCODE: $ussdCode, MSISDN: $msisdn, INPUT: $input");

// Validate required parameters
if (empty($sessionId) || empty($msisdn)) {
    error_log("USSD Error: Missing required parameters - SESSIONID: " . ($sessionId ? 'present' : 'missing') . ", MSISDN: " . ($msisdn ? 'present' : 'missing'));
    echo "END Invalid request parameters.";
    exit;
}

try {
    $handler = new USSDHandler($sessionId, $msisdn, $input);
    $response = $handler->process();
    error_log("USSD Response: " . substr($response, 0, 100));
    echo $response;
} catch (Exception $e) {
    error_log("USSD Exception: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    echo "END An error occurred. Please try again later.";
} catch (Error $e) {
    error_log("USSD Fatal Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    echo "END An error occurred. Please try again later.";
}
