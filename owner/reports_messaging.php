<?php
session_start();

if (!isset($_SESSION['owner_logged_in']) || $_SESSION['owner_logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once "../connection.php";
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('chef_to_admin', 'admin_to_chef', 'general') DEFAULT 'general',
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        status ENUM('unread', 'read', 'replied') DEFAULT 'unread',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        read_at TIMESTAMP NULL,
        INDEX idx_sender (sender_id),
        INDEX idx_receiver (receiver_id),
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    )");
} catch (Exception $e) {
    error_log("Error creating reports table: " . $e->getMessage());
}

$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send_report') {
        $receiver_id = intval($_POST['receiver_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        $type = $_POST['type'] ?? 'general';
        
        if (empty($title) || empty($message) || $receiver_id <= 0) {
            $error = "Please fill in all required fields.";
        } else {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO reports (sender_id, receiver_id, title, message, type, priority, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'unread', NOW())
                ");
                
                if ($stmt->execute([0, $receiver_id, $title, $message, $type, $priority])) {
                    $success = "Message sent successfully!";
                } else {
                    $error = "Failed to send message. Please try again.";
                }
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
    
    if ($action === 'reply_report') {
        $original_report_id = intval($_POST['original_report_id'] ?? 0);
        $reply_message = trim($_POST['reply_message'] ?? '');
        
        if (empty($reply_message) || $original_report_id <= 0) {
            $error = "Please enter a reply message.";
        } else {
            try {

                $stmt = $conn->prepare("SELECT * FROM reports WHERE id = ?");
                $stmt->execute([$original_report_id]);
                $original_report = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($original_report) {
               
                    $reply_title = "Re: " . $original_report['title'];
                    $stmt = $conn->prepare("
                        INSERT INTO reports (sender_id, receiver_id, title, message, type, priority, status, created_at) 
                        VALUES (?, ?, ?, ?, 'general', 'medium', 'unread', NOW())
                    ");
                    
                    if ($stmt->execute([0, $original_report['sender_id'], $reply_title, $reply_message])) {
                      
                        $stmt = $conn->prepare("UPDATE reports SET status = 'replied' WHERE id = ?");
                        $stmt->execute([$original_report_id]);
                        
                        $success = "Reply sent successfully!";
                    } else {
                        $error = "Failed to send reply. Please try again.";
                    }
                } else {
                    $error = "Original message not found.";
                }
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

try {
    $stmt = $conn->prepare("SELECT id, name, role FROM users WHERE role IN ('admin', 'chef') ORDER BY role, name");
    $stmt->execute();
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $staff = [];
}

try {
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE role = 'admin' ORDER BY name");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $admins = [];
}

try {
    $stmt = $conn->prepare("
        SELECT r.*, u.name as sender_name, u.role as sender_role
        FROM reports r 
        LEFT JOIN users u ON r.sender_id = u.id 
        WHERE r.receiver_id = 0 OR r.type = 'general'
        ORDER BY r.created_at DESC
    ");
    $stmt->execute();
    $received_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $received_reports = [];
}

try {
    $stmt = $conn->prepare("
        SELECT r.*, u.name as receiver_name, u.role as receiver_role
        FROM reports r 
        LEFT JOIN users u ON r.receiver_id = u.id 
        WHERE r.sender_id = 0
        ORDER BY r.created_at DESC
    ");
    $stmt->execute();
    $sent_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $sent_reports = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Messages - Restaurant Management</title>
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

        .header-left h1 {
            background: #3f2a1d;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1.8rem;
            font-weight: 600;
        }

        .back-btn {
            background: #3f2a1d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .reports-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .reports-section {
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
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-header h2 {
            color: #333;
            font-size: 1.3rem;
        }

        .section-header i {
            color: #3f2a1d;
            font-size: 1.4rem;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 2px solid rgba(40, 167, 69, 0.2);
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 2px solid rgba(220, 53, 69, 0.2);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            background: white;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3f2a1d;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            background: #6d6764ff;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-sm {
            padding: 8px 15px;
            font-size: 0.9rem;
        }

        .report-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #3f2a1d;
            transition: all 0.3s ease;
        }

        .report-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .report-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .report-meta {
            font-size: 0.9rem;
            color: #666;
        }

        .report-status {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-unread {
            background: #fff3cd;
            color: #856404;
        }

        .status-read {
            background: #d4edda;
            color: #155724;
        }

        .status-replied {
            background: #cce5ff;
            color: #004085;
        }

        .priority-high {
            border-left-color: #3f2a1d;
        }

        .priority-urgent {
            border-left-color: #3f2a1d;
        }

        .priority-medium {
            border-left-color: #3f2a1d;
        }

        .priority-low {
            border-left-color: #3f2a1d;
        }

        .report-message {
            color: #555;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .report-actions {
            display: flex;
            gap: 10px;
        }

        .reply-form {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 2px solid #e1e5e9;
        }

        .no-reports {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .role-badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 500;
            margin-left: 8px;
        }

        .role-admin {
            background: #dc3545;
            color: white;
        }

        .role-chef {
            background: #fd7e14;
            color: white;
        }

        @media (max-width: 768px) {
            .reports-grid {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .report-header {
                flex-direction: column;
                gap: 10px;
            }

            .report-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <h1><i class="fas fa-comments"></i> Owner Messages</h1>
            </div>
            <div class="header-right">
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="reports-grid">
            <div class="reports-section">
                <div class="section-header">
                    <i class="fas fa-user-shield"></i>
                    <h2>Send Message to Admin</h2>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="send_report">
                    <input type="hidden" name="type" value="general">
                    
                    <div class="form-group">
                        <label for="admin_receiver_id">Select Admin *</label>
                        <select id="admin_receiver_id" name="receiver_id" required>
                            <option value="">Choose an admin...</option>
                            <?php foreach ($admins as $admin): ?>
                                <option value="<?= $admin['id'] ?>">
                                    <?= htmlspecialchars($admin['name']) ?> (Administrator)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="admin_priority">Priority</label>
                        <select id="admin_priority" name="priority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="admin_title">Message Title *</label>
                        <input type="text" id="admin_title" name="title" required 
                               placeholder="Enter message title">
                    </div>

                    <div class="form-group">
                        <label for="admin_message">Message *</label>
                        <textarea id="admin_message" name="message" required 
                                  placeholder="Enter your message to admin..."></textarea>
                    </div>

                    <button type="submit" class="btn">
                        <i class="fas fa-paper-plane"></i> Send to Admin
                    </button>
                </form>
            </div>

            <div class="reports-section">
                <div class="section-header">
                    <i class="fas fa-users"></i>
                    <h2>Send Message to Staff</h2>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="send_report">
                    
                    <div class="form-group">
                        <label for="receiver_id">Select Staff Member *</label>
                        <select id="receiver_id" name="receiver_id" required>
                            <option value="">Choose staff member...</option>
                            <?php foreach ($staff as $member): ?>
                                <option value="<?= $member['id'] ?>">
                                    <?= htmlspecialchars($member['name']) ?> (<?= ucfirst($member['role']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="title">Message Title *</label>
                        <input type="text" id="title" name="title" required 
                               placeholder="Enter message title">
                    </div>

                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" name="message" required 
                                  placeholder="Enter your message or instructions..."></textarea>
                    </div>

                    <button type="submit" class="btn">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
            </div>
        </div>

        <div class="reports-grid">
            <div class="reports-section">
                <div class="section-header">
                    <i class="fas fa-inbox"></i>
                    <h2>Messages from Staff (<?= count($received_reports) ?>)</h2>
                </div>

                <div style="max-height: 500px; overflow-y: auto;">
                    <?php if (empty($received_reports)): ?>
                        <div class="no-reports">
                            <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px; color: #ddd;"></i>
                            <h3>No Messages Received</h3>
                            <p>No messages from staff yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($received_reports as $report): ?>
                            <div class="report-item priority-<?= $report['priority'] ?>">
                                <div class="report-header">
                                    <div>
                                        <div class="report-title"><?= htmlspecialchars($report['title']) ?></div>
                                        <div class="report-meta">
                                            From: <?= htmlspecialchars($report['sender_name'] ?? 'Staff') ?>
                                            <span class="role-badge role-<?= $report['sender_role'] ?? 'staff' ?>">
                                                <?= ucfirst($report['sender_role'] ?? 'Staff') ?>
                                            </span> | 
                                            <?= date('M j, Y g:i A', strtotime($report['created_at'])) ?> |
                                            Priority: <?= ucfirst($report['priority']) ?>
                                        </div>
                                    </div>
                                    <span class="report-status status-<?= $report['status'] ?>">
                                        <?= ucfirst($report['status']) ?>
                                    </span>
                                </div>
                                
                                <div class="report-message">
                                    <?= nl2br(htmlspecialchars($report['message'])) ?>
                                </div>
                                
                                <div class="report-actions">
                                    <button class="btn btn-sm" onclick="toggleReplyForm(<?= $report['id'] ?>)">
                                        <i class="fas fa-reply"></i> Reply
                                    </button>
                                </div>
                                
                                <div class="reply-form" id="replyForm<?= $report['id'] ?>">
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="reply_report">
                                        <input type="hidden" name="original_report_id" value="<?= $report['id'] ?>">
                                        
                                        <div class="form-group">
                                            <label>Reply Message</label>
                                            <textarea name="reply_message" required 
                                                      placeholder="Enter your reply..."></textarea>
                                        </div>
                                        
                                        <div style="display: flex; gap: 10px;">
                                            <button type="submit" class="btn btn-sm">
                                                <i class="fas fa-paper-plane"></i> Send Reply
                                            </button>
                                            <button type="button" class="btn btn-sm" style="background: #6c757d;" onclick="toggleReplyForm(<?= $report['id'] ?>)">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="reports-section">
            <div class="section-header">
                <i class="fas fa-paper-plane"></i>
                <h2>Sent Messages (<?= count($sent_reports) ?>)</h2>
            </div>

            <div style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($sent_reports)): ?>
                    <div class="no-reports">
                        <i class="fas fa-paper-plane" style="font-size: 3rem; margin-bottom: 15px; color: #ddd;"></i>
                        <h3>No Messages Sent</h3>
                        <p>You haven't sent any messages yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($sent_reports as $report): ?>
                        <div class="report-item priority-<?= $report['priority'] ?>">
                            <div class="report-header">
                                <div>
                                    <div class="report-title"><?= htmlspecialchars($report['title']) ?></div>
                                    <div class="report-meta">
                                        To: <?= htmlspecialchars($report['receiver_name'] ?? 'Staff') ?>
                                        <span class="role-badge role-<?= $report['receiver_role'] ?? 'staff' ?>">
                                            <?= ucfirst($report['receiver_role'] ?? 'Staff') ?>
                                        </span> | 
                                        <?= date('M j, Y g:i A', strtotime($report['created_at'])) ?> |
                                        Priority: <?= ucfirst($report['priority']) ?>
                                    </div>
                                </div>
                                <span class="report-status status-<?= $report['status'] ?>">
                                    <?= ucfirst($report['status']) ?>
                                </span>
                            </div>
                            
                            <div class="report-message">
                                <?= nl2br(htmlspecialchars($report['message'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

<script>
function toggleReplyForm(reportId) {
    const form = document.getElementById('replyForm' + reportId);
    const allForms = document.querySelectorAll('.reply-form');

    allForms.forEach(f => {
        if (f.id !== 'replyForm' + reportId) {
            f.style.display = 'none';
        }
    });

    form.style.display = form.style.display === 'none' || form.style.display === '' ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    const sections = document.querySelectorAll('.reports-section');
    sections.forEach((section, index) => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(30px)';
        section.style.transition = `opacity 0.6s ease ${index * 0.2}s, transform 0.6s ease ${index * 0.2}s`;
        
        setTimeout(() => {
            section.style.opacity = '1';
            section.style.transform = 'translateY(0)';
        }, 100 + (index * 200));
    });
});
</script>
</body>
</html>