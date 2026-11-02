<?php
session_start();
require_once "../connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_name = $_SESSION['name'] ?? 'User';
$user_id = $_SESSION['user_id'];

if (isset($_POST['mark_read']) && isset($_POST['response_id'])) {
    $response_id = $_POST['response_id'];

    try {
        $conn->exec("ALTER TABLE comment_replies ADD COLUMN is_read TINYINT(1) DEFAULT 0");
    } catch (Exception $e) {

    }
    
    $update_query = "UPDATE comment_replies SET is_read = 1 WHERE id = ? AND comment_id IN (SELECT id FROM comments WHERE user_id = ?)";
    $stmt = $conn->prepare($update_query);
    $stmt->execute([$response_id, $user_id]);
    
    $success_message = "Response marked as read!";
}

if (isset($_POST['mark_all_read'])) {

    try {
        $conn->exec("ALTER TABLE comment_replies ADD COLUMN is_read TINYINT(1) DEFAULT 0");
    } catch (Exception $e) {

    }
    
    $update_query = "UPDATE comment_replies cr 
                     JOIN comments c ON cr.comment_id = c.id 
                     SET cr.is_read = 1 
                     WHERE c.user_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->execute([$user_id]);
    $success_message = "All responses marked as read!";
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$count_query = "
    SELECT COUNT(*) FROM comment_replies cr
    JOIN comments c ON cr.comment_id = c.id
    WHERE c.user_id = ?
";
$stmt = $conn->prepare($count_query);
$stmt->execute([$user_id]);
$total_responses = $stmt->fetchColumn();
$total_pages = ceil($total_responses / $per_page);

$unread_query = "
    SELECT COUNT(*) FROM comment_replies cr
    JOIN comments c ON cr.comment_id = c.id
    WHERE c.user_id = ? AND COALESCE(cr.is_read, 0) = 0
";
$stmt = $conn->prepare($unread_query);
$stmt->execute([$user_id]);
$unread_responses = $stmt->fetchColumn();

$responses_query = "
    SELECT cr.*, c.message as original_comment, c.rating, c.created_at as comment_date,
           COALESCE(cr.is_read, 0) as is_read
    FROM comment_replies cr
    JOIN comments c ON cr.comment_id = c.id
    WHERE c.user_id = ?
    ORDER BY cr.created_at DESC
    LIMIT " . (int)$per_page . " OFFSET " . (int)$offset . "
";
$stmt = $conn->prepare($responses_query);
$stmt->execute([$user_id]);
$responses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Responses - Restaurant</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(to right, #f3fafaff 0%, #5ab7d1ff);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .header h1 {
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #1d4c55ff;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-btn:hover {
            background: #197ba5ff;
            transform: translateY(-2px);
        }
        
        .stats {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(45deg, #1d4c55ff, #197ba5ff);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            flex: 1;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .response-item {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border-left: 4px solid #10b981;
            transition: all 0.3s ease;
        }
        
        .response-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .response-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .admin-badge {
            background: linear-gradient(45deg, #10b981, #34d399);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .response-date {
            color: #666;
            font-size: 0.9rem;
        }
        
        .original-comment {
            background: rgba(16, 185, 129, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 3px solid #10b981;
        }
        
        .comment-label {
            color: #666;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .comment-text {
            color: #333;
            line-height: 1.5;
            margin-bottom: 10px;
        }
        
        .comment-rating {
            display: flex;
            gap: 2px;
        }
        
        .comment-rating .star {
            color: #fbbf24;
            font-size: 1rem;
        }
        
        .comment-rating .star.empty {
            color: #d1d5db;
        }
        
        .admin-response {
            background: white;
            border-radius: 10px;
            padding: 15px;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .response-text {
            color: #333;
            line-height: 1.6;
            font-size: 1rem;
        }
        
        .mark-read-btn {
            background: linear-gradient(45deg, #10b981, #34d399);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .mark-read-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .mark-all-read-btn {
            background: linear-gradient(45deg, #f59e0b, #fbbf24);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 auto;
        }
        
        .mark-all-read-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.3);
        }
        
        .response-item.unread {
            border-left: 4px solid #f59e0b;
            background: linear-gradient(90deg, rgba(245, 158, 11, 0.1), #f8f9fa);
        }
        
        .unread-badge {
            background: linear-gradient(45deg, #f59e0b, #fbbf24);
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            animation: pulse 2s infinite;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .pagination a, .pagination span {
            padding: 10px 15px;
            border-radius: 10px;
            text-decoration: none;
            transition: 0.3s;
        }
        
        .pagination a {
            background: #f8f9fa;
            color: #333;
        }
        
        .pagination a:hover {
            background: #1d4c55ff;
            color: white;
        }
        
        .pagination .current {
            background: linear-gradient(45deg, #1d4c55ff, #197ba5ff);
            color: white;
        }
        
        .no-responses {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .no-responses i {
            font-size: 4rem;
            color: #d1d5db;
            margin-bottom: 20px;
        }
        
        .no-responses h3 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .write-review-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 25px;
            background: linear-gradient(45deg, #10b981, #34d399);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            transition: 0.3s;
        }
        
        .write-review-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
                margin: 10px;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .stats {
                flex-direction: column;
            }
            
            .response-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-reply-all"></i>
                Admin Responses
            </h1>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert">
                <i class="fas fa-check-circle"></i>
                <?= $success_message ?>
            </div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?= $total_responses ?></div>
                <div class="stat-label">Total Responses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $unread_responses ?></div>
                <div class="stat-label">Unread Responses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($responses) ?></div>
                <div class="stat-label">This Page</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_pages ?></div>
                <div class="stat-label">Total Pages</div>
            </div>
        </div>
        
        <?php if ($unread_responses > 0): ?>
            <div style="text-align: center; margin-bottom: 20px;">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="mark_all_read" class="mark-all-read-btn">
                        <i class="fas fa-check-double"></i> Mark All as Read (<?= $unread_responses ?>)
                    </button>
                </form>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($responses)): ?>
            <?php foreach ($responses as $response): ?>
                <div class="response-item <?= !$response['is_read'] ? 'unread' : '' ?>">
                    <div class="response-header">
                        <div class="admin-info">
                            <i class="fas fa-user-shield" style="color: #10b981; font-size: 1.2rem;"></i>
                            <strong><?= htmlspecialchars($response['admin_name']) ?></strong>
                            <span class="admin-badge">Restaurant Staff</span>
                            <?php if (!$response['is_read']): ?>
                                <span class="unread-badge">New</span>
                            <?php endif; ?>
                        </div>
                        <div class="response-date">
                            <i class="fas fa-clock"></i>
                            <?= date('M j, Y - g:i A', strtotime($response['created_at'])) ?>
                            <?php if (!$response['is_read']): ?>
                                <form method="POST" style="display: inline; margin-left: 10px;">
                                    <input type="hidden" name="response_id" value="<?= $response['id'] ?>">
                                    <button type="submit" name="mark_read" class="mark-read-btn">
                                        <i class="fas fa-check"></i> Mark Read
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="original-comment">
                        <div class="comment-label">Your Original Review:</div>
                        <div class="comment-text">
                            <?= htmlspecialchars($response['original_comment']) ?>
                        </div>
                        <div class="comment-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star star <?= $i <= $response['rating'] ? '' : 'empty' ?>"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="admin-response">
                        <div class="response-text">
                            <?= nl2br(htmlspecialchars($response['reply_message'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="no-responses">
                <i class="fas fa-inbox"></i>
                <h3>No Admin Responses Yet</h3>
                <p>You haven't received any responses from our team yet. Write a review to get personalized feedback!</p>
                <a href="write_comment.php" class="write-review-btn">
                    <i class="fas fa-star"></i> Write Your First Review
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>