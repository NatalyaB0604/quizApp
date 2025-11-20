<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$db = new Database();
$conn = $db->getConnection();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    header('Content-Type: application/json');
    $quizId = (int)$_POST['id'];
    try {
        $checkStmt = $conn->prepare("SELECT id FROM quizzes WHERE id = ? AND created_by = ?");
        $checkStmt->execute([$quizId, $_SESSION['user_id']]);
        if ($checkStmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'error' => 'Викторина не найдена']);
            exit;
        }

        $deleteStmt = $conn->prepare("DELETE FROM quizzes WHERE id = ?");
        $deleteStmt->execute([$quizId]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
    }
    exit;
}

$stmt = $conn->prepare("
    SELECT id, title, description, access_code, created_at, start_date, auto_start, public
    FROM quizzes
    WHERE created_by = ?
    ORDER BY
        public ASC,
        CASE
            WHEN public = 0 AND start_date IS NOT NULL THEN start_date
            ELSE '1000-01-01'
        END DESC
");

$stmt->execute([$_SESSION['user_id']]);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Мои викторины</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/header_footer.css">
    <link rel="stylesheet" href="assets/css/my_quizzes.css">
    <link href="https://fonts.googleapis.com/css?family=Dela+Gothic+One:regular" rel="stylesheet" />
</head>
<body>
    <?php include 'header.php'; ?>
    <main class="my-quizzes-container">
        <div class="quizzes-header">
            <h2 class="quizzes-title">Мои викторины (<?php echo count($quizzes); ?>)</h2>
            <p class="quizzes-subtitle">Выберите викторину для запуска, редактирования, и управления.</p>
        </div>
        <section class="create-quiz-section">
            <a href="create_quiz.php" class="create-quiz-btn">
                <span>+ Создать викторину</span>
            </a>
        </section>
        <?php if (empty($quizzes)): ?>
            <div class="empty-state">
                <p>У вас пока нет созданных викторин</p>
            </div>
        <?php else: ?>
            <section class="quizzes-list-section">
                <div class="quizzes-list">
                    <?php
                    $months = [
                        1 => 'Янв.', 2 => 'Фев.', 3 => 'Мар.', 4 => 'Апр.',
                        5 => 'Мая', 6 => 'Июн.', 7 => 'Июл.', 8 => 'Авг.',
                        9 => 'Сен.', 10 => 'Окт.', 11 => 'Нояб.', 12 => 'Дек.'
                    ];
                    foreach ($quizzes as $quiz):
                        if ($quiz['public'] == 1) {
                            $date_str = 'Публичная';
                            $time_str = '';
                            $is_public = true;
                        } else {
                            $is_public = false;
                            if (!empty($quiz['start_date'])) {
                                $date_str = date('d', strtotime($quiz['start_date'])) . ' ' .
                                            $months[date('n', strtotime($quiz['start_date']))] . ' ' .
                                            date('Y', strtotime($quiz['start_date']));
                                $time_str = date('H:i', strtotime($quiz['start_date']));
                            } else {
                                $date_str = 'Не указана дата запуска';
                                $time_str = '';
                            }
                        }
                    ?>
                        <div class="quiz-item <?php echo $is_public ? 'public-quiz' : ''; ?>">
                            <div class="quiz-start-info">
                                <?php if ($is_public): ?>
                                    <span class="public-badge">Публичная</span>
                                <?php else: ?>
                                    <span class="quiz-date"><?php echo $date_str; ?></span>
                                    <?php if ($time_str): ?>
                                        <span class="vertical-bar">|</span>
                                        <span class="quiz-time"><?php echo $time_str; ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <?php if (!$is_public): ?>
                                <span class="access-code"><?php echo htmlspecialchars($quiz['access_code']); ?></span>
                            <?php endif; ?>

                            <h3 class="quiz-title"><?php echo htmlspecialchars($quiz['title']); ?></h3>

                            <a href="start_quiz.php?id=<?php echo $quiz['id']; ?>" class="launch-btn">
                                <span>Запустить</span>
                            </a>

                            <div class="quiz-actions">
                                <a href="edit_quiz.php?id=<?php echo $quiz['id']; ?>" class="action-btn edit-btn">
                                    <img src="assets/images/edit.svg" alt="Редактировать">
                                </a>
                                <button class="action-btn delete-btn" onclick="deleteQuiz(<?php echo $quiz['id']; ?>)">
                                    <img src="assets/images/trash.svg" alt="Удалить">
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
    <script>
        function deleteQuiz(quizId) {
            if (confirm('Вы уверены, что хотите удалить эту викторину?')) {
                fetch('my_quizzes.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'delete',
                        id: quizId
                    })
                }).then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                }).then(result => {
                    if (result.success) {
                        location.reload();
                    } else {
                        alert('Ошибка при удалении: ' + (result.error || 'Неизвестная ошибка'));
                    }
                }).catch(error => {
                    alert('Ошибка: ' + error.message);
                });
            }
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>
