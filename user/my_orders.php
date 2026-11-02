<?php
session_start();
require_once "../connection.php";
require_once "../includes/payment_functions.php";
require_once "../config/payment_config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? "Customer";
$success = $error = "";

if (isset($_SESSION['payment_message'])) {
    $success = $_SESSION['payment_message'];
    unset($_SESSION['payment_message']);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    $order_id = intval($_POST['order_id'] ?? 0);
    
    if ($action === 'cancel' && $order_id > 0) {
        try {

            $stmt = $conn->prepare("SELECT po.* FROM pre_orders po 
                                   JOIN bookings b ON po.booking_id = b.id 
                                   WHERE po.id = ? AND b.user_id = ? AND po.status = 'pending'");
            $stmt->execute([$order_id, $user_id]);
            $order = $stmt->fetch();
            
            if ($order) {
                $stmt = $conn->prepare("UPDATE pre_orders SET status = 'cancelled' WHERE id = ?");
                if ($stmt->execute([$order_id])) {
                    $success = "Order cancelled successfully.";
                } else {
                    $error = "Failed to cancel order.";
                }
            } else {
                $error = "Order not found or cannot be cancelled.";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    if ($action === 'delete' && $order_id > 0) {
        try {

            $stmt = $conn->prepare("SELECT po.*, po.status as order_status FROM pre_orders po 
                                   JOIN bookings b ON po.booking_id = b.id 
                                   WHERE po.id = ? AND b.user_id = ?");
            $stmt->execute([$order_id, $user_id]);
            $order = $stmt->fetch();
            
            if ($order) {

                $can_delete = false;
                $delete_reason = "";
                
                if ($order['order_status'] === 'cancelled') {
                    $can_delete = true;
                    $delete_reason = "cancelled order";
                } elseif (in_array($order['order_status'], ['ready', 'completed'])) {
                    
                    $can_delete = true;
                    $delete_reason = "completed order";
                }
                
                if ($can_delete) {
                   
                    $conn->prepare("DELETE FROM pre_order_items WHERE pre_order_id = ?")->execute([$order_id]);
                
                    $conn->prepare("DELETE FROM payments WHERE order_id = ? AND payment_type = 'order'")->execute([$order_id]);
                    

                    $conn->prepare("DELETE FROM kitchen_orders WHERE pre_order_id = ?")->execute([$order_id]);
                    
               
                    $stmt = $conn->prepare("DELETE FROM pre_orders WHERE id = ?");
                    if ($stmt->execute([$order_id])) {
                        $success = "Order deleted successfully (" . $delete_reason . ").";
                    } else {
                        $error = "Failed to delete order.";
                    }
                } else {
                    $error = "Order cannot be deleted. Only cancelled or completed orders can be deleted.";
                }
            } else {
                $error = "Order not found.";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

$stmt = $conn->prepare("
    SELECT po.*, b.booking_datetime, b.meal_type, rt.table_label,
           GROUP_CONCAT(CONCAT(mi.name, ' (', poi.quantity, 'x)') SEPARATOR ', ') as items,
           p.payment_reference, p.status as payment_status, p.payment_method
    FROM pre_orders po
    JOIN bookings b ON po.booking_id = b.id
    LEFT JOIN restaurant_tables rt ON b.table_id = rt.id
    LEFT JOIN pre_order_items poi ON po.id = poi.pre_order_id
    LEFT JOIN menu_items mi ON poi.menu_item_id = mi.id
    LEFT JOIN payments p ON po.id = p.order_id AND p.payment_type = 'order'
    WHERE b.user_id = ?
    GROUP BY po.id
    ORDER BY po.created_at DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<title>My Orders</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: Arial; background: linear-gradient(to right , #f3fafaff 0%, #5ab7d1ff); min-height: 100vh; }
header { background: rgba(255,255,255,0.95); backdrop-filter: blur(15px); padding: 20px; display: flex; justify-content: space-between; align-items: center; }

.header-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.mobile-menu-btn {
    display: none;
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #3c575fff;
    cursor: pointer;
    padding: 10px;
    border-radius: 8px;
    transition: 0.3s;
}

.mobile-menu-btn:hover {
    background: rgba(102,126,234,0.1);
}

header h2 { background: linear-gradient( #3c575fff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
nav a { color: #333; text-decoration: none; margin: 0 10px; padding: 8px 15px; border-radius: 20px; transition: 0.3s; }
nav a:hover { background: #3c575fff; color: white; }
.logout-btn { background: #ff6b6b; color: white !important; }

.mobile-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 999;
    opacity: 0;
    visibility: hidden;
    transition: 0.3s;
}

.mobile-overlay.active {
    opacity: 1;
    visibility: visible;
}

.mobile-nav {
    position: fixed;
    top: 0;
    left: -100%;
    width: 280px;
    height: 100vh;
    background: rgba(255,255,255,0.98);
    backdrop-filter: blur(20px);
    z-index: 1000;
    padding: 20px;
    box-shadow: 2px 0 20px rgba(0,0,0,0.1);
    transition: 0.3s ease;
    overflow-y: auto;
}

.mobile-nav.active {
    left: 0;
}

.mobile-nav-close {
    display: block;
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #3c575fff;
    cursor: pointer;
    padding: 5px;
}

.mobile-nav-header {
    margin-bottom: 30px;
    padding-top: 40px;
    text-align: center;
}

.mobile-nav-header h3 {
    background: linear-gradient( #3c575fff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 1.2rem;
}

.mobile-nav a {
    display: block;
    color: #333;
    text-decoration: none;
    padding: 15px 20px;
    margin-bottom: 10px;
    border-radius: 10px;
    transition: 0.3s;
    background: rgba(102,126,234,0.1);
    border: 1px solid rgba(102,126,234,0.2);
}

.mobile-nav a:hover {
    background: linear-gradient( #3c575fff);
    color: white;
    transform: translateX(5px);
}

.mobile-nav a i {
    margin-right: 10px;
    width: 20px;
}
.container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
.message { padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
.success { background: rgba(40,167,69,0.1); color: #28a745; border: 2px solid rgba(40,167,69,0.3); }
.error { background: rgba(220,53,69,0.1); color: #dc3545; border: 2px solid rgba(220,53,69,0.3); }
.page-title { text-align: center; color: white; font-size: 2.5rem; margin-bottom: 30px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); }
.orders-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 15px; }
.order-card { 
    background: rgba(255,255,255,0.95); 
    border-radius: 12px; 
    padding: 20px; 
    box-shadow: 0 6px 20px rgba(0,0,0,0.08); 
    transition: 0.3s; 
    position: relative;
    min-height: auto;
}
.order-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.12); }
.order-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: 15px; 
    margin-top: 25px; 
    padding-left: 45px; 
}
.order-id { font-size: 1.1rem; font-weight: bold; color: #333; }
.status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.9rem; font-weight: 500; }
.status-pending { background: #fff3cd; color: #856404; }
.status-pending_payment { background: #ffeaa7; color: #856404; }
.status-confirmed { background: #d4edda; color: #155724; }
.status-preparing { background: #cce5ff; color: #004085; }
.status-ready { background: #d1ecf1; color: #0c5460; }
.status-cancelled { background: #f8d7da; color: #721c24; }
.status-paid { background: #d4edda; color: #155724; font-weight: bold; }
.status-completed { background: #d4edda; color: #155724; }
.status-failed { background: #f8d7da; color: #721c24; }
.order-details { margin-bottom: 15px; }
.detail-row { display: flex; justify-content: space-between; margin-bottom: 8px; padding: 6px 0; border-bottom: 1px solid #eee; }
.detail-label { font-weight: 500; color: #666; font-size: 0.9rem; }
.detail-value { color: #333; font-size: 0.9rem; }
.order-items { background: #f8f9fa; padding: 12px; border-radius: 8px; margin: 12px 0; }
.items-title { font-weight: bold; margin-bottom: 8px; color: #333; font-size: 0.95rem; }
.items-list { color: #666; line-height: 1.5; font-size: 0.9rem; }
.order-total { font-size: 1.2rem; font-weight: bold; color: #3c575fff; text-align: center; margin: 12px 0; }
.order-actions { display: flex; gap: 10px; justify-content: center; }
.btn { padding: 10px 20px; border: none; border-radius: 20px; cursor: pointer; font-size: 0.9rem; transition: 0.3s; text-decoration: none; display: inline-block; text-align: center; font-weight: 500; }
.btn-cancel { background: #dc3545; color: white; }
.btn-reorder { background: #28a745; color: white; }
.btn-update { background: #3c575fff; color: white; }
.btn-view { background: #6c757d; color: white; }
.btn-delete { background: #e74c3c; color: white; border: 2px solid #c0392b; }
.btn-cancel:hover { background: #c82333; transform: translateY(-2px); }
.btn-reorder:hover { background: #218838; transform: translateY(-2px); }
.btn-update:hover { background: #3c575fff; transform: translateY(-2px); }
.btn-view:hover { background: #5a6268; transform: translateY(-2px); }
.btn-delete:hover { background: #c0392b; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(231, 76, 60, 0.4); }

.dropdown {
    position: absolute;
    top: 15px;
    left: 15px;
    z-index: 10;
}

.dropdown-toggle {
    background: none;
    color: #666;
    border: none;
    padding: 8px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.dropdown-toggle:hover {
    background: rgba(102, 126, 234, 0.1);
    color: #3c575fff;
    transform: scale(1.1);
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    background: white;
    min-width: 180px;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    border: 1px solid rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-item {
    display: block;
    width: 100%;
    padding: 12px 20px;
    text-decoration: none;
    color: #333;
    border: none;
    background: none;
    text-align: left;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.95rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.dropdown-item:last-child {
    border-bottom: none;
}

.dropdown-item:hover {
    background: linear-gradient( #3c575fff);
    color: white;
    transform: translateX(5px);
}

.dropdown-item i {
    margin-right: 10px;
    width: 16px;
    text-align: center;
}

.dropdown-item.delete-item {
    color: #dc3545;
}

.dropdown-item.delete-item:hover {
    background: linear-gradient(45deg, #dc3545, #c82333);
    color: white;
}

.dropdown-item.pay-item {
    color: #28a745;
    font-weight: 600;
}

.dropdown-item.pay-item:hover {
    background: linear-gradient(45deg, #28a745, #218838);
    color: white;
}
.btn-pay { 
    background: linear-gradient(45deg, #28a745, #20c997); 
    color: white; 
    font-weight: bold; 
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    border: 2px solid #28a745;
    animation: pulse-pay 2s infinite;
}
.btn-pay:hover { 
    background: linear-gradient(45deg, #218838, #1aa179); 
    transform: translateY(-2px); 
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
}
.payment-warning {
    background: linear-gradient(45deg, #ff6b6b, #ee5a24);
    color: white;
    padding: 15px;
    border-radius: 10px;
    margin: 15px 0;
    text-align: center;
    font-weight: bold;
    box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
    border: 2px solid #ff4757;
}
.payment-warning i {
    margin-right: 8px;
    font-size: 1.2rem;
}

.status-not-paid {
    background: linear-gradient(45deg, #ff6b6b, #ee5a24) !important;
    color: white !important;
    font-weight: bold !important;
    padding: 8px 15px !important;
    border-radius: 25px !important;
    border: 2px solid #ff4757 !important;
    box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4) !important;
}

.status-unpaid {
    background: linear-gradient(45deg, #ffa726, #ff9800) !important;
    color: white !important;
    font-weight: bold !important;
    padding: 8px 15px !important;
    border-radius: 25px !important;
    border: 2px solid #ff8f00 !important;
    box-shadow: 0 4px 15px rgba(255, 167, 38, 0.4) !important;
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: center;
}

.expired-message {
    background: linear-gradient(45deg, #f8d7da, #fab1a0);
    color: #721c24;
    padding: 10px;
    border-radius: 8px;
    text-align: center;
    font-weight: 600;
    border: 2px solid #dc3545;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-bottom: 10px;
}

.order-expired {
    background: linear-gradient(45deg, #f8d7da, #fab1a0);
    color: #721c24;
    padding: 12px;
    border-radius: 10px;
    margin: 10px 0;
    text-align: center;
    font-weight: 600;
    border: 2px solid #dc3545;
    box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.no-orders { text-align: center; padding: 60px 20px; background: rgba(255,255,255,0.95); border-radius: 15px; }
.no-orders i { font-size: 4rem; color: #ddd; margin-bottom: 20px; }
.order-now-btn { display: inline-block; background: linear-gradient( #3c575fff); color: white; padding: 15px 30px; border-radius: 25px; text-decoration: none; margin-top: 20px; transition: 0.3s; }
@media (max-width: 768px) { 
    .orders-grid { grid-template-columns: 1fr; } 
    .order-actions { flex-direction: column; }
    
    .mobile-menu-btn {
        display: block;
    }
    
    nav {
        display: none;
    }
    
    header {
        flex-direction: row;
        text-align: left;
    }
    
    .action-buttons {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    
    .dropdown {
        align-self: flex-end;
    }
    
    .dropdown-menu {
        right: 0;
        left: auto;
        min-width: 180px;
    }
    
    .btn-pay {
        width: 100% !important;
        text-align: center;
        margin-bottom: 15px !important;
        font-size: 1rem !important;
        padding: 12px !important;
    }
    
    .expired-message {
        margin-bottom: 10px;
    }
}
</style>
</head>
<body>
<header>
    <div class="header-left">
        <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <h2><i class="fas fa-receipt"></i> My Orders</h2>
    </div>
    <nav>
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="my_bookings.php"><i class="fas fa-calendar-check"></i> Bookings</a>
        <a href="pre_order.php"><i class="fas fa-shopping-cart"></i> Pre-Order</a>
        <a href="View_Tables.php"><i class="fas fa-chair"></i> Tables</a>
        <a href="write_comment.php"><i class="fas fa-star"></i> Reviews</a>
        <a class="logout-btn" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</header>

<div class="mobile-nav" id="mobileNav">
    <button class="mobile-nav-close" onclick="toggleMobileMenu()">
        <i class="fas fa-times"></i>
    </button>
    
    <div class="mobile-nav-header">
        <h3>Restaurant Menu</h3>
    </div>
    
    <a href="dashboard.php">
        <i class="fas fa-home"></i> Dashboard
    </a>
    
    <a href="my_bookings.php">
        <i class="fas fa-calendar-check"></i> Bookings
    </a>
    
    <a href="pre_order.php">
        <i class="fas fa-shopping-cart"></i> Pre-Order
    </a>
    
    <a href="View_Tables.php">
        <i class="fas fa-chair"></i> Tables
    </a>
    
    <a href="write_comment.php">
        <i class="fas fa-star"></i> Reviews
    </a>
    
    <a href="../logout.php" style="background: linear-gradient(45deg, #ff6b6b, #ee5a24); color: white; margin-top: 20px;">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>

<div class="mobile-overlay" id="mobileOverlay" onclick="toggleMobileMenu()"></div>

<div class="container">
    <h1 class="page-title">My Food Orders</h1>
    
    <?php if ($success): ?>
        <div class="message success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="message error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <div class="no-orders">
            <i class="fas fa-shopping-cart"></i>
            <h3>No Orders Found</h3>
            <p>You haven't placed any food orders yet.</p>
            <a href="pre_order.php" class="order-now-btn">Place Your First Order</a>
        </div>
    <?php else: ?>
        <div class="orders-grid">
            <?php foreach ($orders as $order): ?>
                <?php

                $order_created_time = strtotime($order['created_at']);
                $current_time = time();
                $time_diff_hours = ($current_time - $order_created_time) / 3600;
                ?>
                <div class="order-card" data-order-created="<?= strtotime($order['created_at']) ?>">
                    <div class="order-header">
                        <div class="order-id">Order #<?= $order['id'] ?></div>
                        <div class="status-badge status-<?= $order['status'] ?>">
                            <?php 

                            $status_labels = [
                                'pending' => 'Pending',
                                'pending_payment' => 'Awaiting Payment',
                                'confirmed' => 'Confirmed',
                                'preparing' => 'Preparing',
                                'ready' => 'Ready',
                                'cancelled' => 'Cancelled'
                            ];
                            echo $status_labels[$order['status']] ?? ucfirst($order['status']);
                            ?>
                        </div>
                    </div>
                    
                    <div class="order-details">
                        <div class="detail-row">
                            <span class="detail-label">Table:</span>
                            <span class="detail-value"><?= htmlspecialchars($order['table_label'] ?? 'Table') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Booking Date & Time:</span>
                            <span class="detail-value"><?= date('M j, Y g:i A', strtotime($order['booking_datetime'])) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Meal Type:</span>
                            <span class="detail-value"><?= htmlspecialchars($order['meal_type']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Ordered On:</span>
                            <span class="detail-value"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></span>
                        </div>
                        

                        <div class="detail-row">
                            <span class="detail-label">Payment Status:</span>
                            <span class="detail-value">
                                <?php if ($order['payment_reference']): ?>
                                    <?php 
                                    $payment_info = formatPaymentStatus($order['payment_status'], $order['payment_method']);
                                    $method_info = formatPaymentMethod($order['payment_method']);
                                    ?>
                                    <span class="status-badge <?= $payment_info['class'] ?>">
                                        <i class="<?= $payment_info['icon'] ?>"></i> <?= $payment_info['text'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-not-paid" style="background: #ff6b6b; color: white; font-weight: bold;">
                                        <i class="fas fa-exclamation-triangle"></i> NOT PAID
                                    </span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <?php if ($order['payment_reference']): ?>
                        <div class="detail-row">
                            <span class="detail-label">Payment Method:</span>
                            <span class="detail-value">
                                <i class="<?= $method_info['icon'] ?>"></i> <?= $method_info['text'] ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($order['items']): ?>
                        <div class="order-items">
                            <div class="items-title"><i class="fas fa-utensils"></i> Ordered Items:</div>
                            <div class="items-list"><?= htmlspecialchars($order['items']) ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="order-total">
                        Total: ETB <?= number_format($order['total_amount'], 2) ?>
                    </div>
                    
                    <div class="order-actions">
                        <?php 

                        $needs_payment = false;
                        

                        if ($order['total_amount'] > 0) {
               
                            if (!$order['payment_reference'] || in_array($order['payment_status'], ['pending', 'failed', 'cancelled', 'expired'])) {
                                $needs_payment = true;
                            }
                        }
                        ?>
                        
                        <?php if ($order['status'] === 'cancelled'): ?>
                           
                            <div class="order-expired">
                                <i class="fas fa-times-circle"></i>
                                <strong>Order Cancelled</strong>
                                <br><small>This order has been cancelled and is no longer active.</small>
                            </div>
                        <?php endif; ?>
                        
                        <div class="action-buttons">
                        
                            <?php if ($needs_payment && $order['status'] !== 'cancelled'): ?>
                               
                                <a href="payment_select.php?type=order&order_id=<?= $order['id'] ?>&amount=<?= $order['total_amount'] ?>" class="btn btn-pay" style="display: block; margin: 15px 0; font-size: 1.1rem; padding: 15px 25px;">
                                    <i class="fas fa-credit-card"></i> Pay Now
                                </a>
                            <?php elseif ($order['total_amount'] > 0 && !$order['payment_reference']): ?>
                                
                                <a href="payment_select.php?type=order&order_id=<?= $order['id'] ?>&amount=<?= $order['total_amount'] ?>" class="btn btn-pay" style="display: block; margin: 15px 0; font-size: 1.1rem; padding: 15px 25px;">
                                    <i class="fas fa-credit-card"></i> Pay Now
                                </a>
                            <?php endif; ?>

                            <div class="dropdown">
                                <button class="dropdown-toggle" onclick="toggleDropdown(<?= $order['id'] ?>)">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu" id="dropdown-<?= $order['id'] ?>">
                                   
                                    <?php if (($needs_payment || ($order['total_amount'] > 0 && !$order['payment_reference'])) && $order['status'] !== 'cancelled'): ?>
                                        <a href="payment_select.php?type=order&order_id=<?= $order['id'] ?>&amount=<?= $order['total_amount'] ?>" class="dropdown-item pay-item">
                                            <i class="fas fa-credit-card"></i> Pay Now
                                        </a>
                                    <?php endif; ?>
                                    
                                    
                                    <a href="order_details.php?order_id=<?= $order['id'] ?>" class="dropdown-item">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                  
                                    <?php if ($order['status'] === 'pending' && !$needs_payment): ?>
                                        <a href="update_order.php?order_id=<?= $order['id'] ?>" class="dropdown-item">
                                            <i class="fas fa-edit"></i> Update Order
                                        </a>
                                    <?php endif; ?>
                                
                                    <?php if ($order['status'] === 'pending' && !$needs_payment): ?>
                                        <button type="button" class="dropdown-item delete-item" onclick="cancelOrder(<?= $order['id'] ?>)">
                                            <i class="fas fa-times"></i> Cancel Order
                                        </button>
                                    <?php endif; ?>
                                 
                                    <?php 

                                    $is_paid = ($order['payment_reference'] && $order['payment_status'] === 'completed');
                                    $can_reorder = in_array($order['status'], ['ready', 'cancelled']) && !$is_paid;
                                    ?>
                                    <?php if ($can_reorder): ?>
                                        <a href="pre_order.php" class="dropdown-item">
                                            <i class="fas fa-redo"></i> Reorder
                                        </a>
                                    <?php endif; ?>
                                 
                                    <?php 
                                    $can_delete = false;
                                    $delete_tooltip = "";
                                    
                                    if ($order['status'] === 'cancelled') {
                                        $can_delete = true;
                                        $delete_tooltip = "Delete this cancelled order";
                                    } elseif (in_array($order['status'], ['ready', 'completed'])) {
                                      
                                        $order_age = time() - strtotime($order['created_at']);
                                        if ($order_age > 86400) { // 24 hours
                                            $can_delete = true;
                                            $delete_tooltip = "Delete this completed order (24+ hours old)";
                                        }
                                    }
                                    ?>
                                    
                                    <?php if ($can_delete): ?>
                                        <button type="button" class="dropdown-item delete-item" onclick="deleteOrder(<?= $order['id'] ?>)" title="<?= $delete_tooltip ?>">
                                            <i class="fas fa-trash"></i> Delete Order
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>

function toggleMobileMenu() {
    const mobileNav = document.getElementById('mobileNav');
    const overlay = document.getElementById('mobileOverlay');
    
    if (!mobileNav || !overlay) {
        console.error('Mobile menu elements not found');
        return;
    }
    
    mobileNav.classList.toggle('active');
    overlay.classList.toggle('active');
}

function toggleDropdown(orderId) {
    const dropdown = document.getElementById(`dropdown-${orderId}`);
    const allDropdowns = document.querySelectorAll('.dropdown-menu');

    allDropdowns.forEach(menu => {
        if (menu.id !== `dropdown-${orderId}`) {
            menu.classList.remove('show');
        }
    });

    dropdown.classList.toggle('show');
}

document.addEventListener('click', function(event) {
    if (!event.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});


function cancelOrder(orderId) {
    if (confirm('Are you sure you want to cancel this order?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'cancel';
        
        const orderIdInput = document.createElement('input');
        orderIdInput.type = 'hidden';
        orderIdInput.name = 'order_id';
        orderIdInput.value = orderId;
        
        form.appendChild(actionInput);
        form.appendChild(orderIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteOrder(orderId) {
    if (confirm('⚠️ PERMANENTLY DELETE this order?\n\nThis will remove:\n• Order #' + orderId + '\n• All order items\n• Payment records\n• Kitchen records\n\nThis action CANNOT be undone!\n\nAre you sure you want to delete this order?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        
        const orderIdInput = document.createElement('input');
        orderIdInput.type = 'hidden';
        orderIdInput.name = 'order_id';
        orderIdInput.value = orderId;
        
        form.appendChild(actionInput);
        form.appendChild(orderIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

window.addEventListener('resize', function() {
    const mobileNav = document.getElementById('mobileNav');
    const overlay = document.getElementById('mobileOverlay');
    
    if (window.innerWidth > 768 && mobileNav && overlay) {
        mobileNav.classList.remove('active');
        overlay.classList.remove('active');
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuItems = document.querySelectorAll('.mobile-nav a');
    mobileMenuItems.forEach(item => {
        item.addEventListener('click', function() {

            if (window.innerWidth <= 768) {
                setTimeout(() => {
                    toggleMobileMenu();
                }, 200);
            }
        });
    });

    const unpaidOrders = document.querySelectorAll('.status-not-paid');
    unpaidOrders.forEach(badge => {
        const orderCard = badge.closest('.order-card');
        if (orderCard) {
            orderCard.style.border = '3px solid #ff6b6b';
            orderCard.style.boxShadow = '0 8px 25px rgba(255, 107, 107, 0.3)';
        }
    });
});
</script>

</body>
</html>