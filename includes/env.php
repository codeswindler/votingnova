<?php
/**
 * Environment Variables Loader
 * Loads .env file if it exists
 */

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue; // Skip comments and empty lines
        }
        $parts = explode('=', $line, 2);
        if (count($parts) < 2) {
            continue; // No '=' or empty value, skip to avoid undefined key
        }
        $name = trim($parts[0]);
        $value = trim($parts[1]);
        if ($name === '') continue;
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
