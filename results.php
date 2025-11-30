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

$quiz_stmt = $conn->prepare("SELECT title, description FROM quizzes WHERE id=?");
$quiz_stmt->execute([$quiz_id]);
$quiz = $quiz_stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    echo "<p>Викторина не найдена.</p>";
    exit;
}

if ($is_guest) {
    if (!isset($_SESSION['guest_results'][$quiz_id])) {
        echo "<p>Результаты не найдены.</p>";
        exit;
    }

    $guest_result = $_SESSION['guest_results'][$quiz_id];
    $score = $guest_result['score'];
    $user_answers = $guest_result['answers'];

    $questions_stmt = $conn->prepare("SELECT * FROM questions WHERE quiz_id=? ORDER BY position ASC");
    $questions_stmt->execute([$quiz_id]);
    $questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($questions as &$q) {
        $answers_stmt = $conn->prepare("SELECT * FROM answers WHERE question_id=?");
        $answers_stmt->execute([$q['id']]);
        $q['answers'] = $answers_stmt->fetchAll(PDO::FETCH_ASSOC);

        $q['user_answers'] = $user_answers[$q['id']] ?? [];
    }
    unset($q);

    $total_questions = count($questions);
    $percent = $total_questions > 0 ? round($score / $total_questions * 100) : 0;

} else {
    if (!isset($_SESSION['user_id'])) {
        header('Location:index.php');
        exit;
    }

    $user_id = (int) $_SESSION['user_id'];

    $results_stmt = $conn->prepare("SELECT * FROM results WHERE quiz_id=? AND user_id=? ORDER BY completed_at DESC LIMIT 1");
    $results_stmt->execute([$quiz_id, $user_id]);
    $result = $results_stmt->fetch(PDO::FETCH_ASSOC);
    $result_id = $result['id'] ?? null;

    $questions_stmt = $conn->prepare("SELECT * FROM questions WHERE quiz_id=? ORDER BY position ASC");
    $questions_stmt->execute([$quiz_id]);
    $questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($questions as &$q) {
        $answers_stmt = $conn->prepare("SELECT * FROM answers WHERE question_id=?");
        $answers_stmt->execute([$q['id']]);
        $q['answers'] = $answers_stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <link rel="stylesheet" href="assets/css/results.css">
    <link href="https://fonts.googleapis.com/css?family=Dela+Gothic+One:regular" rel="stylesheet" />
</head>

<body>
    <?php include 'header.php'; ?>

    <main class="main-content">
        <div class="results-container">
            <div class="quiz-tabs">
                <button class="quiz-tab active" data-tab="score">Мой результат</button>
                <button class="quiz-tab" data-tab="answers">Мои ответы</button>
            </div>

            <?php if ($is_guest): ?>
                <div class="guest-notice">
                    <strong>Гостевой режим:</strong> Для сохранения результатов рекомендуется <a href="login.php">войти в систему</a>.
                </div>
            <?php endif; ?>

            <div class="quiz-tab-content active" id="score">
                <div class="results-section">
                    <h2 class="results-title">Результаты викторины</h2>
                    <div class="score-summary">
                        <div class="score-circle">
                            <div class="score-percent"><?php echo $percent; ?>%</div>
                            <div class="score-text">правильных ответов</div>
                        </div>
                        <div class="score-details">
                            <div class="score-item">
                                <span class="score-label">Набрано баллов:</span>
                                <span class="score-value"><?php echo $score; ?><span class="score-label"> из </span>
                                    <?php echo $total_questions; ?></span>
                            </div>
                            <div class="score-item">
                                <span class="score-label">Правильных ответов:</span>
                                <span class="score-value"><?php echo $score; ?></span>
                            </div>
                            <div class="score-item">
                                <span class="score-label">Неправильных ответов:</span>
                                <span class="score-value"><?php echo $total_questions - $score; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="results-actions">
                        <a href="start_quiz.php?code=<?php
                        $access_stmt = $conn->prepare("SELECT access_code FROM quizzes WHERE id=?");
                        $access_stmt->execute([$quiz_id]);
                        $access_code = $access_stmt->fetchColumn();
                        echo htmlspecialchars($access_code);
                        ?>" class="play-btn">Играть еще раз</a>
                        <a href="index.php" class="back-btn">На главную</a>
                    </div>
                </div>
            </div>

            <div class="quiz-tab-content" id="answers">
                <div class="answers-section">
                    <h2 class="results-title">Детализация ответов</h2>
                    <div class="questions-list">
                        <?php foreach ($questions as $i => $q): ?>
                            <?php
                            $user_ans = $q['user_answers'] ?? [];
                            $correct_answers = array_filter($q['answers'], function ($a) {
                                return $a['is_correct'];
                            });
                            $correct_ids = array_column($correct_answers, 'id');
                            $is_correct = empty(array_diff($user_ans, $correct_ids)) && empty(array_diff($correct_ids, $user_ans));
                            ?>
                            <div class="question-item <?php echo $is_correct ? 'correct' : 'incorrect'; ?>">
                                <div class="question-header">
                                    <h3>Вопрос <?php echo $i + 1; ?></h3>
                                    <span class="question-status">
                                        <?php
                                        if ($is_correct): ?>
                                            ✓ Правильно
                                        <?php else: ?>
                                            ✗ Неправильно
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <p class="question-text"><?php echo htmlspecialchars($q['question_text']); ?></p>

                                <div class="answers-list">
                                    <?php foreach ($q['answers'] as $a):
                                        $user_chose = in_array($a['id'], $q['user_answers']);
                                        $is_correct = $a['is_correct'];
                                        $class = '';
                                        if ($is_correct && $user_chose)
                                            $class = 'correct chosen';
                                        elseif ($is_correct && !$user_chose)
                                            $class = 'correct';
                                        elseif (!$is_correct && $user_chose)
                                            $class = 'wrong chosen';
                                        else
                                            $class = 'neutral';
                                        ?>
                                        <div class="answer-item <?php echo $class; ?>">
                                            <span class="answer-text"><?php echo htmlspecialchars($a['answer_text']); ?></span>
                                            <?php if ($user_chose): ?>
                                                <span class="answer-marker">Ваш ответ</span>
                                            <?php elseif ($is_correct): ?>
                                                <span class="answer-marker">Правильный ответ</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabs = document.querySelectorAll('.quiz-tab');
            const tabContents = document.querySelectorAll('.quiz-tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));

                    tab.classList.add('active');
                    const tabId = tab.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        });
    </script>

    <?php include 'footer.php'; ?>
</body>

</html>
