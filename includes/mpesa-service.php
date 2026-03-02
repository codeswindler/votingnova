<?php
/**
 * M-Pesa Daraja API Service
 * Handles STK Push and payment callbacks
 */

require_once __DIR__ . '/db.php';

class MpesaService {
    private $db;
    private $config;
    private $accessToken = null;
    private $tokenExpiry = null;

    public function __construct() {
        $this->db = getDB();
        $this->config = require __DIR__ . '/../config/mpesa.php';
    }

    /**
     * Get OAuth access token
     */
    private function getAccessToken() {
        // Check if we have a valid token
        if ($this->accessToken && $this->tokenExpiry && time() < $this->tokenExpiry) {
            return $this->accessToken;
        }

        $url = $this->config['base_url'][$this->config['environment']] . '/oauth/v1/generate?grant_type=client_credentials';
        
        $credentials = base64_encode($this->config['consumer_key'] . ':' . $this->config['consumer_secret']);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $credentials
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("M-Pesa Token Error: " . $response);
            return null;
        }

        $data = json_decode($response, true);
        $this->accessToken = $data['access_token'] ?? null;
        $this->tokenExpiry = time() + ($data['expires_in'] ?? 3600) - 60; // 1 minute buffer

        return $this->accessToken;
    }

    /**
     * Generate password for STK Push
     */
    private function generatePassword() {
        $timestamp = date('YmdHis');
        $shortcode = $this->config['shortcode'];
        $passkey = $this->config['passkey'];
        $password = base64_encode($shortcode . $passkey . $timestamp);
        return ['password' => $password, 'timestamp' => $timestamp];
    }

    /**
     * Initiate STK Push
     */
    public function initiateSTKPush($phone, $amount, $sessionId) {
        // Check if we're in simulation mode (session ID starts with "SIM-")
        // or if M-Pesa credentials are not configured
        $isSimulation = (strpos($sessionId, 'SIM-') === 0);
        $hasCredentials = !empty($this->config['consumer_key']) && !empty($this->config['consumer_secret']);
        
        if ($isSimulation || !$hasCredentials) {
            // Simulate STK Push for testing
            $checkoutRequestId = 'SIM-' . $sessionId . '-' . time();
            $merchantRequestId = 'SIM-' . md5($sessionId . time());
            
            // Normalize phone number
            $phone = preg_replace('/[^0-9]/', '', $phone);
            if (substr($phone, 0, 1) === '0') {
                $phone = '254' . substr($phone, 1);
            } elseif (substr($phone, 0, 3) !== '254') {
                $phone = '254' . $phone;
            }
            
            // Store simulated transaction
            $stmt = $this->db->prepare("
                INSERT INTO mpesa_transactions 
                (phone, amount, checkout_request_id, merchant_request_id, status, raw_response)
                VALUES (?, ?, ?, ?, 'pending', ?)
            ");
            $stmt->execute([
                $phone,
                $amount,
                $checkoutRequestId,
                $merchantRequestId,
                json_encode(['simulated' => true, 'session_id' => $sessionId])
            ]);

            return $checkoutRequestId;
        }

        // Real M-Pesa API call
        $token = $this->getAccessToken();
        if (!$token) {
            error_log("STK Push Error: Failed to get M-Pesa access token. Check consumer_key and consumer_secret in .env");
            return null;
        }
        
        // Validate required config
        if (empty($this->config['shortcode']) || empty($this->config['passkey'])) {
            error_log("STK Push Error: Missing M-Pesa shortcode or passkey in .env");
            return null;
        }
        
        if (empty($this->config['callback_url'])) {
            error_log("STK Push Error: Missing M-Pesa callback URL in .env");
            return null;
        }

        // Normalize phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (substr($phone, 0, 3) !== '254') {
            $phone = '254' . $phone;
        }

        $passwordData = $this->generatePassword();
        $callbackUrl = $this->config['callback_url'];
        
        $payload = [
            'BusinessShortCode' => $this->config['shortcode'],
            'Password' => $passwordData['password'],
            'Timestamp' => $passwordData['timestamp'],
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int)$amount,
            'PartyA' => $phone,
            'PartyB' => $this->config['shortcode'],
            'PhoneNumber' => $phone,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => 'VOTE-' . $sessionId,
            'TransactionDesc' => 'Voting Payment'
        ];

        $url = $this->config['base_url'][$this->config['environment']] . '/mpesa/stkpush/v1/processrequest';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("STK Push HTTP Error ($httpCode): " . $response);
            error_log("STK Push Request URL: " . $url);
            error_log("STK Push Payload: " . json_encode($payload));
            return null;
        }

        $data = json_decode($response, true);
        
        if (isset($data['ResponseCode']) && $data['ResponseCode'] == '0') {
            $checkoutRequestId = $data['CheckoutRequestID'];
            
            // Store transaction
            $stmt = $this->db->prepare("
                INSERT INTO mpesa_transactions 
                (phone, amount, checkout_request_id, merchant_request_id, status, raw_response)
                VALUES (?, ?, ?, ?, 'pending', ?)
            ");
            $stmt->execute([
                $phone,
                $amount,
                $checkoutRequestId,
                $data['MerchantRequestID'] ?? null,
                $response
            ]);

            error_log("STK Push Success: CheckoutRequestID = $checkoutRequestId, Phone = $phone, Amount = $amount");
            return $checkoutRequestId;
        }

        $errorMsg = $data['errorMessage'] ?? $data['error_description'] ?? 'Unknown error';
        $errorCode = $data['errorCode'] ?? $data['ResponseCode'] ?? 'Unknown';
        error_log("STK Push Failed - ResponseCode: $errorCode, Message: $errorMsg");
        error_log("STK Push Full Response: " . $response);
        return null;
    }

    /**
     * Process STK Push callback
     */
    public function processCallback($callbackData) {
        $body = json_decode($callbackData, true);
        
        if (!isset($body['Body']['stkCallback'])) {
            error_log("Invalid callback data: " . $callbackData);
            return false;
        }

        $stkCallback = $body['Body']['stkCallback'];
        $checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? null;
        $resultCode = $stkCallback['ResultCode'] ?? null;
        $resultDesc = $stkCallback['ResultDesc'] ?? null;

        // Find transaction
        $stmt = $this->db->prepare("
            SELECT id, phone, amount FROM mpesa_transactions 
            WHERE checkout_request_id = ?
        ");
        $stmt->execute([$checkoutRequestId]);
        $transaction = $stmt->fetch();

        if (!$transaction) {
            error_log("Transaction not found for checkout: " . $checkoutRequestId);
            return false;
        }

        // Update transaction
        $callbackItems = $stkCallback['CallbackMetadata']['Item'] ?? [];
        $mpesaReceiptNumber = null;
        $transactionDate = null;

        foreach ($callbackItems as $item) {
            if ($item['Name'] === 'MpesaReceiptNumber') {
                $mpesaReceiptNumber = $item['Value'];
            }
            if ($item['Name'] === 'TransactionDate') {
                $transactionDate = $item['Value'];
            }
        }

        $status = ($resultCode == 0) ? 'completed' : 'failed';
        
        $stmt = $this->db->prepare("
            UPDATE mpesa_transactions 
            SET result_code = ?, result_desc = ?, status = ?, 
                mpesa_receipt_number = ?, transaction_date = ?, raw_response = ?
            WHERE checkout_request_id = ?
        ");
        $stmt->execute([
            $resultCode,
            $resultDesc,
            $status,
            $mpesaReceiptNumber,
            $transactionDate,
            $callbackData,
            $checkoutRequestId
        ]);

        // Update vote if payment successful
        if ($status === 'completed') {
            $stmt = $this->db->prepare("
                UPDATE votes 
                SET status = 'completed', mpesa_ref = ?
                WHERE transaction_id = ? AND status = 'pending'
            ");
            $stmt->execute([$mpesaReceiptNumber, $checkoutRequestId]);

            // Update nominee vote count
            $stmt = $this->db->prepare("
                UPDATE nominees n
                INNER JOIN votes v ON n.id = v.nominee_id
                SET n.votes_count = n.votes_count + v.votes_count
                WHERE v.transaction_id = ? AND v.status = 'completed'
            ");
            $stmt->execute([$checkoutRequestId]);

            // Send SMS confirmation with vote details
            $this->sendSMSConfirmation($checkoutRequestId, $transaction['phone'], $mpesaReceiptNumber, $transaction['amount']);
        }

        return true;
    }

    /**
     * Send SMS confirmation via Advanta
     */
    private function sendSMSConfirmation($checkoutRequestId, $phone, $receiptNumber, $amount) {
        $config = require __DIR__ . '/../config/advanta.php';
        
        if (empty($config['api_key']) || empty($config['partner_id'])) {
            return; // SMS not configured
        }

        // Get vote details including nominee and category
        $stmt = $this->db->prepare("
            SELECT v.votes_count, n.name as nominee_name, c.name as category_name
            FROM votes v
            INNER JOIN nominees n ON v.nominee_id = n.id
            INNER JOIN categories c ON n.category_id = c.id
            WHERE v.transaction_id = ? AND v.status = 'completed'
            LIMIT 1
        ");
        $stmt->execute([$checkoutRequestId]);
        $voteDetails = $stmt->fetch();

        if ($voteDetails) {
            $message = "Thank you for voting! You voted {$voteDetails['votes_count']} time(s) for {$voteDetails['nominee_name']} in the {$voteDetails['category_name']} category. Amount: KES " . number_format($amount, 2) . ". Receipt: {$receiptNumber}.";
        } else {
            $message = "Your vote payment of KES " . number_format($amount, 2) . " was successful. Receipt: {$receiptNumber}. Thank you for voting!";
        }
        
        $url = $config['sms_api_url'];
        $payload = [
            'api_key' => $config['api_key'],
            'partner_id' => $config['partner_id'],
            'shortcode' => $config['shortcode'],
            'mobile' => $phone,
            'message' => $message
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        curl_exec($ch);
        curl_close($ch);
    }
}
