<?php
/**
 * OTP Service - Handles OTP generation and SMS sending
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../config/advanta.php';

class OTPService {
    private $db;
    private $smsConfig;
    private $otpExpiryMinutes = 10; // OTP expires in 10 minutes

    public function __construct() {
        $this->db = getDB();
        $this->smsConfig = require __DIR__ . '/../config/advanta.php';
    }

    /**
     * Generate a 6-digit OTP code
     */
    private function generateOTP() {
        return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Check if OTP is enabled globally
     */
    public function isOTPEnabled() {
        $stmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'otp_enabled'");
        $stmt->execute();
        $result = $stmt->fetch();
        
        return $result && $result['setting_value'] === '1';
    }

    /**
     * Toggle OTP on/off globally
     */
    public function toggleOTP($enabled, $adminId) {
        $stmt = $this->db->prepare("
            INSERT INTO system_settings (setting_key, setting_value, description, updated_by)
            VALUES ('otp_enabled', ?, 'Enable/Disable OTP for user login', ?)
            ON DUPLICATE KEY UPDATE 
                setting_value = ?,
                updated_by = ?,
                updated_at = NOW()
        ");
        $value = $enabled ? '1' : '0';
        $stmt->execute([$value, $adminId, $value, $adminId]);
        return true;
    }

    /**
     * Generate and send OTP to user
     */
    public function generateAndSendOTP($userId, $phone, $purpose = 'login') {
        // Check if OTP is enabled globally
        if (!$this->isOTPEnabled()) {
            return ['success' => false, 'message' => 'OTP is currently disabled'];
        }

        // Check if user has OTP enabled
        $userStmt = $this->db->prepare("SELECT otp_enabled FROM system_users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        
        if (!$user || !$user['otp_enabled']) {
            return ['success' => false, 'message' => 'OTP is not enabled for this user'];
        }

        // Generate OTP
        $code = $this->generateOTP();
        $expiresAt = date('Y-m-d H:i:s', time() + ($this->otpExpiryMinutes * 60));

        // Invalidate previous unused OTPs for this user
        $invalidateStmt = $this->db->prepare("
            UPDATE otp_codes 
            SET is_used = 1 
            WHERE user_id = ? AND is_used = 0 AND purpose = ?
        ");
        $invalidateStmt->execute([$userId, $purpose]);

        // Save OTP to database
        $stmt = $this->db->prepare("
            INSERT INTO otp_codes (user_id, phone, code, purpose, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $phone, $code, $purpose, $expiresAt]);

        // Send OTP via SMS
        $smsSent = $this->sendOTPSMS($phone, $code);

        if ($smsSent) {
            return ['success' => true, 'message' => 'OTP sent successfully', 'code' => $code]; // Return code for testing
        } else {
            return ['success' => false, 'message' => 'Failed to send OTP via SMS'];
        }
    }

    /**
     * Verify OTP code
     */
    public function verifyOTP($userId, $code, $purpose = 'login') {
        $stmt = $this->db->prepare("
            SELECT id, expires_at, is_used
            FROM otp_codes
            WHERE user_id = ? AND code = ? AND purpose = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $code, $purpose]);
        $otp = $stmt->fetch();

        if (!$otp) {
            return ['success' => false, 'message' => 'Invalid OTP code'];
        }

        if ($otp['is_used']) {
            return ['success' => false, 'message' => 'OTP code has already been used'];
        }

        if (strtotime($otp['expires_at']) < time()) {
            return ['success' => false, 'message' => 'OTP code has expired'];
        }

        // Mark OTP as used
        $updateStmt = $this->db->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?");
        $updateStmt->execute([$otp['id']]);

        return ['success' => true, 'message' => 'OTP verified successfully'];
    }

    /**
     * Send OTP via SMS using Advanta SMS API
     */
    private function sendOTPSMS($phone, $code) {
        if (empty($this->smsConfig['api_key']) || empty($this->smsConfig['partner_id'])) {
            error_log("OTP SMS: Missing Advanta API credentials");
            return false;
        }

        // Normalize phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 3) !== '254') {
            if (substr($phone, 0, 1) === '0') {
                $phone = '254' . substr($phone, 1);
            } else {
                $phone = '254' . $phone;
            }
        }

        $message = "Your OTP code is: {$code}. Valid for {$this->otpExpiryMinutes} minutes. Do not share this code.";

        $data = [
            'api_key' => $this->smsConfig['api_key'],
            'partner_id' => $this->smsConfig['partner_id'],
            'shortcode' => $this->smsConfig['shortcode'],
            'message' => $message,
            'mobile' => $phone
        ];

        $url = $this->smsConfig['sms_api_url'];
        error_log("OTP SMS: Sending to $phone, URL: $url, Payload: " . json_encode($data));
        
        $ch = curl_init($url);
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

        if ($curlError) {
            error_log("OTP SMS cURL Error: $curlError");
            return false;
        }

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['status']) && $result['status'] === 'success') {
                error_log("OTP SMS: Successfully sent to $phone");
                return true;
            }
        }

        error_log("OTP SMS failed: HTTP {$httpCode}, Response: {$response}");
        return false;
    }

    /**
     * Clean up expired OTPs
     */
    public function cleanupExpiredOTPs() {
        $stmt = $this->db->prepare("DELETE FROM otp_codes WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
