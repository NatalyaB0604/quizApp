<?php
session_start();
require_once __DIR__ . '/config.php';

$db = new Database();
$conn = $db->getConnection();

if (!isset($_GET['code'])) {
    header('Location: index.php');
    exit;
}

$access_code = $_GET['code'];
$stmt = $conn->prepare("
    SELECT q.*, u.username AS created_by_username
    FROM quizzes q
    JOIN users u ON q.created_by = u.id
    WHERE q.access_code = ?
");
$stmt->execute([$access_code]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    echo "<p>Викторина не найдена.</p>";
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;

if (!$quiz['public'] && !$user_id) {
    echo "<p>Приватная викторина, войдите.</p>";
    exit;
}

$questions_stmt = $conn->prepare("SELECT * FROM questions WHERE quiz_id=? ORDER BY position ASC");
$questions_stmt->execute([$quiz['id']]);
$questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);

$quiz_data = [];
foreach ($questions as &$q) {
    $answers_stmt = $conn->prepare("SELECT * FROM answers WHERE question_id=?");
    $answers_stmt->execute([$q['id']]);
    $q['answers'] = $answers_stmt->fetchAll(PDO::FETCH_ASSOC);
    $quiz_data[] = $q;
}

$total_time_seconds = $quiz['total_time'] ? ($quiz['total_time'] * 60) : 0;

$quiz_json = json_encode([
    'quiz' => $quiz,
    'questions' => $quiz_data,
    'user_id' => $user_id,
    'total_time_seconds' => $total_time_seconds
]);

?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Викторина: <?php echo htmlspecialchars($quiz['title']); ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/header_footer.css">
    <link rel="stylesheet" href="assets/css/start_quiz.css">
    <link href="https://fonts.googleapis.com/css?family=Dela+Gothic+One:regular" rel="stylesheet" />
</head>

<body>
    <?php include 'header.php'; ?>
    <main>
        <section class="quiz-container">
            <div class="quiz-header">
                <h2 id="quiz-title"><?php echo htmlspecialchars($quiz['title']); ?></h2>
                <div id="total-timer-container" style="display:none;">
                    <span id="total-timer">0</span>
                </div>
            </div>

            <div id="waiting-section" style="display:none;">
                <p id="waiting-message">Ожидание старта викторины...</p>
                <p>Начало через <span id="start-timer"></span> секунд</p>
                <div id="online-users"></div>
            </div>

            <div id="question-section" style="display:none;">
                <div id="question-header">
                    <span id="question-number"></span>
                </div>
                <h3 id="question-text"></h3>
                <div id="answers-form"></div>
                <div class="navigation-buttons">
                    <div class="nav-left">
                        <button id="prev-btn" style="display:none;">← Назад</button>
                    </div>
                    <div class="nav-right">
                        <button id="next-btn" disabled>Следующий вопрос</button>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <?php include 'footer.php'; ?>

    <div id="modal-overlay">
        <div id="modal-content">
            <h2><?php echo htmlspecialchars($quiz['title']); ?></h2>
            <p><?php echo htmlspecialchars($quiz['description'] ?? 'Описание не указано'); ?></p>
            <div class="modal-info">
                <p>Количество вопросов: <?php echo count($questions); ?></p>
                <p>Общее время: <?php
                if ($quiz['total_time'] > 0) {
                    $minutes = floor($quiz['total_time']);
                    $seconds = ($quiz['total_time'] - $minutes) * 60;
                    echo $minutes . ' мин ' . ($seconds > 0 ? $seconds . ' сек' : '');
                } else {
                    echo 'Не ограничено';
                }
                ?></p>
                <p>Создатель: <?php echo htmlspecialchars($quiz['created_by_username']); ?></p>
            </div>
            <div class="modal-buttons">
                <button id="start-quiz-btn">Начать</button>
                <button id="exit-quiz-btn">Выход</button>
            </div>
        </div>
    </div>

    <script>
        const quizData = <?php echo $quiz_json; ?>;
        const questions = quizData.questions;
        let currentIndex = 0;
        let selectedAnswers = {};
        let score = 0;
        let totalTimer;

        let totalTimeRemaining = quizData.total_time_seconds || 0;

        const waitingSection = document.getElementById('waiting-section');
        const startTimerEl = document.getElementById('start-timer');
        const onlineUsersEl = document.getElementById('online-users');
        const questionSection = document.getElementById('question-section');
        const questionText = document.getElementById('question-text');
        const questionNumber = document.getElementById('question-number');
        const answersForm = document.getElementById('answers-form');
        const nextBtn = document.getElementById('next-btn');
        const prevBtn = document.getElementById('prev-btn');
        const totalTimerContainer = document.getElementById('total-timer-container');
        const totalTimerEl = document.getElementById('total-timer');

        const modalOverlay = document.getElementById('modal-overlay');
        const startQuizBtn = document.getElementById('start-quiz-btn');
        const exitQuizBtn = document.getElementById('exit-quiz-btn');

        function fetchOnlineUsers() {
            fetch('update_online.php?quiz=' + quizData.quiz.id)
                .then(r => r.text())
                .then(html => onlineUsersEl.innerHTML = html);
        }

        function formatTime(seconds) {
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${minutes}:${secs < 10 ? '0' : ''}${secs}`;
        }

        function startTotalTimer() {
            if (totalTimeRemaining <= 0) return;

            totalTimerContainer.style.display = 'block';
            totalTimerEl.textContent = formatTime(totalTimeRemaining);

            totalTimer = setInterval(() => {
                totalTimeRemaining--;
                totalTimerEl.textContent = formatTime(totalTimeRemaining);

                if (totalTimeRemaining <= 20) {
                    totalTimerEl.classList.add('time-warning');
                }

                if (totalTimeRemaining <= 0) {
                    clearInterval(totalTimer);
                    finishQuiz();
                }
            }, 1000);
        }

        function waitForStart() {
            document.querySelector('.quiz-container').style.display = 'block';
            document.getElementById('quiz-title').style.display = 'block';

            let startTime = quizData.quiz.start_date ? new Date(quizData.quiz.start_date).getTime() : null;
            let countdown = quizData.quiz.countdown_seconds || 0;

            if (startTime) {
                waitingSection.style.display = 'block';
                let interval = setInterval(() => {
                    let now = new Date().getTime();
                    let diffSec = Math.max(0, Math.floor((startTime - now) / 1000));
                    startTimerEl.innerText = diffSec;
                    fetchOnlineUsers();
                    if (diffSec <= 0) {
                        clearInterval(interval);
                        startQuiz();
                    }
                }, 1000);
            } else if (countdown > 0) {
                waitingSection.style.display = 'block';
                let t = countdown;
                startTimerEl.innerText = t;
                let interval = setInterval(() => {
                    t--;
                    startTimerEl.innerText = t;
                    fetchOnlineUsers();
                    if (t <= 0) {
                        clearInterval(interval);
                        startQuiz();
                    }
                }, 1000);
            } else {
                startQuiz();
            }
        }

        function startQuiz() {

            document.querySelector('.quiz-container').style.display = 'block';
            document.getElementById('quiz-title').style.display = 'block';
            waitingSection.style.display = 'none';
            questionSection.style.display = 'block';
            startTotalTimer();
            showQuestion();
        }

        function showQuestion() {
            answersForm.innerHTML = '';
            const q = questions[currentIndex];

            if (!selectedAnswers[q.id]) {
                selectedAnswers[q.id] = [];
            }

            questionText.innerText = q.question_text;
            questionNumber.innerText = `Вопрос ${currentIndex + 1} из ${questions.length}`;
            const multiple = q.answers.filter(a => a.is_correct == 1).length > 1;

            updateNavigationButtons();

            q.answers.forEach(a => {
                const label = document.createElement('label');
                label.className = 'answer-option';
                const input = document.createElement('input');
                input.type = multiple ? 'checkbox' : 'radio';
                input.name = 'answer';
                input.value = a.id;

                if (selectedAnswers[q.id].includes(parseInt(a.id))) {
                    input.checked = true;
                }

                input.onchange = () => {
                    if (multiple) {
                        const checked = Array.from(answersForm.querySelectorAll('input:checked')).map(i => parseInt(i.value));
                        selectedAnswers[q.id] = checked;
                    } else {
                        selectedAnswers[q.id] = [parseInt(input.value)];
                    }
                    nextBtn.disabled = selectedAnswers[q.id].length === 0;
                };

                label.appendChild(input);
                label.appendChild(document.createTextNode(a.answer_text));
                answersForm.appendChild(label);
            });

            nextBtn.disabled = selectedAnswers[q.id].length === 0;
        }

        function updateNavigationButtons() {
            if (currentIndex > 0) {
                prevBtn.style.display = 'block';
            } else {
                prevBtn.style.display = 'none';
            }

            if (currentIndex === questions.length - 1) {
                nextBtn.textContent = 'Завершить викторину';
            } else {
                nextBtn.textContent = 'Следующий вопрос';
            }
        }

        prevBtn.onclick = () => {
            if (currentIndex > 0) {
                currentIndex--;
                showQuestion();
            }
        };

        nextBtn.onclick = () => {
            if (currentIndex < questions.length - 1) {
                currentIndex++;
                showQuestion();
            } else {
                if (confirm('Вы уверены, что хотите завершить викторину?')) {
                    finishQuiz();
                }
            }
        };

        function finishQuiz() {
            if (totalTimer) {
                clearInterval(totalTimer);
            }

            score = 0;
            questions.forEach(q => {
                const correct = q.answers.map(a => a.is_correct ? a.id : -1).filter(i => i >= 0);
                const userIdx = selectedAnswers[q.id] || [];
                if (JSON.stringify(userIdx.sort()) === JSON.stringify(correct.sort())) score++;
            });

            if (quizData.user_id > 0) {
                fetch('save_result.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        quiz_id: quizData.quiz.id,
                        user_id: quizData.user_id,
                        score: score,
                        answers: selectedAnswers
                    })
                }).then(() => {
                    window.location.href = `results.php?quiz_id=${quizData.quiz.id}`;
                });
            } else {
                fetch('save_guest_result.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        quiz_id: quizData.quiz.id,
                        score: score,
                        answers: selectedAnswers
                    })
                }).then(response => response.json())
                    .then(data => {
                        window.location.href = `results.php?quiz_id=${quizData.quiz.id}&guest=true`;
                    });
            }
        }

        window.onload = () => {
            modalOverlay.style.display = 'flex';
        };

        startQuizBtn.onclick = () => {
            modalOverlay.style.display = 'none';
            waitForStart();
        };

        exitQuizBtn.addEventListener('click', function () {
            if (confirm('Вы уверены, что хотите выйти из викторины?')) {
                window.location.href = 'index.php';
            }
        });
    </script>
</body>

</html>
