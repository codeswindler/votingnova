<?php
/**
 * USSD Simulator - guzaBox Style Phone Interface
 * Realistic phone interface matching guzaBox design
 */

require_once __DIR__ . '/../includes/env.php';

// Get USSD base code from environment
$ussdBaseCode = getenv('USSD_BASE_CODE') ?: '*519*24#';

// Handle simulator requests
$action = $_GET['action'] ?? '';

if ($action === 'test') {
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/ussd-handler.php';
    header('Content-Type: application/json');
    
    $sessionId = $_POST['session_id'] ?? 'SIM-' . time();
    $phone = $_POST['phone'] ?? '254712345678';
    $input = $_POST['input'] ?? '';
    
    // Call USSD handler directly (no HTTP request needed)
    try {
        $handler = new USSDHandler($sessionId, $phone, $input);
        $response = $handler->process();
        
        echo json_encode([
            'success' => true,
            'response' => $response,
            'http_code' => 200
        ]);
    } catch (Exception $e) {
        error_log("Simulator Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'response' => 'Error: ' . $e->getMessage(),
            'http_code' => 500
        ]);
    }
    exit;
}

if ($action === 'simulate_payment') {
    require_once __DIR__ . '/../includes/db.php';
    header('Content-Type: application/json');
    
    $sessionId = $_POST['session_id'] ?? '';
    $phone = $_POST['phone'] ?? '254712345678';
    
    try {
        $db = getDB();
        
        // Get the session to find the checkout_request_id and vote details
        $stmt = $db->prepare("
            SELECT checkout_request_id, nominee_id, votes_count, amount
            FROM ussd_sessions
            WHERE session_id = ?
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();
        
        if ($session) {
            $checkoutRequestId = $session['checkout_request_id'] ?? 'SIM-' . $sessionId . '-' . time();
            // Receipt format: VOT#45p095j (VOT# + 7 alphanumeric chars)
            $receiptNumber = 'VOT#' . strtolower(substr(preg_replace('/[^a-z0-9]/', '', md5($sessionId . time())), 0, 7));
            
            // Create or update M-Pesa transaction
            $stmt = $db->prepare("
                SELECT id FROM mpesa_transactions WHERE checkout_request_id = ?
            ");
            $stmt->execute([$checkoutRequestId]);
            $existingTx = $stmt->fetch();
            
            $amount = $session['amount'] ?? 0;
            if ($existingTx) {
                // Update existing transaction
                $stmt = $db->prepare("
                    UPDATE mpesa_transactions 
                    SET status = 'completed', 
                        mpesa_receipt_number = ?,
                        result_code = 0,
                        result_desc = 'The service request is processed successfully.'
                    WHERE checkout_request_id = ?
                ");
                $stmt->execute([$receiptNumber, $checkoutRequestId]);
            } else {
                // Create new transaction for simulation
                $stmt = $db->prepare("
                    INSERT INTO mpesa_transactions 
                    (phone, amount, checkout_request_id, status, mpesa_receipt_number, result_code, result_desc)
                    VALUES (?, ?, ?, 'completed', ?, 0, 'The service request is processed successfully.')
                ");
                $stmt->execute([$phone, $amount, $checkoutRequestId, $receiptNumber]);
            }
            
            // Check if vote already exists (shouldn't, but check to avoid duplicates)
            $stmt = $db->prepare("
                SELECT id FROM votes WHERE transaction_id = ?
            ");
            $stmt->execute([$checkoutRequestId]);
            $existingVote = $stmt->fetch();
            
            if (!$existingVote && $session['nominee_id'] && $session['votes_count']) {
                // Create vote directly with 'completed' status (no pending state)
                $stmt = $db->prepare("
                    INSERT INTO votes (nominee_id, phone, votes_count, amount, status, mpesa_ref, transaction_id)
                    VALUES (?, ?, ?, ?, 'completed', ?, ?)
                ");
                $stmt->execute([
                    $session['nominee_id'],
                    $phone,
                    $session['votes_count'],
                    $amount,
                    $receiptNumber,
                    $checkoutRequestId
                ]);
                
                // Update nominee vote count
                $stmt = $db->prepare("
                    UPDATE nominees 
                    SET votes_count = votes_count + ?
                    WHERE id = ?
                ");
                $stmt->execute([$session['votes_count'], $session['nominee_id']]);
            }
            
            echo json_encode(['success' => true, 'receipt' => $receiptNumber]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Session not found']);
        }
    } catch (Exception $e) {
        error_log("Payment simulation error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_vote_details') {
    require_once __DIR__ . '/../includes/db.php';
    header('Content-Type: application/json');
    
    $sessionId = $_GET['session_id'] ?? '';
    
    try {
        $db = getDB();
        
        // Get vote details including nominee and category
        $stmt = $db->prepare("
            SELECT v.votes_count, v.amount, n.name as nominee_name, c.name as category_name
            FROM votes v
            INNER JOIN nominees n ON v.nominee_id = n.id
            INNER JOIN categories c ON n.category_id = c.id
            INNER JOIN ussd_sessions s ON v.transaction_id = s.checkout_request_id
            WHERE s.session_id = ? AND v.status = 'completed'
            ORDER BY v.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$sessionId]);
        $vote = $stmt->fetch();
        
        // If vote not found, try to get category from session
        if (!$vote) {
            $stmt = $db->prepare("
                SELECT s.category_id, c.name as category_name, n.name as nominee_name
                FROM ussd_sessions s
                LEFT JOIN categories c ON s.category_id = c.id
                LEFT JOIN nominees n ON s.nominee_id = n.id
                WHERE s.session_id = ?
            ");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch();
            
            if ($session && $session['category_name']) {
                $vote = [
                    'votes_count' => null,
                    'amount' => null,
                    'nominee_name' => $session['nominee_name'] ?? null,
                    'category_name' => $session['category_name']
                ];
            }
        }
        
        if ($vote) {
            echo json_encode(['success' => true, 'vote' => $vote]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Vote not found']);
        }
    } catch (Exception $e) {
        error_log("Get vote details error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Get recent sessions
try {
    require_once __DIR__ . '/../includes/db.php';
    $db = getDB();
    $stmt = $db->query("
        SELECT session_id, phone, state, created_at 
        FROM ussd_sessions 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $recentSessions = $stmt->fetchAll();
} catch (Exception $e) {
    $recentSessions = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USSD Simulator - Voting System</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "DM Sans", Arial, sans-serif;
            background: radial-gradient(circle at top, #0f172a 0%, #0b1020 45%, #090c16 100%);
            color: #e2e8f0;
            min-height: 100vh;
            padding: 24px;
        }

        .simulator-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
            padding-bottom: 18px;
        }

        .simulator-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .simulator-icon {
            height: 42px;
            width: 42px;
            border-radius: 14px;
            background: rgba(56, 189, 248, 0.2);
            border: 1px solid rgba(56, 189, 248, 0.5);
            display: grid;
            place-items: center;
            font-weight: 700;
        }

        .simulator-heading {
            font-weight: 700;
            font-size: 18px;
        }

        .simulator-subheading {
            font-size: 12px;
            color: #94a3b8;
        }

        .simulator-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 0.9fr);
            gap: 28px;
            align-items: start;
        }

        .simulator-copy {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .simulator-device {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .page-title {
            font-size: 26px;
            margin: 0 0 12px;
        }

        .subtle {
            color: #94a3b8;
            margin-top: 4px;
        }

        .card {
            background: linear-gradient(135deg, hsla(200, 80%, 55%, 0.12), rgba(15, 23, 42, 0.7));
            padding: 18px 20px;
            border-radius: 16px;
            border: 1px solid hsla(200, 80%, 60%, 0.25);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.35);
            backdrop-filter: blur(8px);
            margin-bottom: 16px;
        }

        .mono {
            font-family: "JetBrains Mono", monospace;
        }

        .phone-device {
            width: 320px;
            height: 650px;
            background: #000000;
            border-radius: 48px;
            padding: 10px;
            border: 8px solid #27272a;
            box-shadow: 0 20px 50px -10px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .phone-status {
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #ffffff;
            font-size: 12px;
            font-weight: 500;
            padding: 6px 16px;
            height: 32px;
            background: #000000;
        }

        .status-icons {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-signal {
            width: 16px;
            height: 10px;
            border-radius: 2px;
            background: linear-gradient(to right, #ffffff 20%, #ffffff 40%, transparent 40%, transparent 60%, #ffffff 60%, #ffffff 80%, transparent 80%);
            opacity: 0.9;
        }

        .status-wifi {
            width: 14px;
            height: 10px;
            border-radius: 50%;
            border: 2px solid #ffffff;
            border-color: #ffffff transparent transparent transparent;
            transform: rotate(45deg);
            opacity: 0.9;
        }

        .status-battery {
            position: relative;
            width: 18px;
            height: 10px;
            border: 2px solid #ffffff;
            border-radius: 3px;
        }

        .status-battery::after {
            content: "";
            position: absolute;
            top: 2px;
            right: -4px;
            width: 2px;
            height: 6px;
            border-radius: 1px;
            background: #ffffff;
        }

        .status-battery-fill {
            display: block;
            width: 70%;
            height: 100%;
            background: #ffffff;
            opacity: 0.9;
        }

        .phone-body {
            background: #000000;
            color: #0f172a;
            border-radius: 28px;
            padding: 0;
            flex: 1;
            position: relative;
            overflow: hidden;
        }

        .phone-body.dialer-only {
            padding: 0;
        }

        .dialer-view {
            display: flex;
            flex-direction: column;
            height: 100%;
            justify-content: space-between;
            background: #000000;
        }

        .dialer-display {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            flex: 1;
            padding: 0 24px 32px;
        }

        .dialer-display-input {
            width: 100%;
            text-align: center;
            font-size: 34px;
            font-weight: 300;
            border: none;
            background: transparent;
            color: #ffffff;
            padding: 0;
            margin: 0 0 6px;
            font-family: "JetBrains Mono", monospace;
        }

        .dialer-keypad {
            padding: 24px 24px 32px;
        }

        .dialer-keypad-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 26px;
        }

        .dialer-key-btn {
            height: 62px;
            width: 62px;
            border-radius: 999px;
            border: none;
            background: #1f1f1f;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            font-size: 20px;
            font-weight: 500;
            color: #f8fafc;
            justify-self: center;
            cursor: pointer;
            transition: background 0.2s;
        }

        .dialer-digit {
            font-size: 22px;
            font-weight: 500;
        }

        .dialer-key-btn:active {
            background: #2a2a2a;
        }

        .dialer-key-btn:hover {
            background: #2a2a2a;
        }

        .dialer-symbol {
            font-size: 22px;
        }

        .dialer-key-btn.dialer-symbol {
            background: #1f1f1f;
        }

        .dialer-symbol-text {
            font-size: 22px;
            padding-top: 2px;
        }

        .dialer-letters {
            font-size: 9px;
            letter-spacing: 1px;
            font-weight: 700;
            color: #9ca3af;
            text-transform: uppercase;
        }

        .dialer-actions-row {
            display: flex;
            justify-content: center;
        }

        .dialer-call-btn {
            height: 64px;
            width: 64px;
            border-radius: 999px;
            background: #16a34a;
            color: #fff;
            border: none;
            font-weight: 600;
            box-shadow: 0 12px 24px rgba(22, 163, 74, 0.35);
            display: grid;
            place-items: center;
            cursor: pointer;
            transition: transform 0.1s ease, box-shadow 0.2s ease;
        }

        .dialer-call-btn:active {
            transform: scale(0.96);
            box-shadow: 0 6px 16px rgba(22, 163, 74, 0.3);
        }

        .dialer-call-icon {
            width: 26px;
            height: 26px;
        }

        .ussd-modal {
            position: absolute;
            inset: 0;
            background: #5f5f5f;
            display: flex;
            align-items: stretch;
            justify-content: stretch;
            padding: 24px 18px 28px;
        }

        .ussd-card {
            width: 100%;
            background: transparent;
            border-radius: 16px;
            padding: 0;
            box-shadow: none;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .ussd-modal-body {
            background: transparent;
            border-radius: 0;
            padding: 0;
            min-height: 200px;
            color: #f8fafc;
            border: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .ussd-modal-body .mono {
            white-space: pre-wrap;
            line-height: 1.5;
            font-size: 13px;
            max-width: 90%;
        }

        .ussd-input-row {
            margin-top: 12px;
        }

        .ussd-input-row {
            margin-top: 12px;
        }
        
        .ussd-input-row input {
            width: 100%;
            background: #ffffff;
            color: #1f2937;
            border: 1px solid rgba(148, 163, 184, 0.35);
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 16px;
            font-family: "JetBrains Mono", monospace;
            box-sizing: border-box;
        }
        
        .ussd-input-row input:focus {
            outline: none;
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.1);
        }

        .ussd-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
            align-items: center;
            margin-top: auto;
        }

        .ussd-actions-split .ussd-cancel,
        .ussd-actions-split .ussd-send {
            flex: 1;
            padding: 14px 12px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            border: none;
            cursor: pointer;
        }

        .ussd-cancel {
            background: #ffffff;
            color: #111827;
        }

        .ussd-send {
            background: #111827;
            color: #ffffff;
        }

        .phone-home {
            height: 20px;
            display: grid;
            place-items: center;
            background: #ffffff;
        }

        .phone-home span {
            width: 120px;
            height: 4px;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.4);
        }

        .admin-credentials {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .admin-credentials h6 {
            margin-bottom: 10px;
            font-weight: 600;
        }

        .credential-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .credential-item:last-child {
            border-bottom: none;
        }

        .credential-value {
            font-family: 'JetBrains Mono', monospace;
            background: rgba(0,0,0,0.2);
            padding: 4px 8px;
            border-radius: 4px;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .toast {
            background: #1f2937;
            color: #ffffff;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(56, 189, 248, 0.3);
            min-width: 300px;
            max-width: 400px;
            animation: slideInRight 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .toast.success {
            border-color: rgba(34, 197, 94, 0.5);
            background: linear-gradient(135deg, #1f2937 0%, #0f172a 100%);
        }

        .toast.info {
            border-color: rgba(56, 189, 248, 0.5);
        }

        .toast-icon {
            font-size: 24px;
            flex-shrink: 0;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .toast-message {
            font-size: 12px;
            color: #94a3b8;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* SMS Popup Modal */
        .sms-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 10001;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s ease-out;
        }

        .sms-modal {
            background: #1f2937;
            border-radius: 16px;
            padding: 24px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(56, 189, 248, 0.3);
            animation: scaleIn 0.2s ease-out;
        }

        .sms-modal-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
        }

        .sms-modal-icon {
            font-size: 28px;
        }

        .sms-modal-title {
            font-weight: 600;
            font-size: 18px;
            color: #ffffff;
        }

        .sms-modal-body {
            background: #0f172a;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            border: 1px solid rgba(148, 163, 184, 0.1);
        }

        .sms-from {
            font-size: 12px;
            color: #94a3b8;
            margin-bottom: 8px;
        }

        .sms-message {
            font-size: 14px;
            color: #e2e8f0;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .sms-modal-footer {
            display: flex;
            justify-content: flex-end;
        }

        .sms-modal-close {
            background: #38bdf8;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .sms-modal-close:hover {
            background: #0ea5e9;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes scaleIn {
            from {
                transform: scale(0.9);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        @media (max-width: 1024px) {
            .simulator-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="simulator-header">
        <div class="simulator-brand">
            <div class="simulator-icon">VS</div>
            <div>
                <div class="simulator-heading">USSD Simulator</div>
                <div class="simulator-subheading">VOTING SYSTEM - PROTOTYPE v1.0</div>
            </div>
        </div>
        <div class="simulator-actions">
            <span class="mono" style="padding: 6px 12px; border-radius: 999px; border: 1px solid rgba(148, 163, 184, 0.25); background: rgba(2, 6, 23, 0.5);"><?php echo htmlspecialchars($ussdBaseCode); ?></span>
        </div>
    </div>

    <div class="simulator-layout">
        <div class="simulator-copy">
            <h2 class="page-title">USSD Simulator</h2>
            <p class="subtle">Dial and test the voting flow with a live handset UI.</p>

            <div class="card">
                <div class="admin-credentials">
                    <h6>🔐 Admin Dashboard Credentials</h6>
                    <div class="credential-item">
                        <span>Username:</span>
                        <span class="credential-value">admin</span>
                    </div>
                    <div class="credential-item">
                        <span>Password:</span>
                        <span class="credential-value">admin123</span>
                    </div>
                    <div style="margin-top: 10px; text-align: center;">
                        <a href="/admin/" style="color: white; text-decoration: none; padding: 8px 16px; background: rgba(255,255,255,0.2); border-radius: 6px; display: inline-block;">Go to Admin Dashboard</a>
                    </div>
                </div>
            </div>

            <div class="card">
                <h4>How to test</h4>
                <ol style="margin: 10px 0 0; padding-left: 18px; color: #cbd5f5;">
                    <li>Dial <span style="padding: 2px 8px; border-radius: 999px; background: rgba(56, 189, 248, 0.2); border: 1px solid rgba(56, 189, 248, 0.4); font-family: 'JetBrains Mono', monospace;"><?php echo htmlspecialchars($ussdBaseCode); ?></span> on the simulator</li>
                    <li>Navigate using keypad (Select category, gender, nominee)</li>
                    <li>Enter number of votes</li>
                    <li>Complete the voting flow</li>
                </ol>
            </div>

            <div class="card">
                <h4>Session log</h4>
                <div class="mono" id="sessionLog" style="white-space: pre-wrap; max-height: 220px; overflow: auto; background: rgba(2, 6, 23, 0.5); padding: 10px 12px; border-radius: 12px; border: 1px solid rgba(148, 163, 184, 0.2);">
                    No messages yet.
                </div>
            </div>
        </div>

        <div class="simulator-device">
            <div class="phone-device">
                <div class="phone-status">
                    <span id="phoneTime">9:41</span>
                    <div class="status-icons">
                        <span class="status-signal"></span>
                        <span class="status-wifi"></span>
                        <span class="status-battery">
                            <span class="status-battery-fill"></span>
                        </span>
                    </div>
                </div>
                <div class="phone-body dialer-only" id="phoneBody">
                    <div class="dialer-view" id="dialerView">
                        <div class="dialer-display" id="dialerDisplay">
                            <input type="text" class="dialer-display-input" id="dialerInput" value="<?php echo htmlspecialchars($ussdBaseCode); ?>" readonly>
                        </div>
                        <div class="dialer-keypad">
                            <div class="dialer-keypad-grid">
                                <button class="dialer-key-btn" onclick="handleDigitPress('1')">
                                    <span class="dialer-digit">1</span>
                                </button>
                                <button class="dialer-key-btn" onclick="handleDigitPress('2')">
                                    <span class="dialer-digit">2</span>
                                    <span class="dialer-letters">ABC</span>
                                </button>
                                <button class="dialer-key-btn" onclick="handleDigitPress('3')">
                                    <span class="dialer-digit">3</span>
                                    <span class="dialer-letters">DEF</span>
                                </button>
                                <button class="dialer-key-btn" onclick="handleDigitPress('4')">
                                    <span class="dialer-digit">4</span>
                                    <span class="dialer-letters">GHI</span>
                                </button>
                                <button class="dialer-key-btn" onclick="handleDigitPress('5')">
                                    <span class="dialer-digit">5</span>
                                    <span class="dialer-letters">JKL</span>
                                </button>
                                <button class="dialer-key-btn" onclick="handleDigitPress('6')">
                                    <span class="dialer-digit">6</span>
                                    <span class="dialer-letters">MNO</span>
                                </button>
                                <button class="dialer-key-btn" onclick="handleDigitPress('7')">
                                    <span class="dialer-digit">7</span>
                                    <span class="dialer-letters">PQRS</span>
                                </button>
                                <button class="dialer-key-btn" onclick="handleDigitPress('8')">
                                    <span class="dialer-digit">8</span>
                                    <span class="dialer-letters">TUV</span>
                                </button>
                                <button class="dialer-key-btn" onclick="handleDigitPress('9')">
                                    <span class="dialer-digit">9</span>
                                    <span class="dialer-letters">WXYZ</span>
                                </button>
                                <button class="dialer-key-btn dialer-symbol" onclick="handleDigitPress('*')">
                                    <span class="dialer-symbol-text">*</span>
                                </button>
                                <button class="dialer-key-btn" onclick="handleDigitPress('0')">
                                    <span style="font-size: 14px; font-weight: 700;">VS</span>
                                    <span class="dialer-letters">+</span>
                                </button>
                                <button class="dialer-key-btn dialer-symbol" onclick="handleDigitPress('#')">
                                    <span class="dialer-symbol-text">#</span>
                                </button>
                            </div>
                            <div class="dialer-actions-row">
                                <button class="dialer-call-btn" onclick="handleDial()" aria-label="Call">
                                    <svg class="dialer-call-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.7.6 2.5a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.6-1.5a2 2 0 0 1 2.1-.5c.8.3 1.6.5 2.5.6a2 2 0 0 1 1.7 2z"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="phone-home">
                    <span></span>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        let currentSessionId = 'SIM-' + Date.now();
        let currentInput = '';
        let dialText = '<?php echo addslashes($ussdBaseCode); ?>';
        let mode = 'dial';
        let screenText = '';
        let history = [];
        let showReplyInput = false;
        let isComplete = false;
        let paymentPending = false;
        let paymentAmount = 0;
        let paymentVotes = 0;
        let paymentNominee = '';
        let paymentCategory = '';

        function updateTime() {
            const now = new Date();
            const time = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: false });
            document.getElementById('phoneTime').textContent = time;
        }
        setInterval(updateTime, 1000);
        updateTime();

        function handleDigitPress(value) {
            if (mode === 'dial') {
                dialText += value;
                updateDialDisplay();
            }
        }

        function updateDialDisplay() {
            const input = document.getElementById('dialerInput');
            if (input) {
                input.value = dialText;
            }
        }

        function handleDial() {
            if (mode === 'dial') {
                const expectedCode = '<?php echo addslashes($ussdBaseCode); ?>';
                if (dialText.trim() === expectedCode) {
                    startSession(dialText);
                } else {
                    // Reset if wrong
                    dialText = '<?php echo addslashes($ussdBaseCode); ?>';
                    updateDialDisplay();
                }
            }
        }

        function startSession(dial) {
            const trimmed = dial.trim();
            if (!trimmed.includes('*') || !trimmed.includes('#')) {
                addToLog('> ' + trimmed + '\nCON Invalid code. Try again.');
                return;
            }

            currentSessionId = 'SIM-' + Date.now();
            currentInput = '';
            mode = 'session';
            showReplyInput = true; // Will be set based on response, but prepare for CON
            isComplete = false;

            addToLog('> ' + trimmed);
            sendRequest('<?php echo addslashes($ussdBaseCode); ?>');
        }

        function sendRequest(input) {
            addToLog('Sending request...');
            fetch('?action=test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    session_id: currentSessionId,
                    phone: '254712345678',
                    input: input
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    const response = data.response || '';
                    const isEnd = response.startsWith('END');
                    const isCon = response.startsWith('CON');
                    
                    let message = response.replace(/^(CON|END)\s*/, '').trim();
                    screenText = message;
                    
                    addToLog(response);
                    
                    // Check if payment processing started
                    if (message.includes('Processing payment') || message.includes('STK Push')) {
                        paymentPending = true;
                        // Extract payment details from previous context if available
                        showSTKPushToast();
                        // For simulated payments, auto-complete immediately (no delay needed)
                        setTimeout(() => {
                            simulatePaymentComplete();
                        }, 2000);
                    }
                    
                    // Set showReplyInput based on response type
                    if (isEnd) {
                        isComplete = true;
                        showReplyInput = false;
                        
                        // Check if payment was successful - show SMS popup
                        // The message format is: "Thank you! Your X vote(s) for Y have been recorded. Ref: Z"
                        console.log('END response received:', message);
                        
                        // If payment session not found, try to auto-complete and show SMS
                        if (message.includes('Payment session not found') || message.includes('complete the payment')) {
                            console.log('Payment session issue detected, attempting auto-completion...');
                            if (paymentPending) {
                                paymentPending = false;
                                simulatePaymentComplete();
                                // After auto-completing, show SMS popup with available data
                                setTimeout(() => {
                                    // Receipt format: VOT#45p095j
                                    const receipt = 'VOT#' + Math.random().toString(36).substring(2, 9).toLowerCase();
                                    console.log('Showing SMS popup after auto-completion');
                                    fetchVoteDetailsAndShowSMS(receipt);
                                }, 2000);
                            } else {
                                // Still show SMS even if paymentPending is false (might have completed)
                                setTimeout(() => {
                                    // Receipt format: VOT#45p095j
                                    const receipt = 'VOT#' + Math.random().toString(36).substring(2, 9).toLowerCase();
                                    fetchVoteDetailsAndShowSMS(receipt);
                                }, 1000);
                            }
                        } else if (message.includes('Thank you') || message.includes('recorded') || message.includes('vote')) {
                            // Extract payment details for SMS
                            const votesMatch = message.match(/(\d+)\s+vote/);
                            const refMatch = message.match(/Ref:\s*([A-Z0-9#]+)/i); // Updated to match VOT# format
                            const nomineeMatch = message.match(/for\s+([^.]+)/);
                            
                            if (votesMatch) {
                                paymentVotes = parseInt(votesMatch[1]);
                            }
                            if (nomineeMatch) {
                                paymentNominee = nomineeMatch[1].trim();
                            }
                            
                            // Always show SMS popup after successful payment
                            // Receipt format: VOT#45p095j
                            const receipt = refMatch ? refMatch[1] : ('VOT#' + Math.random().toString(36).substring(2, 9).toLowerCase());
                            console.log('Payment successful! Showing SMS popup for receipt:', receipt, 'Votes:', paymentVotes, 'Nominee:', paymentNominee);
                            setTimeout(() => {
                                fetchVoteDetailsAndShowSMS(receipt);
                            }, 1500);
                        } else {
                            console.log('Payment message does not match expected format:', message);
                        }
                    } else {
                        // CON responses should always show input
                        isComplete = false;
                        showReplyInput = true;
                    }
                    
                    updateUSSDView(message, isEnd);
                } else {
                    const errorMsg = data.response || 'Request failed';
                    console.error('Request failed:', data);
                    addToLog('ERROR: ' + errorMsg);
                    showReplyInput = true; // Allow retry on error
                    isComplete = false;
                    updateUSSDView('Error: ' + errorMsg + '\n\nPlease try again.', false);
                }
            })
            .catch(error => {
                console.error('Request error:', error);
                addToLog('ERROR: ' + error.message);
                // Show error in USSD view
                updateUSSDView('Error: ' + error.message + '\n\nPlease try again.', false);
            });
        }

        function updateUSSDView(message, isEnd) {
            const phoneBody = document.getElementById('phoneBody');
            phoneBody.className = 'phone-body';
            
            // Extract payment details from confirmation screen
            if (message.includes('You are voting') && message.includes('Total:')) {
                extractPaymentDetails(message);
            }
            
            // Always show input for CON responses, never for END
            const shouldShowInput = !isEnd && showReplyInput;
            
            phoneBody.innerHTML = `
                <div class="ussd-modal">
                    <div class="ussd-card">
                        <div class="ussd-modal-body">
                            <p class="mono">${escapeHtml(message).replace(/\n/g, '<br>')}</p>
                        </div>
                        ${shouldShowInput ? `
                            <div class="ussd-input-row">
                                <input type="text" id="replyInput" placeholder="Enter selection..." autofocus>
                            </div>
                        ` : ''}
                        <div class="ussd-actions ussd-actions-split">
                            <button class="ussd-cancel" onclick="handleEnd()">Dismiss</button>
                            ${shouldShowInput ? `
                                <button class="ussd-send" onclick="handleSend()">Reply</button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
            
            if (shouldShowInput) {
                setTimeout(() => {
                    const input = document.getElementById('replyInput');
                    if (input) {
                        input.focus();
                        input.addEventListener('keypress', function(e) {
                            if (e.key === 'Enter') {
                                handleSend();
                            }
                        });
                    }
                }, 100);
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function handleSend() {
            const input = document.getElementById('replyInput');
            if (!input) {
                // If input doesn't exist, try to show it
                showReplyInput = true;
                updateUSSDView(screenText, isComplete);
                return;
            }
            
            if (!input.value.trim()) {
                // Show a hint if input is empty
                input.placeholder = 'Please enter a selection...';
                input.focus();
                return;
            }
            
            if (isComplete) {
                addToLog('Session ended. Tap Dismiss to start again.');
                return;
            }

            const choice = input.value.trim();
            addToLog('> ' + choice);
            
            // Build cumulative input (e.g., 1, then 1*1, then 1*1*1, etc.)
            if (currentInput) {
                currentInput += '*' + choice;
            } else {
                currentInput = choice;
            }
            
            // Clear input and send request
            input.value = '';
            input.placeholder = 'Enter selection...';
            sendRequest(currentInput);
        }

        function handleEnd() {
            mode = 'dial';
            dialText = '<?php echo addslashes($ussdBaseCode); ?>';
            currentInput = '';
            screenText = '';
            showReplyInput = false;
            isComplete = false;
            history = [];
            
            const phoneBody = document.getElementById('phoneBody');
            phoneBody.className = 'phone-body dialer-only';
            phoneBody.innerHTML = `
                <div class="dialer-view" id="dialerView">
                    <div class="dialer-display" id="dialerDisplay">
                        <input type="text" class="dialer-display-input" id="dialerInput" value="<?php echo htmlspecialchars($ussdBaseCode); ?>" readonly>
                    </div>
                    <div class="dialer-keypad">
                        <div class="dialer-keypad-grid">
                            <button class="dialer-key-btn" onclick="handleDigitPress('1')">
                                <span class="dialer-digit">1</span>
                            </button>
                            <button class="dialer-key-btn" onclick="handleDigitPress('2')">
                                <span class="dialer-digit">2</span>
                                <span class="dialer-letters">ABC</span>
                            </button>
                            <button class="dialer-key-btn" onclick="handleDigitPress('3')">
                                <span class="dialer-digit">3</span>
                                <span class="dialer-letters">DEF</span>
                            </button>
                            <button class="dialer-key-btn" onclick="handleDigitPress('4')">
                                <span class="dialer-digit">4</span>
                                <span class="dialer-letters">GHI</span>
                            </button>
                            <button class="dialer-key-btn" onclick="handleDigitPress('5')">
                                <span class="dialer-digit">5</span>
                                <span class="dialer-letters">JKL</span>
                            </button>
                            <button class="dialer-key-btn" onclick="handleDigitPress('6')">
                                <span class="dialer-digit">6</span>
                                <span class="dialer-letters">MNO</span>
                            </button>
                            <button class="dialer-key-btn" onclick="handleDigitPress('7')">
                                <span class="dialer-digit">7</span>
                                <span class="dialer-letters">PQRS</span>
                            </button>
                            <button class="dialer-key-btn" onclick="handleDigitPress('8')">
                                <span class="dialer-digit">8</span>
                                <span class="dialer-letters">TUV</span>
                            </button>
                            <button class="dialer-key-btn" onclick="handleDigitPress('9')">
                                <span class="dialer-digit">9</span>
                                <span class="dialer-letters">WXYZ</span>
                            </button>
                            <button class="dialer-key-btn dialer-symbol" onclick="handleDigitPress('*')">
                                <span class="dialer-symbol-text">*</span>
                            </button>
                            <button class="dialer-key-btn" onclick="handleDigitPress('0')">
                                <span style="font-size: 14px; font-weight: 700;">VS</span>
                                <span class="dialer-letters">+</span>
                            </button>
                            <button class="dialer-key-btn dialer-symbol" onclick="handleDigitPress('#')">
                                <span class="dialer-symbol-text">#</span>
                            </button>
                        </div>
                        <div class="dialer-actions-row">
                            <button class="dialer-call-btn" onclick="handleDial()" aria-label="Call">
                                <svg class="dialer-call-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.7.6 2.5a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.6-1.5a2 2 0 0 1 2.1-.5c.8.3 1.6.5 2.5.6a2 2 0 0 1 1.7 2z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            updateDialDisplay();
            addToLog('Session reset. Ready to dial.');
        }

        function addToLog(text) {
            history.push(text);
            const log = document.getElementById('sessionLog');
            log.textContent = history.length === 0 ? 'No messages yet.' : history.join('\n');
            log.scrollTop = log.scrollHeight;
        }

        // Toast notification functions
        function showToast(type, title, message, duration = 4000) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const icon = type === 'success' ? '✅' : '📱';
            
            toast.innerHTML = `
                <div class="toast-icon">${icon}</div>
                <div class="toast-content">
                    <div class="toast-title">${escapeHtml(title)}</div>
                    <div class="toast-message">${escapeHtml(message)}</div>
                </div>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideInRight 0.3s ease-out reverse';
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, duration);
        }

        function showSTKPushToast() {
            showToast('info', 'M-Pesa STK Push', 'You have received a payment request. Please check your phone.', 5000);
        }

        function showPaymentConfirmationToast() {
            showToast('success', 'Payment Successful', 'Your payment has been processed successfully!', 4000);
        }

        function simulatePaymentComplete() {
            if (paymentPending) {
                paymentPending = false;
                showPaymentConfirmationToast();
                // Simulate M-Pesa callback by calling a special endpoint
                fetch('?action=simulate_payment', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        session_id: currentSessionId,
                        phone: '254712345678'
                    })
                })
                .then(() => {
                    // Automatically check payment status after callback
                    setTimeout(() => {
                        sendRequest(currentInput);
                    }, 1000);
                })
                .catch(err => {
                    console.error('Payment simulation error:', err);
                    // Still continue the flow
                    setTimeout(() => {
                        sendRequest(currentInput);
                    }, 1000);
                });
            }
        }

        function fetchVoteDetailsAndShowSMS(receiptNumber) {
            console.log('fetchVoteDetailsAndShowSMS called with receipt:', receiptNumber);
            // Always show SMS popup - fetch details if available, otherwise use fallback
            fetch('?action=get_vote_details&session_id=' + encodeURIComponent(currentSessionId))
                .then(response => {
                    console.log('Vote details response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Vote details data:', data);
                    if (data.success && data.vote && data.vote.nominee_name) {
                        // Use fetched details
                        console.log('Using fetched vote details');
                        showSMSPopup(
                            data.vote.votes_count || paymentVotes || 1,
                            data.vote.amount || paymentAmount || 10,
                            data.vote.nominee_name,
                            data.vote.category_name || paymentCategory || 'selected category',
                            receiptNumber
                        );
                    } else {
                        // Fallback - show SMS with available data, but try to get category from API
                        console.log('Using fallback data - Votes:', paymentVotes, 'Amount:', paymentAmount, 'Nominee:', paymentNominee);
                        // Try to get category from session if available
                        fetchCategoryFromSession().then(categoryName => {
                            showSMSPopup(
                                paymentVotes || 1,
                                paymentAmount || 10,
                                paymentNominee || 'your nominee',
                                categoryName || paymentCategory || 'selected category',
                                receiptNumber
                            );
                        }).catch(() => {
                            showSMSPopup(
                                paymentVotes || 1,
                                paymentAmount || 10,
                                paymentNominee || 'your nominee',
                                paymentCategory || 'selected category',
                                receiptNumber
                            );
                        });
                    }
                })
                .catch(err => {
                    console.error('Error fetching vote details:', err);
                    // Always show SMS popup even on error, but try to get category
                    console.log('Showing SMS popup with fallback data due to error');
                    fetchCategoryFromSession().then(categoryName => {
                        showSMSPopup(
                            paymentVotes || 1,
                            paymentAmount || 10,
                            paymentNominee || 'your nominee',
                            categoryName || paymentCategory || 'selected category',
                            receiptNumber
                        );
                    }).catch(() => {
                        showSMSPopup(
                            paymentVotes || 1,
                            paymentAmount || 10,
                            paymentNominee || 'your nominee',
                            paymentCategory || 'selected category',
                            receiptNumber
                        );
                    });
                });
        }

        function showSMSPopup(votes, amount, nomineeName, categoryName, receiptNumber) {
            console.log('showSMSPopup called with:', {votes, amount, nomineeName, categoryName, receiptNumber});
            
            // Extract amount from context or use default
            if (!amount || amount === 0) {
                amount = votes * 10; // KES 10 per vote
            }
            
            const formattedAmount = new Intl.NumberFormat('en-KE', {
                style: 'currency',
                currency: 'KES',
                minimumFractionDigits: 0
            }).format(amount);
            
            // Create detailed SMS message matching backend format
            // Format: "Thank you for voting! You voted X time(s) for [Nominee] in the [Category] category. Amount: KES X. Receipt: [Receipt]."
            const smsMessage = `Thank you for voting! You voted ${votes} time(s) for ${nomineeName} in the ${categoryName} category. Amount: ${formattedAmount}. Receipt: ${receiptNumber}.`;
            
            console.log('Creating SMS popup with message:', smsMessage);
            console.log('SMS Message Format:', {
                votes: votes,
                nominee: nomineeName,
                category: categoryName,
                amount: formattedAmount,
                receipt: receiptNumber
            });
            
            const overlay = document.createElement('div');
            overlay.className = 'sms-modal-overlay';
            overlay.onclick = function(e) {
                if (e.target === overlay) {
                    overlay.remove();
                }
            };
            
            overlay.innerHTML = `
                <div class="sms-modal">
                    <div class="sms-modal-header">
                        <div class="sms-modal-icon">💬</div>
                        <div class="sms-modal-title">SMS Notification</div>
                    </div>
                    <div class="sms-modal-body">
                        <div class="sms-from">From: Voting System</div>
                        <div class="sms-message">${escapeHtml(smsMessage)}</div>
                    </div>
                    <div class="sms-modal-footer">
                        <button class="sms-modal-close" onclick="this.closest('.sms-modal-overlay').remove()">Close</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(overlay);
            console.log('SMS popup added to DOM');
        }

        // Extract payment details from confirmation screen
        function extractPaymentDetails(message) {
            const votesMatch = message.match(/(\d+)\s+time\(s\)/);
            const amountMatch = message.match(/KES\s+([\d,]+\.?\d*)/);
            const nomineeMatch = message.match(/for\s+([^\n]+)/);
            
            if (votesMatch) {
                paymentVotes = parseInt(votesMatch[1]);
            }
            if (amountMatch) {
                paymentAmount = parseFloat(amountMatch[1].replace(/,/g, ''));
            }
            if (nomineeMatch) {
                paymentNominee = nomineeMatch[1].trim();
            }
        }

        // Fetch category name from session
        function fetchCategoryFromSession() {
            return fetch('?action=get_vote_details&session_id=' + encodeURIComponent(currentSessionId))
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.vote && data.vote.category_name) {
                        paymentCategory = data.vote.category_name;
                        return data.vote.category_name;
                    }
                    return null;
                })
                .catch(() => null);
        }

    </script>
</body>
</html>
