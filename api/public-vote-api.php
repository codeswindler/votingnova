<?php
/**
 * Public Vote API - Unauthenticated
 * Categories, nominees, initiate STK push, payment status (same flow as USSD)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/mpesa-service.php';
require_once __DIR__ . '/../includes/paystack-service.php';

$votePrice = 10; // KES per vote - same as USSD

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();

    switch ($action) {
        case 'categories':
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $stmt = $db->query("SELECT id, name, description FROM categories ORDER BY id");
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'categories' => $categories]);
            break;

        case 'nominees':
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $categoryId = (int)($_GET['category_id'] ?? 0);
            $gender = $_GET['gender'] ?? '';
            if (!$categoryId) {
                http_response_code(400);
                echo json_encode(['error' => 'category_id is required']);
                exit;
            }
            if (!in_array($gender, ['Male', 'Female'], true)) {
                http_response_code(400);
                echo json_encode(['error' => 'gender must be Male or Female']);
                exit;
            }
            $stmt = $db->prepare("
                SELECT id, name, gender
                FROM nominees
                WHERE category_id = ? AND gender = ?
                ORDER BY name
            ");
            $stmt->execute([$categoryId, $gender]);
            $nominees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'nominees' => $nominees]);
            break;

        case 'vote-price':
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            echo json_encode(['success' => true, 'vote_price' => $votePrice]);
            break;

        case 'initiate-vote':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $phone = trim($input['phone'] ?? $_POST['phone'] ?? '');
            $nomineeId = (int)($input['nominee_id'] ?? $_POST['nominee_id'] ?? 0);
            $votesCount = (int)($input['votes_count'] ?? $_POST['votes_count'] ?? 0);

            if (!$phone) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Phone number is required']);
                exit;
            }
            $phone = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($phone) < 9) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid phone number']);
                exit;
            }
            if (substr($phone, 0, 1) === '0') {
                $phone = '254' . substr($phone, 1);
            } elseif (substr($phone, 0, 3) !== '254') {
                $phone = '254' . $phone;
            }
            if (!$nomineeId || $votesCount < 1 || $votesCount > 1000) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Valid nominee_id and votes_count (1–1000) required']);
                exit;
            }

            $stmt = $db->prepare("SELECT id, name FROM nominees WHERE id = ?");
            $stmt->execute([$nomineeId]);
            $nominee = $stmt->fetch();
            if (!$nominee) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Nominee not found']);
                exit;
            }

            $amount = $votesCount * $votePrice;
            $sessionRef = 'web-' . uniqid('', true);

            $provider = strtolower(trim(getenv('PAYMENT_PROVIDER') ?: 'mpesa'));
            $checkoutRequestId = null;

            if ($provider === 'paystack') {
                $paystack = new PaystackService();
                $checkoutRequestId = $paystack->initiateCharge($phone, $amount, $sessionRef);
            } else {
                $mpesaService = new MpesaService();
                $checkoutRequestId = $mpesaService->initiateSTKPush($phone, $amount, $sessionRef);
            }

            if (!$checkoutRequestId) {
                http_response_code(502);
                echo json_encode(['success' => false, 'error' => 'Payment initiation failed. Please try again.']);
                exit;
            }

            $stmt = $db->prepare("
                INSERT INTO web_vote_sessions (checkout_request_id, nominee_id, votes_count, amount, phone)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$checkoutRequestId, $nomineeId, $votesCount, $amount, $phone]);

            echo json_encode([
                'success' => true,
                'checkout_request_id' => $checkoutRequestId,
                'message' => 'Please check your phone for the payment prompt to complete payment.'
            ]);
            break;

        case 'payment-status':
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $checkoutRequestId = $_GET['checkout_request_id'] ?? '';
            if (!$checkoutRequestId) {
                http_response_code(400);
                echo json_encode(['error' => 'checkout_request_id is required']);
                exit;
            }

            $stmt = $db->prepare("
                SELECT status, mpesa_receipt_number, result_desc
                FROM mpesa_transactions
                WHERE checkout_request_id = ?
            ");
            $stmt->execute([$checkoutRequestId]);
            $tx = $stmt->fetch();

            if (!$tx) {
                $stmt = $db->prepare("SELECT status FROM paystack_transactions WHERE reference = ?");
                $stmt->execute([$checkoutRequestId]);
                $pt = $stmt->fetch();
                if ($pt) {
                    $tx = [
                        'status' => $pt['status'] === 'success' ? 'completed' : ($pt['status'] === 'failed' ? 'failed' : 'pending'),
                        'mpesa_receipt_number' => $pt['status'] === 'success' ? $checkoutRequestId : null
                    ];
                }
            }

            if (!$tx) {
                echo json_encode(['success' => true, 'status' => 'pending', 'message' => 'Waiting for payment.']);
                exit;
            }

            if ($tx['status'] === 'completed') {
                $stmt = $db->prepare("
                    SELECT v.votes_count, n.name as nominee_name
                    FROM votes v
                    JOIN nominees n ON v.nominee_id = n.id
                    WHERE v.transaction_id = ? AND v.status = 'completed'
                    LIMIT 1
                ");
                $stmt->execute([$checkoutRequestId]);
                $vote = $stmt->fetch();
                echo json_encode([
                    'success' => true,
                    'status' => 'completed',
                    'receipt' => $tx['mpesa_receipt_number'],
                    'nominee_name' => $vote['nominee_name'] ?? null,
                    'votes_count' => $vote['votes_count'] ?? null,
                    'message' => 'Payment successful. Your vote has been recorded.'
                ]);
                exit;
            }

            if ($tx['status'] === 'failed') {
                echo json_encode([
                    'success' => true,
                    'status' => 'failed',
                    'message' => $tx['result_desc'] ?? 'Payment failed. Please try again.'
                ]);
                exit;
            }

            echo json_encode(['success' => true, 'status' => 'pending', 'message' => 'Please complete the payment on your phone.']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action. Use: categories, nominees, vote-price, initiate-vote, payment-status']);
    }
} catch (Exception $e) {
    error_log("Public Vote API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
