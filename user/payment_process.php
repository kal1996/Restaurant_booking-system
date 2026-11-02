<?php
session_start();
require_once "../connection.php";
require_once "../includes/payment_functions.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_name = $_SESSION['name'] ?? 'Customer';
$payment_reference = $_GET['ref'] ?? '';
$payment_method = $_GET['method'] ?? '';

if (empty($payment_reference) || empty($payment_method)) {
    header("Location: dashboard.php");
    exit();
}

$payment = getPaymentByReference($conn, $payment_reference);
if (!$payment || $payment['user_id'] != $_SESSION['user_id']) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'confirm') {

        $transaction_id = ($payment_method === 'telebirr' ? 'TB_' : 'CBE_') . time() . '_' . rand(1000, 9999);
        
        updatePaymentStatus($conn, $payment_reference, 'completed', $transaction_id);

        if ($payment['payment_type'] === 'order' && $payment['order_id']) {
            $conn->prepare("UPDATE pre_orders SET status = 'confirmed' WHERE id = ?")->execute([$payment['order_id']]);
        } elseif ($payment['payment_type'] === 'booking' && $payment['booking_id']) {
            $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?")->execute([$payment['booking_id']]);
        }
        
        header("Location: payment_success.php?ref=" . $payment_reference);
        exit();
    } elseif ($action === 'cancel') {
        updatePaymentStatus($conn, $payment_reference, 'cancelled');
        header("Location: payment_cancel.php?ref=" . $payment_reference);
        exit();
    }
}

$method_info = formatPaymentMethod($payment_method);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Processing Payment - Ethiopian Restaurant</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: linear-gradient( to right , #f3fafaff 0%, #5ab7d1ff); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        
        .container { max-width: 500px; width: 90%; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); text-align: center; }
        
        .payment-icon { font-size: 4rem; color: #3c575fff; margin-bottom: 20px; }
        .title { color: #333; margin-bottom: 10px; }
        .subtitle { color: #666; margin-bottom: 30px; }
        
        .payment-info { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 30px; text-align: left; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .info-row:last-child { margin-bottom: 0; font-weight: bold; color: #3c575fff; }
        
        .simulation-notice { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 10px; margin-bottom: 30px; }
        .simulation-notice h4 { color: #856404; margin-bottom: 10px; }
        .simulation-notice p { color: #856404; font-size: 0.9rem; }
        
        .payment-actions { display: flex; gap: 15px; }
        .btn { flex: 1; padding: 15px; border: none; border-radius: 10px; font-size: 16px; cursor: pointer; transition: 0.3s; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        
        .payment-instructions { background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 10px; margin-bottom: 30px; }
        .payment-instructions h4 { color: #856404; margin-bottom: 15px; }
        .instruction-box { background: white; padding: 15px; border-radius: 8px; }
        .payment-number { font-size: 1.2rem; font-weight: bold; color: #3c575fff; margin: 10px 0; }
        
        @media (max-width: 768px) {
            .container { padding: 30px 20px; }
            .payment-actions { flex-direction: column; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="payment-icon">
        <i class="<?= $method_info['icon'] ?>"></i>
    </div>
    
    <h1 class="title">Processing <?= $method_info['text'] ?> Payment</h1>
    <p class="subtitle">Please complete your payment to confirm your <?= $payment['payment_type'] ?></p>
    
    <div class="payment-info">
        <div class="info-row">
            <span>Payment Reference:</span>
            <span><?= htmlspecialchars($payment_reference) ?></span>
        </div>
        <div class="info-row">
            <span>Payment Method:</span>
            <span><?= $method_info['text'] ?></span>
        </div>
        <div class="info-row">
            <span>Amount:</span>
            <span>ETB <?= number_format($payment['amount'], 2) ?></span>
        </div>
    </div>
    
    <div class="payment-instructions">
        <h4><i class="fas fa-mobile-alt"></i> Payment Instructions</h4>
        <?php if ($payment_method === 'telebirr'): ?>
            <div class="instruction-box">
                <p><strong>Send payment via TeleBirr to:</strong></p>
                <p class="payment-number">📱 <?= TELEBIRR_PHONE ?></p>
                <p><strong>Amount:</strong> ETB <?= number_format($payment['amount'], 2) ?></p>
                <p><strong>Reference:</strong> <?= htmlspecialchars($payment_reference) ?></p>
                <p><em>After sending payment, click "Confirm Payment" below</em></p>
            </div>
        <?php elseif ($payment_method === 'cbe'): ?>
            <div class="instruction-box">
                <p><strong>Transfer payment via CBE to:</strong></p>
                <p class="payment-number">🏛️ <?= CBE_ACCOUNT ?></p>
                <p><strong>Amount:</strong> ETB <?= number_format($payment['amount'], 2) ?></p>
                <p><strong>Reference:</strong> <?= htmlspecialchars($payment_reference) ?></p>
                <p><em>After transferring, click "Confirm Payment" below</em></p>
            </div>
        <?php endif; ?>
    </div>
    
    <form method="POST">
        <div class="payment-actions">
            <button type="submit" name="action" value="confirm" class="btn btn-success">
                <i class="fas fa-check"></i> Confirm Payment
            </button>
            <button type="submit" name="action" value="cancel" class="btn btn-danger">
                <i class="fas fa-times"></i> Cancel Payment
            </button>
        </div>
    </form>
</div>

<script>

document.addEventListener('DOMContentLoaded', function() {

});
</script>

</body>
</html>