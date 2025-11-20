<?php
session_start();
require_once __DIR__ . '/config.php';

$db = new Database();
$conn = $db->getConnection();

if (!isset($_GET['quiz'])) {
    http_response_code(400);
    echo "quiz param missing";
    exit;
}

$quiz_id = (int)$_GET['quiz'];
$session_id = session_id();
$user_id = $_SESSION['user_id'] ?? null;

// Получаем информацию о викторине
$quiz_stmt = $conn->prepare("SELECT public FROM quizzes WHERE id = ?");
$quiz_stmt->execute([$quiz_id]);
$quiz = $quiz_stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    http_response_code(404);
    echo "Quiz not found";
    exit;
}

try {
    $stmt = $conn->prepare("
        INSERT INTO online_users (quiz_id, user_id, session_id, last_seen)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), last_seen = NOW()
    ");
    $stmt->execute([$quiz_id, $user_id, $session_id]);

    if (!$quiz['public']) {
        $list = $conn->prepare("
            SELECT ou.user_id, ou.session_id, u.username
            FROM online_users ou
            LEFT JOIN users u ON u.id = ou.user_id
            WHERE ou.quiz_id = ? AND ou.last_seen > (NOW() - INTERVAL 15 SECOND)
            ORDER BY ou.last_seen DESC
        ");
        $list->execute([$quiz_id]);
        $rows = $list->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            echo "<h4>Подключенные пользователи:</h4>";
            foreach ($rows as $r) {
                $name = $r['username'] ?? ('Гость (' . htmlspecialchars(substr($r['session_id'],0,6)) . ')');
                echo "<div class='online-user-item'>" . htmlspecialchars($name) . "</div>";
            }
        } else {
            echo "<div class='online-user-item'>Нет подключенных пользователей</div>";
        }
    } else {
        echo "<div class='online-info'>Публичная викторина - онлайн пользователи скрыты</div>";
    }
} catch (Exception $e) {
    http_response_code(500);
    echo "err";
}
