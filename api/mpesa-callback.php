<?php
/**
 * M-Pesa STK Push Callback Endpoint
 * Handles payment callbacks from Safaricom
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mpesa-service.php';

// Get raw POST data
$callbackData = file_get_contents('php://input');

if (empty($callbackData)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Empty callback data']);
    exit;
}

try {
    $mpesaService = new MpesaService();
    $result = $mpesaService->processCallback($callbackData);
    
    if ($result) {
        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Failed to process callback']);
    }
} catch (Exception $e) {
    error_log("M-Pesa Callback Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
}
