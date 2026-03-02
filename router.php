<?php
/**
 * Router script for PHP built-in server
 * Handles routing and .htaccess-like functionality
 */

$requestUri = $_SERVER['REQUEST_URI'];
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// Remove query string for file checking
$filePath = __DIR__ . $requestPath;

// If it's a directory, check for index.php
if (is_dir($filePath) && $filePath !== __DIR__) {
    $indexFile = rtrim($filePath, '/') . '/index.php';
    if (file_exists($indexFile)) {
        return false; // Let PHP serve the index.php
    }
}

// If it's a file that exists, serve it
if (is_file($filePath)) {
    return false; // Let PHP serve the file
}

// Handle root path - redirect to admin
if ($requestPath === '/' || $requestPath === '') {
    header('Location: /admin/');
    exit;
}

// If file doesn't exist, return 404
http_response_code(404);
echo '<!doctype html><html><head><title>404 Not Found</title></head><body>';
echo '<h1>404 Not Found</h1>';
echo '<p>The requested resource was not found on this server.</p>';
echo '<p>Available endpoints:</p>';
echo '<ul>';
echo '<li><a href="/admin/">Admin Dashboard</a></li>';
echo '<li><a href="/api/ussd.php">USSD API</a></li>';
echo '</ul>';
echo '</body></html>';
exit;
