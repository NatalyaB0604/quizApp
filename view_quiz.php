<?php
session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$quiz_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $conn->prepare("
    SELECT q.*, u.username as author_name
    FROM quizzes q
    LEFT JOIN users u ON q.created_by = u.id
    WHERE q.id = ?
");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header("Location: moderation.php");
    exit;
}

$q_stmt = $conn->prepare("
    SELECT id, type, question_text, position
    FROM questions
    WHERE quiz_id = ?
    ORDER BY position ASC
");
$q_stmt->execute([$quiz_id]);
$questions = $q_stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($questions as &$question) {
    $a_stmt = $conn->prepare("
        SELECT answer_text, is_correct
        FROM answers
        WHERE question_id = ?
    ");
    $a_stmt->execute([$question['id']]);
    $question['answers'] = $a_stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($question);

function getStatusText($status) {
    $statuses = [
        'pending' => 'Ожидает проверки',
        'approved' => 'Одобрена',
        'rejected' => 'Отклонена',
        'revision' => 'На доработке'
    ];
    return $statuses[$status] ?? $status;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Просмотр: <?= htmlspecialchars($quiz['title']) ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/header_footer.css">
    <link rel="stylesheet" href="assets/css/view_quiz.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="main-content">
        <div class="quiz-view-container">
            <div class="quiz-header">
                <h1><?= htmlspecialchars($quiz['title']) ?></h1>
                <p class="quiz-description"><?= htmlspecialchars($quiz['description']) ?></p>
                <div class="quiz-meta">
                    <span><strong>Автор:</strong> <?= htmlspecialchars($quiz['author_name']) ?></span>
                    <span><strong>Время:</strong> <?= $quiz['total_time'] ?> мин.</span>
                    <span><strong>Вопросов:</strong> <?= count($questions) ?></span>
                </div>
            </div>

            <div class="questions-list">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-item">
                        <h3>Вопрос <?= $index + 1 ?>: <?= htmlspecialchars($question['question_text']) ?></h3>
                        <div class="answers-list">
                            <?php foreach ($question['answers'] as $answer): ?>
                                <div class="answer-item <?= $answer['is_correct'] ? 'correct' : '' ?>">
                                    <?= htmlspecialchars($answer['answer_text']) ?>
                                    <?php if ($answer['is_correct']): ?>
                                        <span class="correct-badge">✓ Правильный ответ</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="view-actions">
                <a href="moderation.php" class="btn-back">← Назад к модерации</a>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>
