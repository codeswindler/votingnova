<?php
/**
 * Setup Script
 * Helps verify system requirements and configuration
 */

echo "USSD Voting System - Setup Check\n";
echo "================================\n\n";

// Check PHP version
echo "PHP Version: " . PHP_VERSION . "\n";
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    echo "❌ PHP 7.4 or higher is required\n";
} else {
    echo "✅ PHP version is OK\n";
}

// Check required extensions
$required = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'];
echo "\nChecking PHP Extensions:\n";
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ $ext\n";
    } else {
        echo "❌ $ext (required)\n";
    }
}

// Check file permissions
echo "\nChecking File Permissions:\n";
$writable = ['config', 'includes', 'api', 'admin'];
foreach ($writable as $dir) {
    if (is_writable(__DIR__ . '/' . $dir)) {
        echo "✅ $dir/ is writable\n";
    } else {
        echo "⚠️  $dir/ is not writable (may need chmod 755)\n";
    }
}

// Check .env file
echo "\nChecking Configuration:\n";
if (file_exists(__DIR__ . '/.env')) {
    echo "✅ .env file exists\n";
} else {
    echo "⚠️  .env file not found. Copy .env.example to .env and configure it.\n";
}

// Check database connection
echo "\nChecking Database Connection:\n";
try {
    require_once __DIR__ . '/includes/db.php';
    $db = getDB();
    echo "✅ Database connection successful\n";
    
    // Check tables
    $tables = ['categories', 'nominees', 'votes', 'mpesa_transactions', 'admin_users', 'ussd_sessions', 'web_vote_sessions'];
    $stmt = $db->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "\nChecking Database Tables:\n";
    foreach ($tables as $table) {
        if (in_array($table, $existingTables)) {
            echo "✅ Table '$table' exists\n";
        } else {
            echo "❌ Table '$table' is missing. Run database/schema.sql\n";
        }
    }
    
    // Check if data is seeded
    $stmt = $db->query("SELECT COUNT(*) FROM categories");
    $catCount = $stmt->fetchColumn();
    if ($catCount > 0) {
        echo "✅ Categories seeded ($catCount categories)\n";
    } else {
        echo "⚠️  No categories found. Run database/seed.sql\n";
    }
    
    $stmt = $db->query("SELECT COUNT(*) FROM nominees");
    $nomCount = $stmt->fetchColumn();
    if ($nomCount > 0) {
        echo "✅ Nominees seeded ($nomCount nominees)\n";
    } else {
        echo "⚠️  No nominees found. Run database/seed.sql\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    echo "   Check your database configuration in config/database.php or .env\n";
}

echo "\n================================\n";
echo "Setup check complete!\n";
echo "\nNext steps:\n";
echo "1. Configure .env file with your API credentials\n";
echo "2. Set up Advanta USSD callback URL\n";
echo "3. Configure M-Pesa Daraja API\n";
echo "4. Change default admin password\n";
echo "5. Test USSD flow\n";
