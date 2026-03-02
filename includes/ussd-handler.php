<?php
/**
 * USSD Handler - State Machine for USSD Voting Flow
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mpesa-service.php';
require_once __DIR__ . '/env.php';

class USSDHandler {
    private $db;
    private $sessionId;
    private $phone;
    private $input;
    private $session;
    private $votePrice = 10; // KES 10 per vote
    private $ussdBaseCode;

    public function __construct($sessionId, $phone, $input) {
        $this->db = getDB();
        $this->sessionId = $sessionId;
        $this->phone = $this->normalizePhone($phone);
        $this->input = $input;
        $this->ussdBaseCode = getenv('USSD_BASE_CODE') ?: '*519*24#';
        $this->loadSession();
    }

    /**
     * Normalize phone number to 254XXXXXXXXX format
     */
    private function normalizePhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (substr($phone, 0, 3) !== '254') {
            $phone = '254' . $phone;
        }
        return $phone;
    }

    /**
     * Load or create session
     */
    private function loadSession() {
        $stmt = $this->db->prepare("
            SELECT * FROM ussd_sessions 
            WHERE session_id = ? AND expires_at > NOW()
        ");
        $stmt->execute([$this->sessionId]);
        $this->session = $stmt->fetch();

        if (!$this->session) {
            // Create new session
            $stmt = $this->db->prepare("
                INSERT INTO ussd_sessions (session_id, phone, state, expires_at)
                VALUES (?, ?, 0, DATE_ADD(NOW(), INTERVAL 5 MINUTE))
            ");
            $stmt->execute([$this->sessionId, $this->phone]);
            $this->session = [
                'session_id' => $this->sessionId,
                'phone' => $this->phone,
                'state' => 0,
                'category_id' => null,
                'gender' => null,
                'nominee_id' => null,
                'votes_count' => null,
                'amount' => null,
                'page' => 0
            ];
        }
    }

    /**
     * Update session state
     */
    private function updateSession($data) {
        $this->session = array_merge($this->session, $data);
        
        // Store page in session data if provided
        if (isset($data['page'])) {
            $this->session['page'] = $data['page'];
        }
        
        $stmt = $this->db->prepare("
            UPDATE ussd_sessions 
            SET state = ?, category_id = ?, gender = ?, nominee_id = ?, 
                votes_count = ?, amount = ?, data = ?, expires_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE)
            WHERE session_id = ?
        ");
        $stmt->execute([
            $data['state'] ?? $this->session['state'],
            $data['category_id'] ?? $this->session['category_id'] ?? null,
            $data['gender'] ?? $this->session['gender'] ?? null,
            $data['nominee_id'] ?? $this->session['nominee_id'] ?? null,
            $data['votes_count'] ?? $this->session['votes_count'] ?? null,
            $data['amount'] ?? $this->session['amount'] ?? null,
            json_encode($this->session),
            $this->sessionId
        ]);
    }

    /**
     * Process USSD request and return response
     */
    public function process() {
        $state = (int)$this->session['state'];
        $input = trim($this->input);

        // Extract base code suffix (e.g., "24" from "*519*24#")
        // This is used to identify the service, not as user input
        $baseCodeSuffix = '';
        $baseCodePattern = trim($this->ussdBaseCode, '*#');
        $baseCodeParts = explode('*', $baseCodePattern);
        if (count($baseCodeParts) > 1) {
            $baseCodeSuffix = end($baseCodeParts); // e.g., "24"
        }

        // Parse input - remove USSD code prefix if present
        $pattern = '/^\*' . preg_quote($baseCodePattern, '/') . '\*/';
        $input = preg_replace($pattern, '', $input);
        $input = preg_replace('/#/', '', $input);
        $parts = explode('*', $input);
        $lastInput = end($parts);

        // If state is 0 (initial) and input matches base code suffix, ignore it (it's part of the base code)
        if ($state === 0 && $lastInput === $baseCodeSuffix && !empty($baseCodeSuffix)) {
            $lastInput = ''; // Treat as empty input for initial menu
        }

        // Handle navigation options (0, 00, 98)
        if ($lastInput === '00') {
            return $this->goToMainMenu();
        }
        
        if ($lastInput === '0' && $state > 0) {
            return $this->goBack();
        }

        try {
            switch ($state) {
                case 0:
                    return $this->showCategories();
                
                case 1:
                    return $this->handleCategorySelection($lastInput);
                
                case 2:
                    return $this->handleGenderSelection($lastInput);
                
                case 3:
                    return $this->handleNomineeSelection($lastInput);
                
                case 4:
                    return $this->handleVotesInput($lastInput);
                
                case 5:
                    return $this->handlePaymentConfirmation($lastInput);
                
                case 6:
                    return $this->checkPaymentStatus();
                
                default:
                    return $this->showError("Invalid session. Please try again.");
            }
        } catch (Exception $e) {
            error_log("USSD Error: " . $e->getMessage());
            return $this->showError("An error occurred. Please try again later.");
        }
    }
    
    /**
     * Go to main menu (00)
     */
    private function goToMainMenu() {
        $this->updateSession(['state' => 0, 'category_id' => null, 'gender' => null, 'nominee_id' => null, 'votes_count' => null, 'amount' => null, 'page' => 0]);
        return $this->showCategories();
    }
    
    /**
     * Go back one step (0)
     */
    private function goBack() {
        $currentState = (int)$this->session['state'];
        
        switch ($currentState) {
            case 1: // After category selection, go back to categories
                $this->updateSession(['state' => 0, 'category_id' => null]);
                return $this->showCategories();
            
            case 2: // Viewing gender selection, go back to categories
                $this->updateSession(['state' => 0, 'category_id' => null]);
                return $this->showCategories();
            
            case 3: // After nominee selection, go back to gender
                $this->updateSession(['state' => 2, 'nominee_id' => null, 'page' => 0]);
                return "CON Select gender:\n1. Male\n2. Female\n\n0. Back\n00. Main Menu";
            
            case 4: // After votes input, go back to nominee selection
                $this->updateSession(['state' => 3, 'votes_count' => null, 'amount' => null]);
                return $this->showNominees();
            
            case 5: // After confirmation, go back to votes input
                $this->updateSession(['state' => 4]);
                $nomineeId = $this->session['nominee_id'];
                $stmt = $this->db->prepare("SELECT name FROM nominees WHERE id = ?");
                $stmt->execute([$nomineeId]);
                $nominee = $stmt->fetch();
                return "CON Enter number of votes for {$nominee['name']} (KES {$this->votePrice} per vote):\n\n0. Back\n00. Main Menu";
            
            default:
                return $this->goToMainMenu();
        }
    }

    /**
     * State 0: Show categories
     */
    private function showCategories() {
        try {
            $stmt = $this->db->query("SELECT id, name FROM categories ORDER BY id");
            $categories = $stmt->fetchAll();

            if (empty($categories)) {
                error_log("USSD Warning: No categories found in database");
                return "END No categories available. Please contact administrator.";
            }

            $message = "Support your champion! Vote now in the Murang'a 40 Under 40 Awards:\n";
            foreach ($categories as $cat) {
                $message .= $cat['id'] . ". " . $cat['name'] . "\n";
            }

            $this->updateSession(['state' => 1]);
            return "CON " . $message;
        } catch (Exception $e) {
            error_log("USSD showCategories Error: " . $e->getMessage());
            return "END An error occurred while loading categories. Please try again later.";
        }
    }

    /**
     * State 1: Handle category selection
     */
    private function handleCategorySelection($input) {
        // Extract base code suffix to ignore it if it appears as input
        $baseCodePattern = trim($this->ussdBaseCode, '*#');
        $baseCodeParts = explode('*', $baseCodePattern);
        $baseCodeSuffix = '';
        if (count($baseCodeParts) > 1) {
            $baseCodeSuffix = end($baseCodeParts); // e.g., "24"
        }
        
        // If input matches base code suffix, ignore it and show menu again
        if ($input === $baseCodeSuffix && !empty($baseCodeSuffix)) {
            return $this->showCategories();
        }
        
        $categoryId = (int)$input;
        
        // Check if input is 98 (more) - not applicable for categories
        if ($input === '98') {
            return $this->showError("Invalid option. Please select a category.");
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        $category = $stmt->fetch();

        if (!$category) {
            return $this->showError("Invalid category. Please try again.");
        }

        $this->updateSession(['state' => 2, 'category_id' => $categoryId, 'page' => 0]);
        return "CON Select gender:\n1. Male\n2. Female\n\n0. Back";
    }

    /**
     * State 2: Handle gender selection
     */
    private function handleGenderSelection($input) {
        $genderMap = ['1' => 'Male', '2' => 'Female'];
        $gender = $genderMap[$input] ?? null;

        if (!$gender) {
            return $this->showError("Invalid selection. Please select 1 for Male or 2 for Female.");
        }

        $this->updateSession(['state' => 3, 'gender' => $gender, 'page' => 0]);
        return $this->showNominees();
    }

    /**
     * Show nominees for selected category and gender (with pagination)
     */
    private function showNominees($page = null) {
        $categoryId = $this->session['category_id'];
        $gender = $this->session['gender'];
        $currentPage = $page !== null ? $page : ((int)($this->session['page'] ?? 0));
        $itemsPerPage = 8; // Show 8 nominees per page
        
        $stmt = $this->db->prepare("
            SELECT id, name FROM nominees 
            WHERE category_id = ? AND gender = ? 
            ORDER BY name
        ");
        $stmt->execute([$categoryId, $gender]);
        $allNominees = $stmt->fetchAll();
        
        $totalNominees = count($allNominees);
        $totalPages = ceil($totalNominees / $itemsPerPage);

        if (empty($allNominees)) {
            return $this->showError("No nominees found for this category.");
        }

        // Get nominees for current page
        $offset = $currentPage * $itemsPerPage;
        $nominees = array_slice($allNominees, $offset, $itemsPerPage);

        $message = "Select nominee:\n";
        $displayIndex = 1;
        foreach ($nominees as $nominee) {
            $message .= $displayIndex . ". " . $nominee['name'] . "\n";
            $displayIndex++;
        }
        
        // Add navigation options
        $message .= "\n";
        if ($currentPage > 0) {
            $message .= "0. Back\n";
        }
        $message .= "00. Main Menu\n";
        if ($currentPage < $totalPages - 1) {
            $message .= "98. More";
        }

        // Update session with current page
        $this->updateSession(['page' => $currentPage]);
        
        return "CON " . $message;
    }

    /**
     * State 3: Handle nominee selection
     */
    private function handleNomineeSelection($input) {
        // Handle "98" for more nominees
        if ($input === '98') {
            $currentPage = (int)($this->session['page'] ?? 0);
            $this->updateSession(['page' => $currentPage + 1]);
            return $this->showNominees($currentPage + 1);
        }
        
        $categoryId = $this->session['category_id'];
        $gender = $this->session['gender'];
        $currentPage = (int)($this->session['page'] ?? 0);
        $itemsPerPage = 8;

        $stmt = $this->db->prepare("
            SELECT id, name FROM nominees 
            WHERE category_id = ? AND gender = ? 
            ORDER BY name
        ");
        $stmt->execute([$categoryId, $gender]);
        $allNominees = $stmt->fetchAll();
        
        // Get nominees for current page
        $offset = $currentPage * $itemsPerPage;
        $nominees = array_slice($allNominees, $offset, $itemsPerPage);

        $selectedIndex = (int)$input - 1;
        if (!isset($nominees[$selectedIndex])) {
            return $this->showError("Invalid selection. Please try again.");
        }

        $nominee = $nominees[$selectedIndex];
        $this->updateSession(['state' => 4, 'nominee_id' => $nominee['id'], 'page' => 0]);
        
        return "CON Enter number of votes for {$nominee['name']} (KES {$this->votePrice} per vote):\n\n0. Back\n00. Main Menu";
    }

    /**
     * State 4: Handle votes input
     */
    private function handleVotesInput($input) {
        $votesCount = (int)$input;

        if ($votesCount < 1 || $votesCount > 100) {
            return $this->showError("Please enter a number between 1 and 100.");
        }

        $nomineeId = $this->session['nominee_id'];
        $stmt = $this->db->prepare("SELECT name FROM nominees WHERE id = ?");
        $stmt->execute([$nomineeId]);
        $nominee = $stmt->fetch();

        $amount = $votesCount * $this->votePrice;
        $this->updateSession([
            'state' => 5,
            'votes_count' => $votesCount,
            'amount' => $amount
        ]);

        $message = "You are voting {$votesCount} time(s) for {$nominee['name']}\n";
        $message .= "Total: KES " . number_format($amount, 2) . "\n";
        $message .= "Confirm?\n1. Yes\n2. No\n\n0. Back\n00. Main Menu";

        return "CON " . $message;
    }

    /**
     * State 5: Handle payment confirmation
     */
    private function handlePaymentConfirmation($input) {
        if ($input === '2' || $input === '0') {
            // Go back to votes input
            $this->updateSession(['state' => 4]);
            $nomineeId = $this->session['nominee_id'];
            $stmt = $this->db->prepare("SELECT name FROM nominees WHERE id = ?");
            $stmt->execute([$nomineeId]);
            $nominee = $stmt->fetch();
            return "CON Enter number of votes for {$nominee['name']} (KES {$this->votePrice} per vote):\n\n0. Back\n00. Main Menu";
        }
        
        if ($input !== '1') {
            return $this->showError("Invalid selection. Please select 1 to confirm or 2 to cancel.");
        }

        // Initiate STK Push
        $mpesaService = new MpesaService();
        $checkoutRequestId = $mpesaService->initiateSTKPush(
            $this->phone,
            $this->session['amount'],
            $this->sessionId
        );

        if (!$checkoutRequestId) {
            return $this->showError("Payment initiation failed. Please try again.");
        }

        // Create vote record
        $stmt = $this->db->prepare("
            INSERT INTO votes (nominee_id, phone, votes_count, amount, status, transaction_id)
            VALUES (?, ?, ?, ?, 'pending', ?)
        ");
        $stmt->execute([
            $this->session['nominee_id'],
            $this->phone,
            $this->session['votes_count'],
            $this->session['amount'],
            $checkoutRequestId
        ]);
        $voteId = $this->db->lastInsertId();

        // Update session
        $this->updateSession([
            'state' => 6,
            'checkout_request_id' => $checkoutRequestId
        ]);

        // Store vote_id in session data
        $this->session['vote_id'] = $voteId;
        $stmt = $this->db->prepare("UPDATE ussd_sessions SET data = ? WHERE session_id = ?");
        $stmt->execute([json_encode($this->session), $this->sessionId]);

        return "CON Processing payment... Please check your phone for STK Push";
    }

    /**
     * State 6: Check payment status
     * For simulated payments (SIM-*), auto-complete immediately
     */
    private function checkPaymentStatus() {
        $checkoutRequestId = $this->session['checkout_request_id'] ?? null;
        
        if (!$checkoutRequestId) {
            return $this->showError("Payment session not found.");
        }

        // For simulated payments, auto-complete if not already done
        if (strpos($checkoutRequestId, 'SIM-') === 0) {
            // Check if vote exists and is pending
            $stmt = $this->db->prepare("
                SELECT v.id, v.status, v.mpesa_ref, n.name 
                FROM votes v
                JOIN nominees n ON v.nominee_id = n.id
                WHERE v.transaction_id = ?
            ");
            $stmt->execute([$checkoutRequestId]);
            $vote = $stmt->fetch();

            if ($vote && $vote['status'] === 'pending') {
                // Auto-complete simulated payment
                // Receipt format: VOT#45p095j (VOT# + 7 alphanumeric chars)
                $receiptNumber = 'VOT#' . strtolower(substr(preg_replace('/[^a-z0-9]/', '', md5($checkoutRequestId . time())), 0, 7));
                $stmt = $this->db->prepare("
                    UPDATE votes 
                    SET status = 'completed', mpesa_ref = ?
                    WHERE id = ?
                ");
                $stmt->execute([$receiptNumber, $vote['id']]);
                
                // Update nominee vote count
                $stmt = $this->db->prepare("
                    UPDATE nominees 
                    SET votes_count = votes_count + ?
                    WHERE id = ?
                ");
                $stmt->execute([$this->session['votes_count'], $this->session['nominee_id']]);
                
                // Update M-Pesa transaction if exists
                $stmt = $this->db->prepare("
                    UPDATE mpesa_transactions 
                    SET status = 'completed', mpesa_receipt_number = ?, result_code = 0
                    WHERE checkout_request_id = ?
                ");
                $stmt->execute([$receiptNumber, $checkoutRequestId]);
                
                $message = "Thank you! Your {$this->session['votes_count']} vote(s) for {$vote['name']} have been recorded.";
                $message .= " Ref: {$receiptNumber}";
                return "END " . $message;
            } elseif ($vote && $vote['status'] === 'completed') {
                // Already completed
                $message = "Thank you! Your {$this->session['votes_count']} vote(s) for {$vote['name']} have been recorded.";
                if ($vote['mpesa_ref']) {
                    $message .= " Ref: {$vote['mpesa_ref']}";
                }
                return "END " . $message;
            }
        }

        // Check payment status for real payments
        $stmt = $this->db->prepare("
            SELECT v.status, v.mpesa_ref, n.name 
            FROM votes v
            JOIN nominees n ON v.nominee_id = n.id
            WHERE v.transaction_id = ?
        ");
        $stmt->execute([$checkoutRequestId]);
        $vote = $stmt->fetch();

        if (!$vote) {
            return "CON Please complete the payment on your phone...";
        }

        if ($vote['status'] === 'completed') {
            $message = "Thank you! Your {$this->session['votes_count']} vote(s) for {$vote['name']} have been recorded.";
            if ($vote['mpesa_ref']) {
                $message .= " Ref: {$vote['mpesa_ref']}";
            }
            return "END " . $message;
        } elseif ($vote['status'] === 'failed') {
            return "END Payment failed. Please try again.";
        }

        return "CON Please complete the payment on your phone...";
    }

    /**
     * Show error message
     */
    private function showError($message) {
        return "END " . $message;
    }
}
