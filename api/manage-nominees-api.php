<?php
/**
 * Nominees Management API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

Auth::requireLogin();

$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        getNominees($db);
        break;
    
    case 'add':
        addNominee($db);
        break;
    
    case 'update':
        updateNominee($db);
        break;
    
    case 'delete':
        deleteNominee($db);
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function getNominees($db) {
    $categoryId = (int)($_GET['category_id'] ?? 0);
    
    if (!$categoryId) {
        http_response_code(400);
        echo json_encode(['error' => 'Category ID is required']);
        return;
    }
    
    $stmt = $db->prepare("
        SELECT id, name, gender, votes_count
        FROM nominees
        WHERE category_id = ?
        ORDER BY gender, name
    ");
    $stmt->execute([$categoryId]);
    $nominees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'nominees' => $nominees
    ]);
}

function addNominee($db) {
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    
    if (!$categoryId || !$name || !$gender) {
        http_response_code(400);
        echo json_encode(['error' => 'Category ID, name, and gender are required']);
        return;
    }
    
    if (!in_array($gender, ['Male', 'Female'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Gender must be Male or Female']);
        return;
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO nominees (category_id, name, gender) VALUES (?, ?, ?)");
        $stmt->execute([$categoryId, $name, $gender]);
        
        $nomineeId = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Nominee added successfully',
            'nominee' => [
                'id' => $nomineeId,
                'name' => $name,
                'gender' => $gender,
                'votes_count' => 0
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to add nominee: ' . $e->getMessage()]);
    }
}

function updateNominee($db) {
    $nomineeId = (int)($_POST['nominee_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    
    if (!$nomineeId || !$name || !$gender) {
        http_response_code(400);
        echo json_encode(['error' => 'Nominee ID, name, and gender are required']);
        return;
    }
    
    if (!in_array($gender, ['Male', 'Female'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Gender must be Male or Female']);
        return;
    }
    
    try {
        $stmt = $db->prepare("UPDATE nominees SET name = ?, gender = ? WHERE id = ?");
        $stmt->execute([$name, $gender, $nomineeId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Nominee updated successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update nominee: ' . $e->getMessage()]);
    }
}

function deleteNominee($db) {
    $nomineeId = (int)($_POST['nominee_id'] ?? $_GET['nominee_id'] ?? 0);
    
    if (!$nomineeId) {
        http_response_code(400);
        echo json_encode(['error' => 'Nominee ID is required']);
        return;
    }
    
    try {
        // Delete nominee (database has ON DELETE CASCADE on votes table,
        // so this will also automatically and safely remove associated votes)
        $stmt = $db->prepare("DELETE FROM nominees WHERE id = ?");
        $stmt->execute([$nomineeId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Nominee deleted successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete nominee: ' . $e->getMessage()]);
    }
}
