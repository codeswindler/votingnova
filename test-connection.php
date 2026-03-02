<?php
/**
 * Quick test script to check database connection and PHP errors
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing Voting Nova Configuration</h2>";

// Test 1: Check if .env file exists
echo "<h3>1. Environment File</h3>";
if (file_exists(__DIR__ . '/.env')) {
    echo "✓ .env file exists<br>";
} else {
    echo "✗ .env file NOT found<br>";
    echo "Please create .env from env.example<br>";
}

// Test 2: Load environment
echo "<h3>2. Environment Loading</h3>";
if (file_exists(__DIR__ . '/includes/env.php')) {
    require_once __DIR__ . '/includes/env.php';
    echo "✓ env.php loaded<br>";
} else {
    echo "✗ env.php NOT found<br>";
}

// Test 3: Database connection
echo "<h3>3. Database Connection</h3>";
try {
    require_once __DIR__ . '/includes/db.php';
    $db = Database::getInstance();
    echo "✓ Database connection successful<br>";
    
    // Test query
    $stmt = $db->query("SELECT 1");
    echo "✓ Database query test successful<br>";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . htmlspecialchars($e->getMessage()) . "<br>";
}

// Test 4: Check required files
echo "<h3>4. Required Files</h3>";
$requiredFiles = [
    'includes/auth.php',
    'includes/db.php',
    'includes/env.php',
    'admin/dashboard.php',
    'admin/login.php'
];

foreach ($requiredFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✓ $file exists<br>";
    } else {
        echo "✗ $file NOT found<br>";
    }
}

// Test 5: PHP Configuration
echo "<h3>5. PHP Configuration</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Error Reporting: " . (ini_get('display_errors') ? 'Enabled' : 'Disabled') . "<br>";

// Test 6: Check admin_users table
echo "<h3>6. Database Tables</h3>";
try {
    $db = Database::getInstance();
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Found " . count($tables) . " tables<br>";
    
    if (in_array('admin_users', $tables)) {
        echo "✓ admin_users table exists<br>";
        $userCount = $db->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
        echo "Admin users: $userCount<br>";
    } else {
        echo "✗ admin_users table NOT found<br>";
    }
} catch (Exception $e) {
    echo "✗ Error checking tables: " . htmlspecialchars($e->getMessage()) . "<br>";
}

echo "<hr>";
echo "<p><strong>If all tests pass, the issue might be in dashboard.php. Check server logs:</strong></p>";
echo "<code>sudo journalctl -u voting-nova -n 50</code>";
