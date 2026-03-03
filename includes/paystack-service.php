<?php
/**
 * Paystack API Service – mobile money / STK-style charge
 * Used when PAYMENT_PROVIDER=paystack
 */

require_once __DIR__ . '/db.php';

class PaystackService {
    private $db;
    private $config;

    public function __construct() {
        $this->db = getDB();
        $this->config = require __DIR__ . '/../config/paystack.php';
    }

    /**
     * Initiate mobile money charge (STK-style). Returns reference on success, null on failure.
     * Same semantic as MpesaService::initiateSTKPush – caller stores reference in ussd_sessions / web_vote_sessions as checkout_request_id.
     */
    public function initiateCharge($phone, $amount, $reference) {
        if (empty($this->config['secret_key'])) {
            error_log("Paystack Error: PAYSTACK_SECRET_KEY not set in .env");
            return null;
        }

        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (substr($phone, 0, 3) !== '254') {
            $phone = '254' . $phone;
        }
        // Paystack Kenya M-Pesa docs: phone must be with country code, e.g. +254710000000
        $phoneForPaystack = '+' . $phone;

        // Paystack amount in subunits (100 = 1.00 KES); API accepts string
        $amountSubunits = (int) round($amount * 100);

        $payload = [
            'email' => 'vote@voting.local',
            'amount' => (string) $amountSubunits,
            'currency' => 'KES',
            'reference' => preg_replace('/[^a-zA-Z0-9.\-=]/', '', $reference),
            'mobile_money' => [
                'phone' => $phoneForPaystack,
                'provider' => 'mpesa'
            ]
        ];

        $url = 'https://api.paystack.co/charge';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->config['secret_key'],
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Paystack Charge HTTP $httpCode: " . substr($response, 0, 500));
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            error_log("Paystack Charge: Invalid JSON response");
            return null;
        }
        $ok = $data['status'] ?? false;
        $dataObj = $data['data'] ?? [];
        $ref = is_array($dataObj) ? ($dataObj['reference'] ?? $reference) : $reference;
        $dataStatus = is_array($dataObj) ? ($dataObj['status'] ?? '') : '';

        if (!$ok || $dataStatus === 'failed') {
            error_log("Paystack Charge rejected: status=" . ($ok ? 'true' : 'false') . ", data.status=" . $dataStatus . ", message=" . ($data['message'] ?? ''));
            return null;
        }
        // Accept success, send_otp, otp, pending – webhook will send charge.success when user completes payment

        // Store transaction so webhook can find phone/amount
        $stmt = $this->db->prepare("
            INSERT INTO paystack_transactions (reference, phone, amount, amount_subunits, status, raw_response)
            VALUES (?, ?, ?, ?, 'pending', ?)
        ");
        $stmt->execute([$ref, $phone, $amount, $amountSubunits, $response]);

        return $ref;
    }
}
