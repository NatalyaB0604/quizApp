<?php
session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] === 'admin') {
    header("Location: index.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'delete_all') {
        try {
            $deleteStmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
            $deleteStmt->execute([$_SESSION['user_id']]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'delete_single' && isset($_POST['id'])) {
        try {
            $notificationId = (int) $_POST['id'];
            $deleteStmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            $deleteStmt->execute([$notificationId, $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
        }
        exit;
    }
}

$mark_read_stmt = $conn->prepare("UPDATE notifications SET status = 'read' WHERE user_id = ? AND status = 'unread'");
$mark_read_stmt->execute([$_SESSION['user_id']]);

$notify_stmt = $conn->prepare("
    SELECT id, message, created_at, status
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$notify_stmt->execute([$_SESSION['user_id']]);
$notifications = $notify_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Уведомления</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/header_footer.css">
    <link rel="stylesheet" href="assets/css/notifications.css">
    <link href="https://fonts.googleapis.com/css?family=Dela+Gothic+One:regular" rel="stylesheet" />
    <style>

    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <main class="notifications-container">
        <div class="notifications-header">
            <h2 class="notifications-title">Уведомления (<?php echo count($notifications); ?>)</h2>
            <p class="notifications-subtitle">Здесь отображаются все ваши уведомления.</p>
        </div>

        <?php if (!empty($notifications)): ?>
            <section class="delete-all-section">
                <button class="delete-all-btn" onclick="deleteAllNotifications()">
                    Удалить все уведомления
                </button>
            </section>

            <section class="notifications-list-section">
                <div class="notifications-list">
                    <?php foreach ($notifications as $notify): ?>
                        <div class="notification-item" data-notification-id="<?php echo $notify['id']; ?>">
                            <div class="notification-content">
                                <p class="notification-message"><?php echo htmlspecialchars($notify['message']); ?></p>
                                <span class="notification-date">
                                    <?php echo date('d.m.Y H:i', strtotime($notify['created_at'])); ?>
                                </span>
                            </div>
                            <div class="notification-actions">
                                <button class="action-btn delete-btn"
                                    onclick="deleteNotification(<?php echo $notify['id']; ?>)">
                                    <img src="assets/images/trash.svg" alt="Удалить">
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php else: ?>
            <div class="empty-state">
                <p>У вас пока нет уведомлений</p>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function deleteNotification(notificationId) {
            if (confirm('Вы уверены, что хотите удалить это уведомление?')) {
                fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'delete_single',
                        id: notificationId
                    })
                })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            const notificationElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
                            if (notificationElement) {
                                notificationElement.remove();
                                updateNotificationCount();

                                // Проверяем, остались ли еще уведомления
                                const remainingNotifications = document.querySelectorAll('.notification-item');
                                if (remainingNotifications.length === 0) {
                                    removeAllNotificationSections();
                                }
                            }
                        } else {
                            alert('Ошибка при удалении уведомления: ' + (result.error || 'Неизвестная ошибка'));
                        }
                    })
                    .catch(error => {
                        alert('Ошибка: ' + error.message);
                    });
            }
        }

        function deleteAllNotifications() {
            if (confirm('Вы уверены, что хотите удалить все уведомления? Это действие нельзя отменить.')) {
                fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'delete_all'
                    })
                })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            removeAllNotificationSections();
                        } else {
                            alert('Ошибка при удалении уведомлений: ' + (result.error || 'Неизвестная ошибка'));
                        }
                    })
                    .catch(error => {
                        alert('Ошибка: ' + error.message);
                    });
            }
        }

        function removeAllNotificationSections() {
            // Удаляем секцию с кнопкой удаления всех
            const deleteAllSection = document.querySelector('.delete-all-section');
            if (deleteAllSection) {
                deleteAllSection.remove();
            }

            // Удаляем всю секцию со списком уведомлений
            const listSection = document.querySelector('.notifications-list-section');
            if (listSection) {
                listSection.remove();
            }

            updateNotificationCount();

            // Создаем и добавляем блок с пустым состоянием
            const emptyState = document.createElement('div');
            emptyState.className = 'empty-state';
            emptyState.innerHTML = '<p>У вас пока нет уведомлений</p>';
            document.querySelector('main').appendChild(emptyState);
        }

        function updateNotificationCount() {
            const notifications = document.querySelectorAll('.notification-item');
            const title = document.querySelector('.notifications-title');
            if (title) {
                title.textContent = `Уведомления (${notifications.length})`;
            }
        }
    </script>

    <?php include 'footer.php'; ?>
</body>

</html>
