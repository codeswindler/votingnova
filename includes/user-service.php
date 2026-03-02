<?php
/**
 * User Service - Handles user password generation and credential sending
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../config/advanta.php';

class UserService {
    private $db;
    private $smsConfig;

    public function __construct() {
        $this->db = getDB();
        $this->smsConfig = require __DIR__ . '/../config/advanta.php';
    }

    /**
     * Generate a random password
     */
    public function generatePassword($length = 8) {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }

    /**
     * Generate and send user credentials via SMS
     */
    public function generateAndSendCredentials($userId) {
        $stmt = $this->db->prepare("
            SELECT id, phone, full_name, email 
            FROM system_users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        // Generate temporary password
        $tempPassword = $this->generatePassword(8);
        $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

        // Update user with temp password and flag for password change
        $updateStmt = $this->db->prepare("
            UPDATE system_users 
            SET temp_password = ?,
                password_hash = ?,
                must_change_password = 1,
                last_password_change = NULL
            WHERE id = ?
        ");
        $updateStmt->execute([$tempPassword, $passwordHash, $userId]);

        // Send credentials via SMS
        $smsSent = $this->sendCredentialsSMS($user['phone'], $user['full_name'] ?: 'User', $tempPassword);

        if ($smsSent) {
            return [
                'success' => true, 
                'message' => 'Credentials generated and sent successfully',
                'temp_password' => $tempPassword // Return for display (remove in production)
            ];
        } else {
            return [
                'success' => false, 
                'message' => 'Password generated but failed to send SMS. Password: ' . $tempPassword
            ];
        }
    }

    /**
     * Send credentials via SMS using Advanta SMS API
     */
    private function sendCredentialsSMS($phone, $name, $password) {
        if (empty($this->smsConfig['api_key']) || empty($this->smsConfig['partner_id'])) {
            error_log("User Credentials SMS: Missing Advanta API credentials");
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

        $message = "Hello {$name}, your Voting System credentials have been created. Username: {$phone}, Password: {$password}. Please change your password on first login.";

        $data = [
            'api_key' => $this->smsConfig['api_key'],
            'partner_id' => $this->smsConfig['partner_id'],
            'shortcode' => $this->smsConfig['shortcode'],
            'message' => $message,
            'mobile' => $phone
        ];

        $ch = curl_init($this->smsConfig['sms_api_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['status']) && $result['status'] === 'success') {
                return true;
            }
        }

        error_log("User Credentials SMS failed: HTTP {$httpCode}, Response: {$response}");
        return false;
    }

    /**
     * Verify user password
     */
    public function verifyPassword($userId, $password) {
        $stmt = $this->db->prepare("
            SELECT password_hash, must_change_password 
            FROM system_users 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !$user['password_hash']) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        if (password_verify($password, $user['password_hash'])) {
            return [
                'success' => true, 
                'must_change_password' => (bool)$user['must_change_password']
            ];
        }

        return ['success' => false, 'message' => 'Invalid password'];
    }

    /**
     * Reset user password (forgot password)
     */
    public function resetPassword($userId) {
        $stmt = $this->db->prepare("
            SELECT id, phone, full_name 
            FROM system_users 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'User not found or inactive'];
        }

        // Generate temporary password
        $tempPassword = $this->generatePassword(8);
        $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

        // Update user with temp password and flag for password change
        $updateStmt = $this->db->prepare("
            UPDATE system_users 
            SET temp_password = ?,
                password_hash = ?,
                must_change_password = 1,
                last_password_change = NULL
            WHERE id = ?
        ");
        $updateStmt->execute([$tempPassword, $passwordHash, $userId]);

        // Send temporary password via SMS
        $smsSent = $this->sendResetPasswordSMS($user['phone'], $user['full_name'] ?: 'User', $tempPassword);

        if ($smsSent) {
            return [
                'success' => true, 
                'message' => 'Temporary password sent successfully',
                'temp_password' => $tempPassword // Return for testing (remove in production)
            ];
        } else {
            return [
                'success' => false, 
                'message' => 'Password reset but failed to send SMS. Please contact administrator.'
            ];
        }
    }

    /**
     * Send password reset SMS
     */
    private function sendResetPasswordSMS($phone, $name, $tempPassword) {
        if (empty($this->smsConfig['api_key']) || empty($this->smsConfig['partner_id'])) {
            error_log("Password Reset SMS: Missing Advanta API credentials");
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

        $message = "Hello {$name}, your password has been reset. Temporary password: {$tempPassword}. Username: {$phone}. Please login and change your password immediately.";

        $data = [
            'api_key' => $this->smsConfig['api_key'],
            'partner_id' => $this->smsConfig['partner_id'],
            'shortcode' => $this->smsConfig['shortcode'],
            'message' => $message,
            'mobile' => $phone
        ];

        $ch = curl_init($this->smsConfig['sms_api_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['status']) && $result['status'] === 'success') {
                return true;
            }
        }

        error_log("Password Reset SMS failed: HTTP {$httpCode}, Response: {$response}");
        return false;
    }

    /**
     * Change user password
     */
    public function changePassword($userId, $newPassword) {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare("
            UPDATE system_users 
            SET password_hash = ?,
                temp_password = NULL,
                must_change_password = 0,
                last_password_change = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$passwordHash, $userId]);

        return $stmt->rowCount() > 0;
    }
}
