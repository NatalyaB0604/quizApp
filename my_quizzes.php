<?php
session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_stats' && isset($_POST['id'])) {
    header('Content-Type: application/json');
    $quizId = (int) $_POST['id'];
    try {
        $checkStmt = $conn->prepare("SELECT id FROM quizzes WHERE id = ? AND created_by = ?");
        $checkStmt->execute([$quizId, $_SESSION['user_id']]);
        if ($checkStmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'error' => 'Викторина не найдена']);
            exit;
        }

        $statsStmt = $conn->prepare("
            SELECT u.username, r.score, r.completed_at, r.time_spent
            FROM results r
            JOIN users u ON r.user_id = u.id
            WHERE r.quiz_id = ?
            ORDER BY r.score DESC, r.time_spent ASC
        ");
        $statsStmt->execute([$quizId]);
        $stats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_stats' && isset($_POST['id'])) {
    header('Content-Type: application/json');
    $quizId = (int) $_POST['id'];
    try {
        $checkStmt = $conn->prepare("SELECT id FROM quizzes WHERE id = ? AND created_by = ?");
        $checkStmt->execute([$quizId, $_SESSION['user_id']]);
        if ($checkStmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'error' => 'Викторина не найдена']);
            exit;
        }

        $deleteStmt = $conn->prepare("DELETE FROM results WHERE quiz_id = ?");
        $deleteStmt->execute([$quizId]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
    }
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    header('Content-Type: application/json');
    $quizId = (int) $_POST['id'];
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
    SELECT id, title, description, access_code, created_at, start_date, public, moderation_status
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

function getStatusText($status)
{
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
                        1 => 'Янв.',
                        2 => 'Фев.',
                        3 => 'Мар.',
                        4 => 'Апр.',
                        5 => 'Мая',
                        6 => 'Июн.',
                        7 => 'Июл.',
                        8 => 'Авг.',
                        9 => 'Сен.',
                        10 => 'Окт.',
                        11 => 'Нояб.',
                        12 => 'Дек.'
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

                        // Определяем, доступна ли кнопка "Играть"
                        $can_play = false;
                        if (!$is_public) {
                            // Приватные викторины всегда доступны
                            $can_play = true;
                        } else {
                            // Публичные викторины доступны только если одобрены
                            $can_play = ($quiz['moderation_status'] === 'approved');
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
                            <div class="quiz-buttons-container">
                                <?php if ($can_play): ?>
                                    <a href="start_quiz.php?code=<?php echo urlencode($quiz['access_code']); ?>" class="launch-btn">
                                        <span>Играть</span>
                                    </a>
                                <?php else: ?>
                                    <?php if ($is_public && $quiz['moderation_status']): ?>
                                        <span class="moderation-status status-<?php echo $quiz['moderation_status']; ?>">
                                            <?php echo getStatusText($quiz['moderation_status']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="disabled-launch-btn">
                                            Недоступно
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <div class="quiz-actions  <?php echo $is_public ? 'public-quiz' : ''; ?>">
                                    <?php if (!$is_public): ?>
                                        <button class="action-btn stats-btn" onclick="showStats(<?php echo $quiz['id']; ?>)">
                                            <img src="assets/images/stats.svg" alt="Статистика">
                                        </button>
                                    <?php endif; ?>
                                    <a href="edit_quiz.php?id=<?php echo $quiz['id']; ?>" class="action-btn edit-btn">
                                        <img src="assets/images/edit.svg" alt="Редактировать">
                                    </a>
                                    <button class="action-btn delete-btn" onclick="deleteQuiz(<?php echo $quiz['id']; ?>)">
                                        <img src="assets/images/trash.svg" alt="Удалить">
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <div id="stats-modal" class="modal">
        <div class="modal-content">
            <h2>Статистика викторины</h2>
            <div id="stats-content">
                <p>Загрузка...</p>
            </div>
            <div class="modal-actions">
                <button id="clear-stats-btn" class="clear-btn">Очистить статистику</button>
                <button id="close-stats-btn" class="close-btn">Закрыть</button>
            </div>
        </div>
    </div>

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

        function showStats(quizId) {
            currentQuizId = quizId;
            const modal = document.getElementById('stats-modal');
            modal.style.display = 'flex';

            loadStats(quizId);
        }

        function loadStats(quizId) {
            fetch('my_quizzes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'get_stats',
                    id: quizId
                })
            })
                .then(response => response.json())
                .then(result => {
                    const statsContent = document.getElementById('stats-content');
                    if (result.success) {
                        if (result.stats.length > 0) {
                            let html = '<table class="stats-table">';
                            html += '<tr><th>Участник</th><th>Баллы</th><th>Время прохождения</th><th>Дата прохождения</th></tr>';
                            result.stats.forEach(stat => {
                                const date = new Date(stat.completed_at);
                                const dateStr = date.toLocaleDateString('ru-RU') + ' ' + date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
                                const timeSpentFormatted = formatTimeSpent(stat.time_spent);

                                html += `<tr>
                                <td>${stat.username}</td>
                                <td>${stat.score}</td>
                                <td>${timeSpentFormatted}</td>
                                <td>${dateStr}</td>
                            </tr>`;
                            });
                            html += '</table>';
                            statsContent.innerHTML = html;
                        } else {
                            statsContent.innerHTML = '<p>Пока нет результатов</p>';
                        }
                    } else {
                        statsContent.innerHTML = '<p>Ошибка загрузки статистики: ' + (result.error || 'Неизвестная ошибка') + '</p>';
                    }
                })
                .catch(error => {
                    document.getElementById('stats-content').innerHTML = '<p>Ошибка загрузки статистики: ' + error.message + '</p>';
                });
        }

        function formatTimeSpent(seconds) {
            if (!seconds || seconds === 0) return '-';

            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;

            if (minutes === 0) {
                return `${secs} сек`;
            } else {
                return `${minutes} мин ${secs} сек`;
            }
        }

        document.getElementById('clear-stats-btn').onclick = function () {
            if (confirm('Вы уверены, что хотите очистить всю статистику для этой викторины? Это действие нельзя отменить.')) {
                fetch('my_quizzes.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'clear_stats',
                        id: currentQuizId
                    })
                })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            loadStats(currentQuizId);
                        } else {
                            alert('Ошибка при очистке статистики: ' + (result.error || 'Неизвестная ошибка'));
                        }
                    })
                    .catch(error => {
                        alert('Ошибка: ' + error.message);
                    });
            }
        }

        document.getElementById('close-stats-btn').onclick = function () {
            document.getElementById('stats-modal').style.display = 'none';
        }

        window.onclick = function (event) {
            const modal = document.getElementById('stats-modal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
    <?php include 'footer.php'; ?>
</body>

</html>
