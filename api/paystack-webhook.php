<?php
/**
 * Paystack Webhook – verify signature, then create vote + send SMS (same flow as M-Pesa callback)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/env.php';

$rawInput = file_get_contents('php://input');
if ($rawInput === false || $rawInput === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Empty body']);
    exit;
}

$secret = getenv('PAYSTACK_WEBHOOK_SECRET') ?: '';
if ($secret === '' || strpos($secret, 'http://') === 0 || strpos($secret, 'https://') === 0) {
    if (strpos($secret ?: '', 'http') === 0) {
        error_log("Paystack Webhook: PAYSTACK_WEBHOOK_SECRET must be the secret key (from Paystack dashboard), not the webhook URL. Fix your .env.");
    } else {
        error_log("Paystack Webhook: PAYSTACK_WEBHOOK_SECRET not set");
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Webhook not configured']);
    exit;
}

$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
if ($signature === '') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Missing signature']);
    exit;
}

$computed = hash_hmac('sha512', $rawInput, $secret);
if (!hash_equals($computed, $signature)) {
    error_log("Paystack Webhook: signature mismatch");
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
    exit;
}

$event = json_decode($rawInput, true);
$eventType = $event['event'] ?? '';

if ($eventType === 'charge.failed') {
    $data = $event['data'] ?? [];
    $reference = $data['reference'] ?? null;
    if ($reference) {
        try {
            $db = getDB();
            $stmt = $db->prepare("UPDATE paystack_transactions SET status = 'failed', raw_response = ? WHERE reference = ?");
            $stmt->execute([$rawInput, $reference]);
        } catch (Exception $e) {
            error_log("Paystack Webhook charge.failed: " . $e->getMessage());
        }
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($eventType !== 'charge.success') {
    echo json_encode(['status' => 'ok']);
    exit;
}

$data = $event['data'] ?? [];
$reference = $data['reference'] ?? null;
if (!$reference) {
    echo json_encode(['status' => 'ok']);
    exit;
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id, phone, amount FROM paystack_transactions WHERE reference = ?");
    $stmt->execute([$reference]);
    $transaction = $stmt->fetch();

    if (!$transaction) {
        error_log("Paystack Webhook: transaction not found for reference " . $reference);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    $stmt = $db->prepare("UPDATE paystack_transactions SET status = 'success', raw_response = ? WHERE reference = ?");
    $stmt->execute([$rawInput, $reference]);

    // Find session by reference (same as M-Pesa: checkout_request_id holds the reference)
    $stmt = $db->prepare("SELECT nominee_id, votes_count, amount FROM ussd_sessions WHERE checkout_request_id = ?");
    $stmt->execute([$reference]);
    $session = $stmt->fetch();

    if (!$session || !$session['nominee_id'] || !$session['votes_count']) {
        $stmt = $db->prepare("SELECT nominee_id, votes_count, amount FROM web_vote_sessions WHERE checkout_request_id = ?");
        $stmt->execute([$reference]);
        $session = $stmt->fetch();
    }

    if (!$session || !$session['nominee_id'] || !$session['votes_count']) {
        error_log("Paystack Webhook: session not found for reference " . $reference);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    $receipt = $data['reference'] ?? $reference;

    $stmt = $db->prepare("
        INSERT INTO votes (nominee_id, phone, votes_count, amount, status, mpesa_ref, transaction_id)
        VALUES (?, ?, ?, ?, 'completed', ?, ?)
    ");
    $stmt->execute([
        $session['nominee_id'],
        $transaction['phone'],
        $session['votes_count'],
        $session['amount'],
        $receipt,
        $reference
    ]);

    $stmt = $db->prepare("UPDATE nominees SET votes_count = votes_count + ? WHERE id = ?");
    $stmt->execute([$session['votes_count'], $session['nominee_id']]);

    // SMS confirmation (same as M-Pesa callback)
    $stmt = $db->prepare("
        SELECT v.votes_count, n.name as nominee_name, c.name as category_name
        FROM votes v
        INNER JOIN nominees n ON v.nominee_id = n.id
        INNER JOIN categories c ON n.category_id = c.id
        WHERE v.transaction_id = ? AND v.status = 'completed'
        LIMIT 1
    ");
    $stmt->execute([$reference]);
    $voteDetails = $stmt->fetch();

    $config = require __DIR__ . '/../config/advanta.php';
    if (!empty($config['api_key']) && !empty($config['partner_id'])) {
        $amount = $transaction['amount'];
        if ($voteDetails) {
            $message = "Thank you for voting! You voted {$voteDetails['votes_count']} time(s) for {$voteDetails['nominee_name']} in the {$voteDetails['category_name']} category";
        } else {
            $message = "Thank you for voting!";
        }
        $url = $config['sms_api_url'];
        $payload = [
            'apikey' => $config['api_key'],
            'partnerID' => $config['partner_id'],
            'shortcode' => $config['shortcode'],
            'mobile' => $transaction['phone'],
            'message' => $message
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_exec($ch);
        curl_close($ch);
    }
} catch (Exception $e) {
    error_log("Paystack Webhook Error: " . $e->getMessage());
}

echo json_encode(['status' => 'ok']);
