<?php
session_start();
require_once "../connection.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_POST['response_id']) || empty($_POST['response_id'])) {
    echo json_encode(['success' => false, 'message' => 'Response ID is required']);
    exit();
}
$response_id = (int)$_POST['response_id'];

try {

    try {
        $conn->exec("ALTER TABLE comment_replies ADD COLUMN is_read TINYINT(1) DEFAULT 0");
    } catch (Exception $e) {
    
    }

    $update_query = "
        UPDATE comment_replies cr
        JOIN comments c ON cr.comment_id = c.id
        SET cr.is_read = 1
        WHERE cr.id = ? AND c.user_id = ?
    ";
    
    $stmt = $conn->prepare($update_query);
    $result = $stmt->execute([$response_id, $user_id]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Response marked as read']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Response not found or already marked as read']);
    }
    
} catch (Exception $e) {
    error_log("Error marking response as read: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>