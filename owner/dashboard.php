<?php
session_start();

if (!isset($_SESSION['owner_logged_in']) || $_SESSION['owner_logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once "../connection.php";

try {

    $stmt = $conn->prepare("SELECT COUNT(*) as total_users FROM users");
    $stmt->execute();
    $total_users = $stmt->fetch()['total_users'];

    $stmt = $conn->prepare("SELECT COUNT(*) as total_bookings FROM bookings");
    $stmt->execute();
    $total_bookings = $stmt->fetch()['total_bookings'];

    $stmt = $conn->prepare("SELECT COUNT(*) as total_orders FROM pre_orders");
    $stmt->execute();
    $total_orders = $stmt->fetch()['total_orders'];

    $stmt = $conn->prepare("SELECT SUM(amount) as total_revenue FROM payments WHERE status = 'completed'");
    $stmt->execute();
    $total_revenue = $stmt->fetch()['total_revenue'] ?? 0;

    $stmt = $conn->prepare("
        SELECT b.*, u.name as user_name, rt.table_label 
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        LEFT JOIN restaurant_tables rt ON b.table_id = rt.id 
        ORDER BY b.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("
        SELECT po.*, u.name as user_name 
        FROM pre_orders po 
        JOIN bookings b ON po.booking_id = b.id 
        JOIN users u ON b.user_id = u.id 
        ORDER BY po.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Error loading dashboard data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Dashboard - Restaurant Management</title>
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
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            padding: 20px 0;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-nav {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .nav-link {
            color: #333;
            text-decoration: none;
            font-weight: 500;
            padding: 10px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-link:hover {
            background: #3f2a1d;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: #333;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .mobile-menu-btn:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #3f2a1d;
        }

        .mobile-nav-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .mobile-nav-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .mobile-nav {
            position: fixed;
            top: 0;
            right: -100%;
            width: 280px;
            height: 100vh;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(25px);
            z-index: 1000;
            padding: 20px;
            box-shadow: -2px 0 32px rgba(0,0,0,0.12);
            border-left: 1px solid rgba(0, 0, 0, 0.05);
            transition: right 0.3s ease;
            overflow-y: auto;
        }

        .mobile-nav.active {
            right: 0;
        }

        .mobile-nav-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            color: #333;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .mobile-nav-close:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #3f2a1d;
        }

        .mobile-nav-header {
            margin-bottom: 30px;
            padding-top: 40px;
            text-align: center;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 20px;
        }

        .mobile-nav-header h3 {
            color: #333;
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        .mobile-nav-menu {
            list-style: none;
            padding: 0;
        }

        .mobile-nav-menu li {
            margin-bottom: 10px;
           
        }

        .mobile-nav-menu a {
            display: block;
            color: #333;
            text-decoration: none;
            padding: 15px 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
            background: rgba(102, 126, 234, 0.05);
            border: 1px solid rgba(102, 126, 234, 0.1);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .mobile-nav-menu a:hover {
            background: #6d6764ff;
            color: #3f2a1d;
            transform: translateX(5px);
        }

        .mobile-nav-menu a i {
            width: 20px;
            text-align: center;
        }

        .owner-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient( #6d6764ff);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .header-info h1 {
            background: #3f2a1d;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1.8rem;
            font-weight: 600;
        }

        .header-info p {
            color: #666;
            font-size: 0.9rem;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout-btn {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
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

        .stat-icon.users { background: linear-gradient(45deg, #4facfe, #00f2fe); }
        .stat-icon.bookings { background: linear-gradient(45deg, #43e97b, #38f9d7); }
        .stat-icon.orders { background: linear-gradient(45deg, #fa709a, #fee140); }
        .stat-icon.revenue { background: linear-gradient(45deg, #a8edea, #fed6e3); }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .dashboard-section {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
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

        .recent-item {
            padding: 15px;
            border-left: 4px solid #3f2a1d;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .recent-item:last-child {
            margin-bottom: 0;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .item-title {
            font-weight: 600;
            color: #333;
        }

        .item-status {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .item-details {
            color: #666;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: row;
                justify-content: space-between;
            }

            .header-nav {
                display: none;
            }

            .mobile-menu-btn {
                display: block;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .header-left {
                flex: 1;
            }

            .header-right {
                gap: 10px;
            }

            .logout-btn {
                padding: 8px 15px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <div class="owner-avatar">
                    <i class="fas fa-crown"></i>
                </div>
                <div class="header-info">
                    <h1>Owner Dashboard</h1>
                    <p>Welcome back, <?= htmlspecialchars($_SESSION['owner_name']) ?></p>
                </div>
            </div>
            
            <nav class="header-nav">
                <a href="add_staff.php" class="nav-link">
                    <i class="fas fa-user-plus"></i> Add Staff
                </a>
                <a href="financial_overview.php" class="nav-link">
                    <i class="fas fa-chart-line"></i> Financial
                </a>
                <a href="reports_messaging.php" class="nav-link">
                    <i class="fas fa-comments"></i> Messages
                </a>
                <a href="settings.php" class="nav-link">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </nav>
            
            <div class="header-right">
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <div class="mobile-nav-overlay" id="mobileOverlay"></div>
    <div class="mobile-nav" id="mobileNav">
        <button class="mobile-nav-close" id="mobileNavClose">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="mobile-nav-header">
            <h3>Owner Menu</h3>
            <p><?= htmlspecialchars($_SESSION['owner_name']) ?></p>
        </div>
        
        <ul class="mobile-nav-menu">
            <li><a href="add_staff.php"><i class="fas fa-user-plus"></i> Add Staff</a></li>
            <li><a href="financial_overview.php"><i class="fas fa-chart-line"></i> Financial Overview</a></li>
            <li><a href="reports_messaging.php"><i class="fas fa-comments"></i> Messages</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php" style="color: #dc3545; border-color: rgba(220, 53, 69, 0.2); background: rgba(220, 53, 69, 0.05);"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon users">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?= number_format($total_users) ?></div>
                <div class="stat-label">Total Users</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon bookings">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-number"><?= number_format($total_bookings) ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orders">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-number"><?= number_format($total_orders) ?></div>
                <div class="stat-label">Total Orders</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon revenue">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-number">ETB <?= number_format($total_revenue, 2) ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>

        <div class="dashboard-grid">

            <div class="dashboard-section">
                <div class="section-header">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>Recent Bookings</h3>
                </div>
                
                <?php if (empty($recent_bookings)): ?>
                    <p style="text-align: center; color: #666; padding: 20px;">No recent bookings</p>
                <?php else: ?>
                    <?php foreach ($recent_bookings as $booking): ?>
                        <div class="recent-item">
                            <div class="item-header">
                                <div class="item-title">
                                    <?= htmlspecialchars($booking['user_name']) ?>
                                </div>
                                <div class="item-status status-<?= $booking['status'] ?>">
                                    <?= ucfirst($booking['status']) ?>
                                </div>
                            </div>
                            <div class="item-details">
                                <i class="fas fa-chair"></i> <?= htmlspecialchars($booking['table_label'] ?? 'Table') ?> | 
                                <i class="fas fa-clock"></i> <?= date('M j, Y g:i A', strtotime($booking['booking_datetime'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="dashboard-section">
                <div class="section-header">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>Recent Orders</h3>
                </div>
                
                <?php if (empty($recent_orders)): ?>
                    <p style="text-align: center; color: #666; padding: 20px;">No recent orders</p>
                <?php else: ?>
                    <?php foreach ($recent_orders as $order): ?>
                        <div class="recent-item">
                            <div class="item-header">
                                <div class="item-title">
                                    Order #<?= $order['id'] ?> - <?= htmlspecialchars($order['user_name']) ?>
                                </div>
                                <div class="item-status status-<?= $order['status'] ?>">
                                    <?= ucfirst($order['status']) ?>
                                </div>
                            </div>
                            <div class="item-details">
                                <i class="fas fa-money-bill"></i> ETB <?= number_format($order['total_amount'], 2) ?> | 
                                <i class="fas fa-clock"></i> <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

<script>

let mobileMenuOpen = false;

function toggleMobileMenu() {
    const mobileNav = document.getElementById('mobileNav');
    const overlay = document.getElementById('mobileOverlay');
    
    if (!mobileNav || !overlay) return;
    
    mobileMenuOpen = !mobileMenuOpen;
    
    if (mobileMenuOpen) {
        mobileNav.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    } else {
        mobileNav.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function closeMobileMenu() {
    const mobileNav = document.getElementById('mobileNav');
    const overlay = document.getElementById('mobileOverlay');
    
    if (mobileNav && overlay) {
        mobileNav.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
        mobileMenuOpen = false;
    }
}

document.addEventListener('DOMContentLoaded', function() {

    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleMobileMenu();
        });
    }

    const mobileNavClose = document.getElementById('mobileNavClose');
    if (mobileNavClose) {
        mobileNavClose.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeMobileMenu();
        });
    }

    const mobileOverlay = document.getElementById('mobileOverlay');
    if (mobileOverlay) {
        mobileOverlay.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeMobileMenu();
        });
    }

    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeMobileMenu();
        }
    });

    const cards = document.querySelectorAll('.stat-card, .dashboard-section');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 + (index * 100));
    });
});
</script>
</body>
</html>