<?php
/**
 * Advanta SMS/USSD API Configuration
 * Get these credentials from your Advanta account
 */

require_once __DIR__ . '/../includes/env.php';

return [
    'api_key' => getenv('ADVANTA_API_KEY') ?: '',
    'partner_id' => getenv('ADVANTA_PARTNER_ID') ?: '',
    'shortcode' => getenv('ADVANTA_SHORTCODE') ?: '',
    'sms_api_url' => 'https://api.advantasms.com/api/v1/send',
    'ussd_callback_url' => getenv('ADVANTA_USSD_CALLBACK_URL') ?: 'https://voting.novotechafrica.co.ke/api/ussd.php'
];
