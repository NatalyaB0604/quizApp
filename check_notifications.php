<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND status = 'unread'");
$count_stmt->execute([$_SESSION['user_id']]);
$count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$new_stmt = $conn->prepare("
    SELECT id, message
    FROM notifications
    WHERE user_id = ? AND status = 'unread'
    ORDER BY created_at DESC
");
$new_stmt->execute([$_SESSION['user_id']]);
$new_notifications = $new_stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'count' => $count,
    'new_notifications' => $new_notifications
]);
?>
