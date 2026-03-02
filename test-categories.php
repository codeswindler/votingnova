<?php
/**
 * Quick test to check if categories exist in database
 */

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Check categories
    $stmt = $db->query("SELECT id, name FROM categories ORDER BY id");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Categories in Database:</h2>\n";
    echo "<p>Total: " . count($categories) . "</p>\n";
    echo "<ul>\n";
    foreach ($categories as $cat) {
        echo "<li>ID: {$cat['id']} - {$cat['name']}</li>\n";
    }
    echo "</ul>\n";
    
    // Test the exact query used by USSD handler
    echo "<h2>USSD Handler Query Test:</h2>\n";
    $stmt = $db->query("SELECT id, name FROM categories ORDER BY id");
    $categories = $stmt->fetchAll();
    
    $message = "Support your champion! Vote now in the Murang'a 40 Under 40 Awards:\n";
    foreach ($categories as $cat) {
        $message .= $cat['id'] . ". " . $cat['name'] . "\n";
    }
    
    echo "<pre>" . htmlspecialchars($message) . "</pre>\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
