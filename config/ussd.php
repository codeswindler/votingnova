<?php
/**
 * USSD Configuration
 * USSD base code configuration for the voting system
 */

require_once __DIR__ . '/../includes/env.php';

$baseCode = getenv('USSD_BASE_CODE') ?: '*519*24#';

// Extract the numeric part for parsing (e.g., *519*24# -> 519*24)
$codePattern = trim($baseCode, '*#');

return [
    'base_code' => $baseCode,
    'code_pattern' => $codePattern
];
