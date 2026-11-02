<?php

require_once __DIR__ . '/../config/payment_config.php';

function createPaymentsTable($conn) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            payment_reference VARCHAR(100) UNIQUE NOT NULL,
            user_id INT NOT NULL,
            order_id INT NULL,
            booking_id INT NULL,
            payment_type ENUM('order', 'booking', 'deposit') NOT NULL,
            payment_method ENUM('telebirr', 'cbe', 'cash') NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'ETB',
            status ENUM('pending', 'completed', 'failed', 'cancelled', 'expired') DEFAULT 'pending',
            transaction_id VARCHAR(255) NULL,
            payment_data JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        $conn->exec($sql);
        return true;
    } catch (Exception $e) {
        error_log("Error creating payments table: " . $e->getMessage());
        return false;
    }
}

function generatePaymentReference() {
    return 'PAY_' . date('Ymd') . '_' . strtoupper(substr(uniqid(), -8));
}

function createPayment($conn, $user_id, $amount, $payment_type, $order_id = null, $booking_id = null) {
    try {
        $payment_reference = generatePaymentReference();

        
        $stmt = $conn->prepare("
            INSERT INTO payments (payment_reference, user_id, order_id, booking_id, payment_type, amount, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->execute([$payment_reference, $user_id, $order_id, $booking_id, $payment_type, $amount]);
        
        return [
            'payment_id' => $conn->lastInsertId(),
            'payment_reference' => $payment_reference,
            'amount' => $amount
        ];
    } catch (Exception $e) {
        error_log("Error creating payment: " . $e->getMessage());
        return false;
    }
}

function getPaymentByReference($conn, $payment_reference) {
    try {
        $stmt = $conn->prepare("SELECT * FROM payments WHERE payment_reference = ?");
        $stmt->execute([$payment_reference]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting payment: " . $e->getMessage());
        return false;
    }
}

function updatePaymentStatus($conn, $payment_reference, $status, $transaction_id = null, $payment_data = null) {
    try {
        $sql = "UPDATE payments SET status = ?, updated_at = CURRENT_TIMESTAMP";
        $params = [$status];
        
        if ($transaction_id) {
            $sql .= ", transaction_id = ?";
            $params[] = $transaction_id;
        }
        
        if ($payment_data) {
            $sql .= ", payment_data = ?";
            $params[] = json_encode($payment_data);
        }
        
        $sql .= " WHERE payment_reference = ?";
        $params[] = $payment_reference;
        
        $stmt = $conn->prepare($sql);
        return $stmt->execute($params);
    } catch (Exception $e) {
        error_log("Error updating payment status: " . $e->getMessage());
        return false;
    }
}

function initiateTeleBirrPayment($payment_reference, $amount, $phone_number, $description) {
    
    $payment_data = [
        'merchant_id' => TELEBIRR_MERCHANT_ID,
        'amount' => $amount,
        'currency' => PAYMENT_CURRENCY,
        'phone_number' => $phone_number,
        'description' => $description,
        'reference' => $payment_reference,
        'callback_url' => TELEBIRR_WEBHOOK_URL,
        'return_url' => PAYMENT_SUCCESS_URL,
        'cancel_url' => PAYMENT_CANCEL_URL
    ];

    $response = [
        'success' => true,
        'payment_url' => 'payment_process.php?ref=' . $payment_reference . '&method=telebirr',
        'transaction_id' => 'TB_' . time() . '_' . rand(1000, 9999),
        'message' => 'Payment initiated successfully'
    ];
    
    return $response;
}

function initiateCBEPayment($payment_reference, $amount, $account_number, $description) {
  
    $payment_data = [
        'merchant_id' => CBE_MERCHANT_ID,
        'amount' => $amount,
        'currency' => PAYMENT_CURRENCY,
        'account_number' => $account_number,
        'description' => $description,
        'reference' => $payment_reference,
        'callback_url' => CBE_WEBHOOK_URL,
        'return_url' => PAYMENT_SUCCESS_URL,
        'cancel_url' => PAYMENT_CANCEL_URL
    ];
    
    $response = [
        'success' => true,
        'payment_url' => 'payment_process.php?ref=' . $payment_reference . '&method=cbe',
        'transaction_id' => 'CBE_' . time() . '_' . rand(1000, 9999),
        'message' => 'Payment initiated successfully'
    ];
    
    return $response;
}

function processCashPayment($payment_reference) {

    return [
        'success' => true,
        'message' => 'Cash payment selected. Please pay upon arrival.',
        'transaction_id' => 'CASH_' . time()
    ];
}

function isPaymentExpired($payment) {
    return false; 
}

function cancelExpiredPayments($conn) {

    return 0;
}

function getUserPayments($conn, $user_id, $limit = 10) {
    try {
        $stmt = $conn->prepare("
            SELECT p.*, 
                   CASE 
                       WHEN p.order_id IS NOT NULL THEN CONCAT('Order #', p.order_id)
                       WHEN p.booking_id IS NOT NULL THEN CONCAT('Booking #', p.booking_id)
                       ELSE 'Unknown'
                   END as payment_for
            FROM payments p 
            WHERE p.user_id = ? 
            ORDER BY p.created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting user payments: " . $e->getMessage());
        return [];
    }
}

function formatPaymentStatus($status, $payment_method = null) {

    if ($payment_method === 'cash' && $status === 'pending') {
        return ['text' => 'UNPAID', 'class' => 'status-unpaid', 'icon' => 'fas fa-money-bill-wave'];
    }
    
    $statuses = [
        'pending' => ['text' => 'Pending', 'class' => 'status-pending', 'icon' => 'fas fa-clock'],
        'completed' => ['text' => 'PAID', 'class' => 'status-paid', 'icon' => 'fas fa-check-circle'],
        'failed' => ['text' => 'Failed', 'class' => 'status-failed', 'icon' => 'fas fa-times-circle'],
        'cancelled' => ['text' => 'Cancelled', 'class' => 'status-cancelled', 'icon' => 'fas fa-ban'],
        'expired' => ['text' => 'Expired', 'class' => 'status-expired', 'icon' => 'fas fa-hourglass-end']
    ];
    
    return $statuses[$status] ?? ['text' => 'Unknown', 'class' => 'status-unknown', 'icon' => 'fas fa-question'];
}

function formatPaymentMethod($method) {
    $methods = [
        'telebirr' => ['text' => 'TeleBirr', 'class' => 'method-telebirr', 'icon' => 'fas fa-mobile-alt'],
        'cbe' => ['text' => 'CBE Bank', 'class' => 'method-cbe', 'icon' => 'fas fa-university'],
        'cash' => ['text' => 'Cash on Arrival', 'class' => 'method-cash', 'icon' => 'fas fa-money-bill-wave']
    ];
    
    return $methods[$method] ?? ['text' => 'Unknown', 'class' => 'method-unknown', 'icon' => 'fas fa-question'];
}

?>