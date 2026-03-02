<?php
/**
 * Admin Users Management API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/otp-service.php';
require_once __DIR__ . '/../includes/user-service.php';

Auth::requireLogin();

$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        getUsers($db);
        break;
    
    case 'create':
        createUser($db);
        break;
    
    case 'update':
        updateUser($db);
        break;
    
    case 'delete':
        deleteUser($db);
        break;
    
    case 'toggle_otp':
        toggleUserOTP($db);
        break;
    
    case 'generate_credentials':
        generateCredentials($db);
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function getUsers($db) {
    $search = $_GET['search'] ?? '';
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(phone LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?)";
        $searchParam = "%{$search}%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $stmt = $db->prepare("
        SELECT u.*, 
               a.username as created_by_username,
               COUNT(DISTINCT o.id) as otp_count
        FROM system_users u
        LEFT JOIN admin_users a ON u.created_by = a.id
        LEFT JOIN otp_codes o ON u.id = o.user_id AND o.is_used = 0
        {$whereClause}
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // Get total count
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM system_users {$whereClause}");
    if ($where) {
        $countParams = array_slice($params, 0, -2);
        $countStmt->execute($countParams);
    } else {
        $countStmt->execute();
    }
    $total = $countStmt->fetch()['total'];
    
    echo json_encode([
        'users' => $users,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

function createUser($db) {
    $phone = $_POST['phone'] ?? '';
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $otpEnabled = isset($_POST['otp_enabled']) ? (int)$_POST['otp_enabled'] : 0;
    
    if (empty($phone)) {
        http_response_code(400);
        echo json_encode(['error' => 'Phone number is required']);
        return;
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
    
    // Check if user already exists
    $checkStmt = $db->prepare("SELECT id FROM system_users WHERE phone = ?");
    $checkStmt->execute([$phone]);
    if ($checkStmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'User with this phone number already exists']);
        return;
    }
    
    $adminId = Auth::getUserId();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO system_users (phone, first_name, last_name, email, otp_enabled, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$phone, $firstName, $lastName, $email, $otpEnabled, $adminId]);
        
        $userId = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'User created successfully',
            'user_id' => $userId
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create user: ' . $e->getMessage()]);
    }
}

function updateUser($db) {
    $userId = (int)($_POST['user_id'] ?? 0);
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $otpEnabled = isset($_POST['otp_enabled']) ? (int)$_POST['otp_enabled'] : 0;
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID is required']);
        return;
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE system_users
            SET first_name = ?, last_name = ?, email = ?, otp_enabled = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$firstName, $lastName, $email, $otpEnabled, $isActive, $userId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'User updated successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update user: ' . $e->getMessage()]);
    }
}

function deleteUser($db) {
    $userId = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID is required']);
        return;
    }
    
    try {
        // Check if user exists
        $stmt = $db->prepare("SELECT id FROM system_users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }
        
        // Prevent users from deleting themselves
        $currentUserId = Auth::getUserId();
        $currentUserType = $_SESSION['user_type'] ?? null;
        
        // Only check for system users (admins can delete system users, but system users can't delete themselves)
        if ($currentUserType === 'system_user' && $currentUserId == $userId) {
            http_response_code(400);
            echo json_encode(['error' => 'You cannot delete your own account. Please contact an administrator.']);
            return;
        }
        
        // Delete user (users can always be deleted - votes are associated with nominees, not users)
        $stmt = $db->prepare("DELETE FROM system_users WHERE id = ?");
        $stmt->execute([$userId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete user: ' . $e->getMessage()]);
    }
}

function toggleUserOTP($db) {
    $userId = (int)($_POST['user_id'] ?? 0);
    $enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : 0;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID is required']);
        return;
    }
    
    try {
        $stmt = $db->prepare("UPDATE system_users SET otp_enabled = ? WHERE id = ?");
        $stmt->execute([$enabled, $userId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'User OTP setting updated successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update OTP setting: ' . $e->getMessage()]);
    }
}

function generateCredentials($db) {
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID is required']);
        return;
    }
    
    try {
        $userService = new UserService();
        $result = $userService->generateAndSendCredentials($userId);
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'temp_password' => $result['temp_password'] ?? null
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $result['message']
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate credentials: ' . $e->getMessage()]);
    }
}
