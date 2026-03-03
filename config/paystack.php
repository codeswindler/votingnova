<?php
/**
 * Paystack API Configuration
 * Used when PAYMENT_PROVIDER=paystack for STK-style mobile money
 */

require_once __DIR__ . '/../includes/env.php';

return [
    'secret_key' => getenv('PAYSTACK_SECRET_KEY') ?: '',
    'public_key' => getenv('PAYSTACK_PUBLIC_KEY') ?: '',
    'webhook_secret' => getenv('PAYSTACK_WEBHOOK_SECRET') ?: '',
    // Email sent to Paystack Charge API (must be valid format; .local often rejected)
    'charge_email' => getenv('PAYSTACK_CHARGE_EMAIL') ?: 'vote@votingnova.app',
];
