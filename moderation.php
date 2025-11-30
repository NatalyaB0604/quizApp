<?php
session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $quiz_id = (int) ($_POST['quiz_id'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($quiz_id > 0) {
        switch ($_POST['action']) {
            case 'approve':
                $status = 'approved';
                break;
            case 'reject':
                $status = 'rejected';
                break;
            case 'revision':
                $status = 'revision';
                break;
            case 'delete':
                $delete_stmt = $conn->prepare("DELETE FROM quizzes WHERE id = ?");
                $delete_stmt->execute([$quiz_id]);
                header("Location: moderation.php");
                exit;
            default:
                $status = 'pending';
        }

        if ($_POST['action'] !== 'delete') {
            $check_stmt = $conn->prepare("SELECT id FROM quiz_moderation WHERE quiz_id = ?");
            $check_stmt->execute([$quiz_id]);
            $exists = $check_stmt->fetch();

            if ($exists) {
                $stmt = $conn->prepare("
                    UPDATE quiz_moderation
                    SET status = ?, admin_comment = ?, moderated_by = ?, moderated_at = NOW()
                    WHERE quiz_id = ?
                ");
                $stmt->execute([$status, $comment, $_SESSION['user_id'], $quiz_id]);
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO quiz_moderation (quiz_id, status, admin_comment, moderated_by, moderated_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$quiz_id, $status, $comment, $_SESSION['user_id']]);
            }

            $update_stmt = $conn->prepare("UPDATE quizzes SET moderation_status = ? WHERE id = ?");
            $update_stmt->execute([$status, $quiz_id]);

            if ($_POST['action'] === 'revision' || $_POST['action'] === 'reject' || $_POST['action'] === 'approve') {
                $creator_stmt = $conn->prepare("
                    SELECT u.id, u.email, u.username, q.title
                    FROM quizzes q
                    JOIN users u ON q.created_by = u.id
                    WHERE q.id = ?
                ");
                $creator_stmt->execute([$quiz_id]);
                $creator = $creator_stmt->fetch(PDO::FETCH_ASSOC);

                if ($creator) {
                    $user_id = $creator['id'];
                    $user_email = $creator['email'];
                    $quiz_title = $creator['title'];

                    $status_text = '';
                    $message = '';

                    if ($_POST['action'] === 'revision') {
                        $status_text = 'отправлена на доработку';
                        $message = "Ваша викторина «{$quiz_title}» {$status_text}.\nКомментарий модератора: {$comment}";
                    } elseif ($_POST['action'] === 'reject') {
                        $status_text = 'отклонена';
                        $message = "Ваша викторина «{$quiz_title}» {$status_text}.\nКомментарий модератора: {$comment}";
                    } elseif ($_POST['action'] === 'approve') {
                        $status_text = 'одобрена';
                        $message = "Ваша викторина «{$quiz_title}» {$status_text}.\nТеперь она доступна для прохождения!";
                    }


                    $notify_stmt = $conn->prepare("
                        INSERT INTO notifications (user_id, quiz_id, message)
                        VALUES (?, ?, ?)
                    ");
                    $notify_stmt->execute([$user_id, $quiz_id, $message]);

                    if (!empty($user_email)) {
                        $subject = "Уведомление о статусе викторины";
                        $email_body = "Здравствуйте, {$creator['username']}!\n\n{$message}\n\nС уважением, Команда QuizApp.";
                        $headers = "From: no-reply@quizapp.com\r\nReply-To: no-reply@quizapp.com\r\n";

                        if (!mail($user_email, $subject, $email_body, $headers)) {
                            error_log("Failed to send email to {$user_email} for quiz_id {$quiz_id}");
                        }
                    }
                }
            }
        }
    }

    header("Location: moderation.php");
    exit;
}

$status_filter = $_GET['status'] ?? 'pending';
$allowed_statuses = ['pending', 'approved', 'rejected', 'revision'];
$status_filter = in_array($status_filter, $allowed_statuses) ? $status_filter : 'pending';

$stmt = $conn->prepare("
    SELECT q.*, u.username as author_name,
           qm.admin_comment, qm.moderated_at,
           mod_user.username as moderator_name
    FROM quizzes q
    LEFT JOIN users u ON q.created_by = u.id
    LEFT JOIN quiz_moderation qm ON q.id = qm.quiz_id
    LEFT JOIN users mod_user ON qm.moderated_by = mod_user.id
    WHERE q.moderation_status = ? AND q.public = 1
    ORDER BY q.created_at DESC
");
$stmt->execute([$status_filter]);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getQuizzesCountByStatus($conn, $status)
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM quizzes
        WHERE moderation_status = ? AND public = 1
    ");
    $stmt->execute([$status]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] ?? 0;
}

$pending_count = getQuizzesCountByStatus($conn, 'pending');
$revision_count = getQuizzesCountByStatus($conn, 'revision');
$approved_count = getQuizzesCountByStatus($conn, 'approved');
$rejected_count = getQuizzesCountByStatus($conn, 'rejected');
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Модерация викторин</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/header_footer.css">
    <link rel="stylesheet" href="assets/css/moderation.css">
    <link href="https://fonts.googleapis.com/css?family=Dela+Gothic+One:regular" rel="stylesheet" />
</head>

<body>
    <?php include 'header.php'; ?>

    <main class="main-content">
        <div class="moderation-container">
            <h1>Модерация публичных викторин</h1>

            <div class="moderation-tabs">
                <a href="?status=pending" class="<?= $status_filter === 'pending' ? 'active' : '' ?>">
                    Ожидают проверки <span class="badge"><?= $pending_count ?></span>
                </a>
                <a href="?status=revision" class="<?= $status_filter === 'revision' ? 'active' : '' ?>">
                    На доработке <span class="badge"><?= $revision_count ?></span>
                </a>
                <a href="?status=approved" class="<?= $status_filter === 'approved' ? 'active' : '' ?>">
                    Одобренные <span class="badge"><?= $approved_count ?></span>
                </a>
                <a href="?status=rejected" class="<?= $status_filter === 'rejected' ? 'active' : '' ?>">
                    Отклоненные <span class="badge"><?= $rejected_count ?></span>
                </a>
            </div>

            <div class="quizzes-list">
                <?php if (empty($quizzes)): ?>
                    <p class="no-quizzes">Нет публичных викторин для отображения</p>
                <?php else: ?>
                    <?php foreach ($quizzes as $quiz): ?>
                        <div class="quiz-item">
                            <div class="quiz-header">
                                <h3><?= htmlspecialchars($quiz['title']) ?></h3>
                                <span class="quiz-status status-<?= $quiz['moderation_status'] ?>">
                                    <?= getStatusText($quiz['moderation_status']) ?>
                                </span>
                            </div>

                            <div class="quiz-info">
                                <p><strong>Автор:</strong> <?= htmlspecialchars($quiz['author_name']) ?></p>
                                <p><strong>Создана:</strong> <?= date('d.m.Y H:i', strtotime($quiz['created_at'])) ?></p>
                                <?php if ($quiz['moderated_at']): ?>
                                    <p><strong>Модерация:</strong> <?= date('d.m.Y H:i', strtotime($quiz['moderated_at'])) ?></p>
                                    <?php if ($quiz['moderator_name']): ?>
                                        <p><strong>Модератор:</strong> <?= htmlspecialchars($quiz['moderator_name']) ?></p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <?php if ($quiz['admin_comment']): ?>
                                <div class="moderator-comment">
                                    <strong>Комментарий модератора:</strong>
                                    <p><?= htmlspecialchars($quiz['admin_comment']) ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="quiz-actions">
                                <a href="view_quiz.php?id=<?= $quiz['id'] ?>" class="btn btn-view" target="_blank">
                                    Просмотр
                                </a>

                                <?php if ($quiz['moderation_status'] === 'pending' || $quiz['moderation_status'] === 'revision'): ?>
                                    <button class="btn btn-approve" onclick="approveQuiz(<?= $quiz['id'] ?>)">
                                        Одобрить
                                    </button>
                                    <button class="btn btn-revision" onclick="showModerationForm(<?= $quiz['id'] ?>, 'revision')">
                                        Доработка
                                    </button>
                                    <button class="btn btn-reject" onclick="showModerationForm(<?= $quiz['id'] ?>, 'reject')">
                                        Отклонить
                                    </button>
                                <?php endif; ?>

                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <form action="moderation.php" method="POST" class="delete-form">
                                        <input type="hidden" name="quiz_id" value="<?= $quiz['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-delete" onclick="return confirm('Удалить викторину?')">
                                            Удалить
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <div id="moderation-form-<?= $quiz['id'] ?>" class="moderation-form" style="display: none;">
                                <form action="moderation.php" method="POST">
                                    <input type="hidden" name="quiz_id" value="<?= $quiz['id'] ?>">
                                    <input type="hidden" name="action" value="" id="action-<?= $quiz['id'] ?>">

                                    <div id="revision-templates-<?= $quiz['id'] ?>" class="template-comments"
                                        style="display: none;">
                                        <div class="form-group">
                                            <label>Шаблоны комментариев для доработки:</label>
                                            <div class="custom-select-wrapper">
                                                <div class="custom-select" onclick="toggleTemplateSelect(this, event)">
                                                    <div class="select-selected">Выберите шаблон...</div>
                                                    <div class="select-items">
                                                        <div class="select-item" data-value=""
                                                            onclick="selectTemplateOption(this, 'revision', <?= $quiz['id'] ?>, event)">
                                                            Выберите шаблон...</div>
                                                        <div class="select-item"
                                                            data-value="Несоответствие правилам оформления викторин"
                                                            onclick="selectTemplateOption(this, 'revision', <?= $quiz['id'] ?>, event)">
                                                            Несоответствие правилам оформления</div>
                                                        <div class="select-item" data-value="Требуется добавить больше вопросов"
                                                            onclick="selectTemplateOption(this, 'revision', <?= $quiz['id'] ?>, event)">
                                                            Мало вопросов</div>
                                                        <div class="select-item" data-value="Некорректные формулировки вопросов"
                                                            onclick="selectTemplateOption(this, 'revision', <?= $quiz['id'] ?>, event)">
                                                            Некорректные формулировки</div>
                                                        <div class="select-item" data-value="Отсутствуют правильные ответы"
                                                            onclick="selectTemplateOption(this, 'revision', <?= $quiz['id'] ?>, event)">
                                                            Отсутствуют правильные ответы</div>
                                                        <div class="select-item" data-value="Требуется проверка содержания"
                                                            onclick="selectTemplateOption(this, 'revision', <?= $quiz['id'] ?>, event)">
                                                            Проверка содержания</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="reject-templates-<?= $quiz['id'] ?>" class="template-comments"
                                        style="display: none;">
                                        <div class="form-group">
                                            <label>Шаблоны комментариев для отклонения:</label>
                                            <div class="custom-select-wrapper">
                                                <div class="custom-select" onclick="toggleTemplateSelect(this, event)">
                                                    <div class="select-selected">Выберите шаблон...</div>
                                                    <div class="select-items">
                                                        <div class="select-item" data-value=""
                                                            onclick="selectTemplateOption(this, 'reject', <?= $quiz['id'] ?>, event)">
                                                            Выберите шаблон...</div>
                                                        <div class="select-item" data-value="Нарушение правил платформы"
                                                            onclick="selectTemplateOption(this, 'reject', <?= $quiz['id'] ?>, event)">
                                                            Нарушение правил платформы</div>
                                                        <div class="select-item" data-value="Низкое качество содержания"
                                                            onclick="selectTemplateOption(this, 'reject', <?= $quiz['id'] ?>, event)">
                                                            Низкое качество содержания</div>
                                                        <div class="select-item" data-value="Плагиат контента"
                                                            onclick="selectTemplateOption(this, 'reject', <?= $quiz['id'] ?>, event)">
                                                            Плагиат контента</div>
                                                        <div class="select-item" data-value="Несоответствие тематике"
                                                            onclick="selectTemplateOption(this, 'reject', <?= $quiz['id'] ?>, event)">
                                                            Несоответствие тематике</div>
                                                        <div class="select-item" data-value="Повторная публикация"
                                                            onclick="selectTemplateOption(this, 'reject', <?= $quiz['id'] ?>, event)">
                                                            Повторная публикация</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="comment-<?= $quiz['id'] ?>">Комментарий</label>
                                        <textarea id="comment-<?= $quiz['id'] ?>" name="comment" rows="4"
                                            placeholder="Необязательно"></textarea>
                                    </div>

                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-confirm">Подтвердить</button>
                                        <button type="button" class="btn btn-cancel"
                                            onclick="hideModerationForm(<?= $quiz['id'] ?>)">Отмена</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function approveQuiz(quizId) {
            if (confirm('Вы уверены, что хотите одобрить эту викторину?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'moderation.php';

                const quizIdInput = document.createElement('input');
                quizIdInput.type = 'hidden';
                quizIdInput.name = 'quiz_id';
                quizIdInput.value = quizId;

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'approve';

                form.appendChild(quizIdInput);
                form.appendChild(actionInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function applyTemplate(quizId, templateText, actionType) {
            const commentTextarea = document.getElementById('comment-' + quizId);
            if (templateText) {
                commentTextarea.value = templateText;
            }
        }

        function showModerationForm(quizId, action) {
            document.querySelectorAll('.moderation-form').forEach(form => {
                form.style.display = 'none';
            });

            const form = document.getElementById('moderation-form-' + quizId);
            const actionInput = document.getElementById('action-' + quizId);
            const commentTextarea = document.getElementById('comment-' + quizId);

            commentTextarea.value = '';

            const revisionCustomSelect = form.querySelector('#revision-templates-' + quizId + ' .custom-select');
            const rejectCustomSelect = form.querySelector('#reject-templates-' + quizId + ' .custom-select');

            if (revisionCustomSelect) {
                revisionCustomSelect.querySelector('.select-selected').textContent = 'Выберите шаблон...';
                revisionCustomSelect.classList.remove('select-active');
            }
            if (rejectCustomSelect) {
                rejectCustomSelect.querySelector('.select-selected').textContent = 'Выберите шаблон...';
                rejectCustomSelect.classList.remove('select-active');
            }

            document.getElementById('revision-templates-' + quizId).style.display = 'none';
            document.getElementById('reject-templates-' + quizId).style.display = 'none';

            actionInput.value = action;
            form.style.display = 'block';

            if (action === 'revision') {
                commentTextarea.placeholder = 'Укажите, что нужно исправить в викторине';
                commentTextarea.required = true;
                document.getElementById('revision-templates-' + quizId).style.display = 'block';

            } else if (action === 'reject') {
                commentTextarea.placeholder = 'Укажите причину отклонения викторины';
                commentTextarea.required = true;
                document.getElementById('reject-templates-' + quizId).style.display = 'block';
            }
        }

        function hideModerationForm(quizId) {
            const form = document.getElementById('moderation-form-' + quizId);
            form.style.display = 'none';

            const commentTextarea = document.getElementById('comment-' + quizId);
            commentTextarea.value = '';

            const revisionCustomSelect = document.querySelector('#revision-templates-' + quizId + ' .custom-select');
            const rejectCustomSelect = document.querySelector('#reject-templates-' + quizId + ' .custom-select');

            if (revisionCustomSelect) {
                revisionCustomSelect.querySelector('.select-selected').textContent = 'Выберите шаблон...';
                revisionCustomSelect.classList.remove('select-active');
            }
            if (rejectCustomSelect) {
                rejectCustomSelect.querySelector('.select-selected').textContent = 'Выберите шаблон...';
                rejectCustomSelect.classList.remove('select-active');
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.moderation-form form').forEach(form => {
                form.addEventListener('submit', function (e) {
                    const actionInput = this.querySelector('input[name="action"]');
                    const commentTextarea = this.querySelector('textarea[name="comment"]');

                    if ((actionInput.value === 'revision' || actionInput.value === 'reject') &&
                        !commentTextarea.value.trim()) {
                        e.preventDefault();
                        alert('Для действий "Доработка" и "Отклонить" комментарий обязателен!');
                        commentTextarea.focus();
                    }
                });
            });
        });

        function toggleTemplateSelect(element, event) {
            if (event) {
                event.stopPropagation();
            }

            document.querySelectorAll('.custom-select').forEach(select => {
                if (select !== element) {
                    select.classList.remove('select-active');
                }
            });

            element.classList.toggle('select-active');
        }

        function selectTemplateOption(element, actionType, quizId, event) {
            if (event) {
                event.stopPropagation();
            }

            const templateText = element.getAttribute('data-value');
            const customSelect = element.closest('.custom-select');
            const selectedElement = customSelect.querySelector('.select-selected');

            selectedElement.textContent = element.textContent;
            customSelect.classList.remove('select-active');

            if (templateText) {
                applyTemplate(quizId, templateText, actionType);
            }
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.custom-select')) {
                document.querySelectorAll('.custom-select').forEach(select => {
                    select.classList.remove('select-active');
                });
            }
        });
    </script>

    <?php include 'footer.php'; ?>
</body>

</html>

<?php
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
