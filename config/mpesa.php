<?php
/**
 * Safaricom Daraja API Configuration
 * Get these credentials from Safaricom Developer Portal
 */

require_once __DIR__ . '/../includes/env.php';

return [
    'environment' => getenv('MPESA_ENV') ?: 'sandbox', // 'sandbox' or 'production'
    'consumer_key' => getenv('MPESA_CONSUMER_KEY') ?: '',
    'consumer_secret' => getenv('MPESA_CONSUMER_SECRET') ?: '',
    'shortcode' => getenv('MPESA_SHORTCODE') ?: '',
    'passkey' => getenv('MPESA_PASSKEY') ?: '',
    // Note: Initiator credentials are only needed for B2C transactions
    // This system uses C2B (STK Push) only, so these are not required
    'initiator_name' => getenv('MPESA_INITIATOR_NAME') ?: '',
    'initiator_password' => getenv('MPESA_INITIATOR_PASSWORD') ?: '',
    'callback_url' => getenv('MPESA_CALLBACK_URL') ?: 'https://voting.novotechafrica.co.ke/api/mpesa-callback.php',
    'base_url' => [
        'sandbox' => 'https://sandbox.safaricom.co.ke',
        'production' => 'https://api.safaricom.co.ke'
    ]
];
