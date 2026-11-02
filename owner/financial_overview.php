<?php
session_start();

if (!isset($_SESSION['owner_logged_in']) || $_SESSION['owner_logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once "../connection.php";

try {

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as today_revenue 
        FROM payments 
        WHERE status = 'completed' 
        AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute();
    $today_revenue = $stmt->fetch()['today_revenue'];

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as month_revenue 
        FROM payments 
        WHERE status = 'completed' 
        AND YEAR(created_at) = YEAR(CURDATE()) 
        AND MONTH(created_at) = MONTH(CURDATE())
    ");
    $stmt->execute();
    $month_revenue = $stmt->fetch()['month_revenue'];

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_revenue 
        FROM payments 
        WHERE status = 'completed'
    ");
    $stmt->execute();
    $total_revenue = $stmt->fetch()['total_revenue'];

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as pending_amount 
        FROM payments 
        WHERE status = 'pending'
    ");
    $stmt->execute();
    $pending_amount = $stmt->fetch()['pending_amount'];

    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, COALESCE(SUM(amount), 0) as revenue
        FROM payments 
        WHERE status = 'completed' 
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $stmt->execute();
    $daily_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("
        SELECT payment_method, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
        FROM payments 
        WHERE status = 'completed'
        GROUP BY payment_method
    ");
    $stmt->execute();
    $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("
        SELECT p.*, u.name as user_name, po.id as order_id
        FROM payments p
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN pre_orders po ON p.order_id = po.id
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Error loading financial data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Overview - Owner Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(to right, #faf3e0 0%, #e9dbb4ff); 
            min-height: 100vh;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            padding: 20px 0;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            background: #3f2a1d;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1.8rem;
        }

        .back-btn {
            background: #6d6764ff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .financial-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .financial-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .financial-card:hover {
            transform: translateY(-5px);
        }

        .financial-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.5rem;
            color: white;
        }

        .icon-today { background: linear-gradient(45deg, #4facfe, #00f2fe); }
        .icon-month { background: linear-gradient(45deg, #43e97b, #38f9d7); }
        .icon-total { background: linear-gradient(45deg, #fa709a, #fee140); }
        .icon-pending { background: linear-gradient(45deg, #ff9a9e, #fecfef); }

        .financial-amount {
            font-size: 1.8rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .financial-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-header h3 {
            color: #333;
            font-size: 1.2rem;
        }

        .section-header i {
            color: #3f2a1d;
            font-size: 1.3rem;
        }

        .revenue-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #3f2a1d;
        }

        .revenue-date {
            font-weight: 600;
            color: #333;
        }

        .revenue-amount {
            font-size: 1.1rem;
            font-weight: bold;
            color: #28a745;
        }

        .payment-method-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .method-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .method-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
        }

        .method-telebirr { background: #ff6b6b; }
        .method-cbe { background: #4ecdc4; }
        .method-cash { background: #45b7d1; }

        .transaction-item {
            padding: 15px;
            border-left: 4px solid #3f2a1d;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .transaction-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .transaction-id {
            font-weight: 600;
            color: #333;
        }

        .transaction-status {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-completed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-failed { background: #f8d7da; color: #721c24; }

        .transaction-details {
            color: #666;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .financial-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <h1><i class="fas fa-chart-line"></i> Financial Overview</h1>
            </div>
            <div class="header-right">
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="financial-grid">
            <div class="financial-card">
                <div class="financial-icon icon-today">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="financial-amount">ETB <?= number_format($today_revenue, 2) ?></div>
                <div class="financial-label">Today's Revenue</div>
            </div>

            <div class="financial-card">
                <div class="financial-icon icon-month">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="financial-amount">ETB <?= number_format($month_revenue, 2) ?></div>
                <div class="financial-label">This Month</div>
            </div>

            <div class="financial-card">
                <div class="financial-icon icon-total">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="financial-amount">ETB <?= number_format($total_revenue, 2) ?></div>
                <div class="financial-label">Total Revenue</div>
            </div>

            <div class="financial-card">
                <div class="financial-icon icon-pending">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="financial-amount">ETB <?= number_format($pending_amount, 2) ?></div>
                <div class="financial-label">Pending Payments</div>
            </div>
        </div>

        <div class="content-grid">
            <div class="section">
                <div class="section-header">
                    <i class="fas fa-chart-bar"></i>
                    <h3>Daily Revenue (Last 7 Days)</h3>
                </div>
                
                <?php if (empty($daily_revenue)): ?>
                    <p style="text-align: center; color: #666; padding: 20px;">No revenue data for the last 7 days</p>
                <?php else: ?>
                    <?php foreach ($daily_revenue as $day): ?>
                        <div class="revenue-item">
                            <div class="revenue-date">
                                <?= date('l, M j', strtotime($day['date'])) ?>
                            </div>
                            <div class="revenue-amount">
                                ETB <?= number_format($day['revenue'], 2) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div>
                <div class="section" style="margin-bottom: 20px;">
                    <div class="section-header">
                        <i class="fas fa-credit-card"></i>
                        <h3>Payment Methods</h3>
                    </div>
                    
                    <?php if (empty($payment_methods)): ?>
                        <p style="text-align: center; color: #666; padding: 20px;">No payment data available</p>
                    <?php else: ?>
                        <?php foreach ($payment_methods as $method): ?>
                            <div class="payment-method-item">
                                <div class="method-info">
                                    <div class="method-icon method-<?= $method['payment_method'] ?>">
                                        <?php
                                        $icons = [
                                            'telebirr' => 'fas fa-mobile-alt',
                                            'cbe' => 'fas fa-university',
                                            'cash' => 'fas fa-money-bill-wave'
                                        ];
                                        ?>
                                        <i class="<?= $icons[$method['payment_method']] ?? 'fas fa-question' ?>"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: #333;">
                                            <?= ucfirst($method['payment_method']) ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: #666;">
                                            <?= $method['count'] ?> transactions
                                        </div>
                                    </div>
                                </div>
                                <div style="font-weight: bold; color: #28a745;">
                                    ETB <?= number_format($method['total'], 2) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="section">
                    <div class="section-header">
                        <i class="fas fa-history"></i>
                        <h3>Recent Transactions</h3>
                    </div>
                    
                    <?php if (empty($recent_transactions)): ?>
                        <p style="text-align: center; color: #666; padding: 20px;">No recent transactions</p>
                    <?php else: ?>
                        <?php foreach (array_slice($recent_transactions, 0, 5) as $transaction): ?>
                            <div class="transaction-item">
                                <div class="transaction-header">
                                    <div class="transaction-id">
                                        <?= htmlspecialchars($transaction['payment_reference']) ?>
                                    </div>
                                    <div class="transaction-status status-<?= $transaction['status'] ?>">
                                        <?= ucfirst($transaction['status']) ?>
                                    </div>
                                </div>
                                <div class="transaction-details">
                                    <div><strong>ETB <?= number_format($transaction['amount'], 2) ?></strong></div>
                                    <div><?= htmlspecialchars($transaction['user_name'] ?? 'Unknown User') ?></div>
                                    <div><?= date('M j, Y g:i A', strtotime($transaction['created_at'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>