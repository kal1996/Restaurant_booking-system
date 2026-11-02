<?php
function getPaymentAccounts() {
    $owner_config_file = __DIR__ . '/owner_settings.json';
    $default_accounts = [
        'telebirr_account' => '0991826385',
        'cbe_account' => '1000324271117'
    ];
    
    if (file_exists($owner_config_file)) {
        $settings = json_decode(file_get_contents($owner_config_file), true);
        if ($settings) {
            return [
                'telebirr_account' => $settings['telebirr_account'] ?? $default_accounts['telebirr_account'],
                'cbe_account' => $settings['cbe_account'] ?? $default_accounts['cbe_account']
            ];
        }
    }
    
    return $default_accounts;
}

function getCurrentPaymentConfig() {
    return [
        'telebirr_phone' => defined('TELEBIRR_PHONE') ? TELEBIRR_PHONE : 'Not defined',
        'cbe_account' => defined('CBE_ACCOUNT') ? CBE_ACCOUNT : 'Not defined',
        'config_loaded' => true,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

$payment_accounts = getPaymentAccounts();

define('ENABLE_TELEBIRR', true);
define('ENABLE_CBE', true);
define('ENABLE_CASH_ON_ARRIVAL', true);

define('TELEBIRR_MERCHANT_ID', 'RESTAURANT_TELEBIRR');
define('TELEBIRR_PHONE', $payment_accounts['telebirr_account']);
define('TELEBIRR_SANDBOX', true); 

define('CBE_MERCHANT_ID', 'RESTAURANT_CBE');
define('CBE_ACCOUNT', $payment_accounts['cbe_account']); 
define('CBE_SANDBOX', true); 

define('REQUIRE_PAYMENT_FOR_BOOKING', false); 
define('REQUIRE_PAYMENT_FOR_ORDERS', true);   
define('BOOKING_DEPOSIT_AMOUNT', 50.00);      
define('PAYMENT_TIMEOUT_MINUTES', 0);        

define('PAYMENT_CURRENCY', 'ETB');
define('PAYMENT_CURRENCY_SYMBOL', 'ETB');

define('PAYMENT_PENDING', 'pending');
define('PAYMENT_COMPLETED', 'completed');
define('PAYMENT_FAILED', 'failed');
define('PAYMENT_CANCELLED', 'cancelled');
define('PAYMENT_EXPIRED', 'expired');

define('TELEBIRR_WEBHOOK_URL', 'https://yourrestaurant.com/webhooks/telebirr.php');
define('CBE_WEBHOOK_URL', 'https://yourrestaurant.com/webhooks/cbe.php');

define('PAYMENT_SUCCESS_URL', '/user/payment_success.php');
define('PAYMENT_CANCEL_URL', '/user/payment_cancel.php');
define('PAYMENT_FAIL_URL', '/user/payment_fail.php');
?>