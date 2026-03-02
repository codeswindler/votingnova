<?php
/**
 * Admin Dashboard API Endpoints
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Check authentication
Auth::requireLogin();

$action = $_GET['action'] ?? '';

try {
    $db = getDB();
    
    switch ($action) {
        case 'stats':
            echo json_encode(getStats($db));
            break;
        
        case 'category-leaderboard':
            $categoryId = (int)($_GET['category_id'] ?? 0);
            echo json_encode(getCategoryLeaderboard($db, $categoryId));
            break;
        
        case 'transactions':
            echo json_encode(getTransactions($db));
            break;
        
        case 'winners':
            $categoryId = (int)($_GET['category_id'] ?? 0);
            echo json_encode(getWinners($db, $categoryId));
            break;
        
        case 'votes-by-category':
            echo json_encode(getVotesByCategory($db));
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Admin API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Get dashboard statistics
 * Shows all votes (both simulated and real M-Pesa transactions)
 */
function getStats($db) {
    // Total votes - all votes (real and simulated)
    $stmt = $db->query("SELECT SUM(votes_count) as total FROM votes");
    $totalVotes = $stmt->fetch()['total'] ?? 0;
    
    // Total revenue - all votes (real and simulated)
    $stmt = $db->query("SELECT SUM(amount) as total FROM votes");
    $totalRevenue = $stmt->fetch()['total'] ?? 0;
    
    // Total transactions - all votes (real and simulated)
    $stmt = $db->query("SELECT COUNT(*) as total FROM votes");
    $totalTransactions = $stmt->fetch()['total'] ?? 0;
    
    // Pending payments - all pending votes
    $stmt = $db->query("SELECT COUNT(*) as total FROM votes WHERE status = 'pending'");
    $pendingPayments = $stmt->fetch()['total'] ?? 0;
    
    // Recent transactions (last 10) - all votes
    $stmt = $db->query("
        SELECT v.*, n.name as nominee_name, c.name as category_name
        FROM votes v
        JOIN nominees n ON v.nominee_id = n.id
        JOIN categories c ON n.category_id = c.id
        ORDER BY v.created_at DESC
        LIMIT 10
    ");
    $recentTransactions = $stmt->fetchAll();
    
    return [
        'total_votes' => (int)$totalVotes,
        'total_revenue' => (float)$totalRevenue,
        'total_transactions' => (int)$totalTransactions,
        'pending_payments' => (int)$pendingPayments,
        'recent_transactions' => $recentTransactions
    ];
}

/**
 * Get category leaderboard
 * Shows all votes (both simulated and real M-Pesa transactions)
 */
function getCategoryLeaderboard($db, $categoryId) {
    if ($categoryId > 0) {
        $stmt = $db->prepare("
            SELECT n.id, n.name, n.gender, c.id as category_id, c.name as category_name,
                   COALESCE((SELECT SUM(votes_count) FROM votes WHERE nominee_id = n.id), 0) as votes_count,
                   (SELECT COUNT(*) FROM votes WHERE nominee_id = n.id) as transaction_count,
                   (SELECT SUM(amount) FROM votes WHERE nominee_id = n.id) as total_amount
            FROM nominees n
            JOIN categories c ON n.category_id = c.id
            WHERE n.category_id = ?
            ORDER BY votes_count DESC, n.name ASC
        ");
        $stmt->execute([$categoryId]);
    } else {
        $stmt = $db->query("
            SELECT n.id, n.name, n.gender, c.id as category_id, c.name as category_name,
                   COALESCE((SELECT SUM(votes_count) FROM votes WHERE nominee_id = n.id), 0) as votes_count,
                   (SELECT COUNT(*) FROM votes WHERE nominee_id = n.id) as transaction_count,
                   (SELECT SUM(amount) FROM votes WHERE nominee_id = n.id) as total_amount
            FROM nominees n
            JOIN categories c ON n.category_id = c.id
            ORDER BY votes_count DESC, n.name ASC
        ");
    }
    
    $nominees = $stmt->fetchAll();
    
    // Calculate percentages based on all votes
    $totalVotes = array_sum(array_column($nominees, 'votes_count'));
    foreach ($nominees as &$nominee) {
        $nominee['percentage'] = $totalVotes > 0 ? round(($nominee['votes_count'] / $totalVotes) * 100, 2) : 0;
    }
    
    return $nominees;
}

/**
 * Get transactions
 * Shows all votes (both simulated and real M-Pesa transactions)
 */
function getTransactions($db) {
    $status = $_GET['status'] ?? '';
    $categoryId = $_GET['category_id'] ?? '';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    
    $where = [];
    $params = [];
    
    if ($status) {
        $where[] = "v.status = ?";
        $params[] = $status;
    }
    
    if ($categoryId) {
        $where[] = "n.category_id = ?";
        $params[] = $categoryId;
    }
    
    if ($dateFrom) {
        $where[] = "DATE(v.created_at) >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $where[] = "DATE(v.created_at) <= ?";
        $params[] = $dateTo;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $stmt = $db->prepare("
        SELECT v.*, n.name as nominee_name, n.gender, c.name as category_name
        FROM votes v
        JOIN nominees n ON v.nominee_id = n.id
        JOIN categories c ON n.category_id = c.id
        WHERE {$whereClause}
        ORDER BY v.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    
    $transactions = $stmt->fetchAll();
    
    // Get total count
    $countStmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM votes v
        JOIN nominees n ON v.nominee_id = n.id
        WHERE {$whereClause}
    ");
    $countStmt->execute(array_slice($params, 0, -2));
    $total = $countStmt->fetch()['total'] ?? 0;
    
    return [
        'transactions' => $transactions,
        'total' => (int)$total
    ];
}

/**
 * Get winners per category
 * Shows all votes (both simulated and real M-Pesa transactions)
 */
function getWinners($db, $categoryId = 0) {
    if ($categoryId > 0) {
        $stmt = $db->prepare("
            SELECT c.id as category_id, c.name as category_name
            FROM categories c
            WHERE c.id = ?
            ORDER BY c.id
        ");
        $stmt->execute([$categoryId]);
    } else {
        $stmt = $db->query("
            SELECT c.id as category_id, c.name as category_name
            FROM categories c
            ORDER BY c.id
        ");
    }
    $categories = $stmt->fetchAll();
    
    $winners = [];
    
    foreach ($categories as $category) {
        $winners[$category['category_id']] = [
            'category_id' => $category['category_id'],
            'category_name' => $category['category_name'],
            'male_winner' => null,
            'female_winner' => null
        ];
        
        // Get male winner - all votes (real and simulated)
        $stmt = $db->prepare("
            SELECT n.id, n.name,
                   COALESCE(SUM(v.votes_count), 0) as votes_count
            FROM nominees n
            LEFT JOIN votes v ON n.id = v.nominee_id
            WHERE n.category_id = ? AND n.gender = 'Male'
            GROUP BY n.id, n.name
            ORDER BY votes_count DESC, n.name ASC
            LIMIT 1
        ");
        $stmt->execute([$category['category_id']]);
        $maleWinner = $stmt->fetch();
        
        if ($maleWinner && $maleWinner['votes_count'] > 0) {
            $winners[$category['category_id']]['male_winner'] = [
                'nominee_id' => $maleWinner['id'],
                'nominee_name' => $maleWinner['name'],
                'votes_count' => (int)$maleWinner['votes_count']
            ];
        }
        
        // Get female winner - all votes (real and simulated)
        $stmt = $db->prepare("
            SELECT n.id, n.name,
                   COALESCE(SUM(v.votes_count), 0) as votes_count
            FROM nominees n
            LEFT JOIN votes v ON n.id = v.nominee_id
            WHERE n.category_id = ? AND n.gender = 'Female'
            GROUP BY n.id, n.name
            ORDER BY votes_count DESC, n.name ASC
            LIMIT 1
        ");
        $stmt->execute([$category['category_id']]);
        $femaleWinner = $stmt->fetch();
        
        if ($femaleWinner && $femaleWinner['votes_count'] > 0) {
            $winners[$category['category_id']]['female_winner'] = [
                'nominee_id' => $femaleWinner['id'],
                'nominee_name' => $femaleWinner['name'],
                'votes_count' => (int)$femaleWinner['votes_count']
            ];
        }
    }
    
    return array_values($winners);
}

/**
 * Get votes by category for charts
 * Shows all votes (both simulated and real M-Pesa transactions)
 */
function getVotesByCategory($db) {
    $stmt = $db->query("
        SELECT c.id, c.name, 
               SUM(v.votes_count) as total_votes,
               SUM(v.amount) as total_revenue
        FROM categories c
        LEFT JOIN nominees n ON c.id = n.category_id
        LEFT JOIN votes v ON n.id = v.nominee_id
        GROUP BY c.id, c.name
        ORDER BY total_votes DESC
    ");
    
    return $stmt->fetchAll();
}
