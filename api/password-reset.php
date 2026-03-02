<?php
/**
 * Password Reset API - For system users
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user-service.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$phone = $input['phone'] ?? '';

if (empty($phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Phone number is required']);
    exit;
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

try {
    $db = getDB();
    
    // Find user by phone
    $stmt = $db->prepare("SELECT id, phone, full_name, is_active FROM system_users WHERE phone = ?");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found with this phone number']);
        exit;
    }
    
    if (!$user['is_active']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Your account is inactive. Please contact administrator.']);
        exit;
    }
    
    // Generate and send temporary password
    $userService = new UserService();
    $result = $userService->resetPassword($user['id']);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Temporary password has been sent to your registered phone number. Please login and change your password immediately.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $result['message'] ?? 'Failed to send reset password'
        ]);
    }
} catch (Exception $e) {
    error_log("Password Reset Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred. Please try again later.']);
}
