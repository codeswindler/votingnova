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

// Get parameters from Advanta (try both GET and POST)
$sessionId = $_GET['SESSIONID'] ?? $_POST['SESSIONID'] ?? $_REQUEST['SESSIONID'] ?? '';
$ussdCode = $_GET['USSDCODE'] ?? $_POST['USSDCODE'] ?? $_REQUEST['USSDCODE'] ?? '';
$msisdn = $_GET['MSISDN'] ?? $_POST['MSISDN'] ?? $_REQUEST['MSISDN'] ?? '';
$input = $_GET['INPUT'] ?? $_POST['INPUT'] ?? $_REQUEST['INPUT'] ?? '';

// URL decode if needed
$ussdCode = urldecode($ussdCode);
$input = urldecode($input);

// Log incoming request for debugging (including raw request data)
error_log("USSD Request - Method: " . $_SERVER['REQUEST_METHOD'] . ", SESSIONID: $sessionId, USSDCODE: $ussdCode, MSISDN: $msisdn, INPUT: $input");
error_log("USSD Raw GET: " . print_r($_GET, true));
error_log("USSD Raw POST: " . print_r($_POST, true));

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
