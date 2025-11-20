<?php
session_start();
require_once __DIR__ . '/config.php';
$db = new Database();
$conn = $db->getConnection();

if (!isset($_GET['quiz_id'])) {
    header('Location:index.php');
    exit;
}

$quiz_id = (int) $_GET['quiz_id'];
$is_guest = isset($_GET['guest']);
$user_id = $_SESSION['user_id'] ?? null;

// Информация о викторине
$quiz_stmt = $conn->prepare("SELECT title, description FROM quizzes WHERE id=?");
$quiz_stmt->execute([$quiz_id]);
$quiz = $quiz_stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    echo "<p>Викторина не найдена.</p>";
    exit;
}

// Для гостей - берем результаты из сессии
if ($is_guest) {
    if (!isset($_SESSION['guest_results'][$quiz_id])) {
        echo "<p>Результаты не найдены.</p>";
        exit;
    }

    $guest_result = $_SESSION['guest_results'][$quiz_id];
    $score = $guest_result['score'];
    $user_answers = $guest_result['answers'];

    // Вопросы и ответы
    $questions_stmt = $conn->prepare("SELECT * FROM questions WHERE quiz_id=? ORDER BY position ASC");
    $questions_stmt->execute([$quiz_id]);
    $questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($questions as &$q) {
        $answers_stmt = $conn->prepare("SELECT * FROM answers WHERE question_id=?");
        $answers_stmt->execute([$q['id']]);
        $q['answers'] = $answers_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ответы пользователя из сессии
        $q['user_answers'] = $user_answers[$q['id']] ?? [];
    }
    unset($q);

    $total_questions = count($questions);
    $percent = $total_questions > 0 ? round($score / $total_questions * 100) : 0;

} else {
    // Для авторизованных пользователей
    if (!isset($_SESSION['user_id'])) {
        header('Location:index.php');
        exit;
    }

    $user_id = (int) $_SESSION['user_id'];

    // Последний результат
    $results_stmt = $conn->prepare("SELECT * FROM results WHERE quiz_id=? AND user_id=? ORDER BY completed_at DESC LIMIT 1");
    $results_stmt->execute([$quiz_id, $user_id]);
    $result = $results_stmt->fetch(PDO::FETCH_ASSOC);
    $result_id = $result['id'] ?? null;

    // Вопросы и ответы
    $questions_stmt = $conn->prepare("SELECT * FROM questions WHERE quiz_id=? ORDER BY position ASC");
    $questions_stmt->execute([$quiz_id]);
    $questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($questions as &$q) {
        $answers_stmt = $conn->prepare("SELECT * FROM answers WHERE question_id=?");
        $answers_stmt->execute([$q['id']]);
        $q['answers'] = $answers_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ответы пользователя
        if ($result_id) {
            $ua_stmt = $conn->prepare("SELECT answer_id FROM user_answers WHERE result_id=? AND question_id=?");
            $ua_stmt->execute([$result_id, $q['id']]);
            $q['user_answers'] = $ua_stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $q['user_answers'] = [];
        }
    }
    unset($q);

    $total_questions = count($questions);
    $score = $result['score'] ?? 0;
    $percent = $total_questions > 0 ? round($score / $total_questions * 100) : 0;
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Результаты: <?php echo htmlspecialchars($quiz['title']); ?></title>

    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/header_footer.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link href="https://fonts.googleapis.com/css?family=Dela+Gothic+One:regular" rel="stylesheet" />
    <style>
        .tabs {
            display: flex;
            margin-bottom: 10px;
        }

        .tab {
            padding: 10px;
            cursor: pointer;
            border: 1px solid #ccc;
            margin-right: 5px;
        }

        .tab.active {
            background: #007bff;
            color: #fff;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .correct {
            color: green;
            font-weight: bold;
        }

        .wrong {
            color: red;
            font-weight: bold;
        }

        .guest-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            color: #856404;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>
    <h2><?php echo htmlspecialchars($quiz['title']); ?></h2>

    <?php if ($is_guest): ?>
        <div class="guest-notice">
            <strong>Гостевой режим:</strong> Ваши результаты сохранены только в этой сессии браузера.
            Для постоянного сохранения результатов рекомендуется <a href="login.php">войти в систему</a>.
        </div>
    <?php endif; ?>

    <div class="tabs">
        <div class="tab active" data-tab="score">Мой результат</div>
        <div class="tab" data-tab="answers">Мои ответы</div>
    </div>
    <div class="tab-content active" id="score">
        <p>Количество баллов: <?php echo $score; ?></p>
        <p>Максимально возможное количество баллов: <?php echo $total_questions; ?></p>
        <p>Процент правильных ответов: <?php echo $percent; ?>%</p>
    </div>
    <div class="tab-content" id="answers">
        <?php foreach ($questions as $i => $q): ?>
            <div>
                <h4>Вопрос <?php echo $i + 1; ?>: <?php echo htmlspecialchars($q['question_text']); ?></h4>
                <ul>
                    <?php foreach ($q['answers'] as $a):
                        $user_chose = in_array($a['id'], $q['user_answers']);
                        $class = '';
                        if ($a['is_correct'] && $user_chose)
                            $class = 'correct';
                        elseif (!$a['is_correct'] && $user_chose)
                            $class = 'wrong';
                        elseif ($a['is_correct'] && !$user_chose)
                            $class = 'correct';
                        ?>
                        <li class="<?php echo $class; ?>"><?php echo htmlspecialchars($a['answer_text']); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
    <script>
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                document.getElementById(tab.dataset.tab).classList.add('active');
            });
        });
    </script>
    <?php include 'footer.php'; ?>
</body>

</html>
