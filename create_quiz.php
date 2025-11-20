<?php
session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'save_quiz') {
    $data = json_decode($_POST['data'], true);

    if (!$data || empty($data['settings']) || empty($data['questions'])) {
        echo json_encode(['success' => false, 'error' => 'Неверные данные викторины']);
        exit;
    }

    $settings = $data['settings'];
    $questions = $data['questions'];

    $title = trim($settings['title'] ?? '');
    $description = trim($settings['description'] ?? '');
    $is_public = isset($settings['is_public']) && $settings['is_public'] ? 1 : 0;
    $start_date = ($is_public || empty($settings['start_date'])) ? null : $settings['start_date'];
    $auto_start = $settings['auto_start'] ? 1 : 0;
    $countdown_enabled = $settings['countdown_enabled'] ? 1 : 0;
    $countdown_seconds = $countdown_enabled ? intval($settings['countdown_seconds'] ?? 0) : 0;
    $total_time = floatval($settings['total_time'] ?? 60);

    if (empty($title)) {
        echo json_encode(['success' => false, 'error' => 'Введите название викторины!']);
        exit;
    }

    do {
        $access_code = strtoupper(substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 6));
        $check = $conn->prepare("SELECT id FROM quizzes WHERE access_code = ?");
        $check->execute([$access_code]);
    } while ($check->fetch());

    $stmt = $conn->prepare("
        INSERT INTO quizzes (title, description, public, access_code, created_by, auto_start, countdown_enabled, countdown_seconds, total_time, start_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$title, $description, $is_public, $access_code, $_SESSION['user_id'], $auto_start, $countdown_enabled, $countdown_seconds, $total_time, $start_date]);

    $quiz_id = $conn->lastInsertId();

    foreach ($questions as $position => $question) {
        $question_text = trim($question['text'] ?? '');
        $type = $question['type'] ?? 'multiple_choice';

        if (empty($question_text)) {
            echo json_encode(['success' => false, 'error' => "Вопрос " . ($position + 1) . " не заполнен!"]);
            exit;
        }

        $q_stmt = $conn->prepare("
            INSERT INTO questions (quiz_id, type, question_text, position)
            VALUES (?, ?, ?, ?)
        ");
        $q_stmt->execute([$quiz_id, $type, $question_text, $position + 1]);

        $question_id = $conn->lastInsertId();

        foreach ($question['answers'] as $answer) {
            $answer_text = trim($answer['text'] ?? '');
            $is_correct = $answer['correct'] ? 1 : 0;

            if (($type === 'multiple_choice' || $type === 'true_false') && empty($answer_text)) {
                echo json_encode(['success' => false, 'error' => "В вопросе " . ($position + 1) . " есть пустые варианты ответа!"]);
                exit;
            }

            $a_stmt = $conn->prepare("
                INSERT INTO answers (question_id, answer_text, is_correct)
                VALUES (?, ?, ?)
            ");
            $a_stmt->execute([$question_id, $answer_text, $is_correct]);
        }
    }

    echo json_encode(['success' => true, 'access_code' => $access_code]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Создание викторины</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/header_footer.css">
    <link rel="stylesheet" href="assets/css/create_quiz.css">
    <link href="https://fonts.googleapis.com/css?family=Dela+Gothic+One:regular" rel="stylesheet" />
</head>

<body>
    <?php include 'header.php'; ?>

    <main>
        <div class="quiz-create-container">
            <div class="quiz-tabs">
                <button class="quiz-tab active" data-tab="settings" onclick="showTab('settings')">Настройки</button>
                <button class="quiz-tab" data-tab="questions" onclick="showTab('questions')"
                    id="questionsTab">Вопросы</button>
            </div>
            <div id="settings" class="quiz-tab-content active">
                <h2 class="quiz-section-title">Настройки викторины</h2>
                <div class="settings-section">
                    <h3 class="quiz-subtitle">Название и описание</h3>
                    <div class="quiz-input-group">
                        <input type="text" id="quizTitle" name="title" maxlength="100"
                            placeholder="Введите название викторины*" required>
                        <div class="char-counter"><span id="titleCounter">0</span>/100</div>
                    </div>

                    <div class="quiz-input-group">
                        <textarea id="quizDescription" name="description" maxlength="800"
                            placeholder="Добавьте описание викторины (необязательно)"></textarea>
                        <div class="char-counter"><span id="descCounter">0</span>/800</div>
                    </div>
                </div>

                <div class="settings-section">
                    <h3 class="quiz-subtitle">Настройки доступа</h3>

                    <div class="quiz-checkbox-group">
                        <input type="checkbox" id="isPublic" name="is_public">
                        <label for="isPublic">Публичная викторина</label>
                    </div>

                    <p class="setting-description">
                        Публичные викторины видны всем пользователям, приватные - только по коду доступа
                    </p>
                </div>

                <div id="startTimeSettings" class="settings-section">
                    <h3 class="quiz-subtitle">Время запуска</h3>

                    <div class="quiz-input-group">
                        <input type="datetime-local" id="startDate" name="start_date">
                    </div>

                    <div class="quiz-checkbox-group">
                        <input type="checkbox" id="autoStart" name="auto_start">
                        <label for="autoStart">Автоматический запуск при старте</label>
                    </div>
                </div>

                <div class="settings-section">
                    <h3 class="quiz-subtitle">Настройки времени</h3>

                    <div class="quiz-checkbox-group">
                        <input type="checkbox" id="countdownEnabled" name="countdown_enabled">
                        <label for="countdownEnabled">Показать обратный отсчет перед стартом</label>
                    </div>

                    <div class="time-settings-row">
                        <div id="countdownDurationSetting" class="time-setting">
                            <label for="countdownSeconds" class="required-field">Длительность отсчета (сек)</label>
                            <input type="number" id="countdownSeconds" name="countdown_seconds" min="0" max="60"
                                value="10" required>
                        </div>

                        <div class="time-setting">
                            <label for="totalTime" class="required-field">Общее время на викторину (мин)</label>
                            <input type="number" id="totalTime" name="total_time" min="0.5" max="180" step="0.5" placeholder="1, 2, 2.5..." required>
                        </div>
                    </div>
                </div>

                <div class="quiz-actions">
                    <div></div>
                    <button class="quiz-btn next-btn" onclick="validateSettings()">Далее →</button>
                </div>
            </div>

            <div id="questions" class="quiz-tab-content">
                <h2 class="quiz-section-title">Вопросы викторины</h2>

                <div class="questions-layout">
                    <div class="questions-sidebar">
                        <h3>Список вопросов</h3>
                        <div class="questions-list" id="questionsList">
                        </div>
                        <button class="add-question-btn" onclick="saveCurrentQuestion(); addQuestion()">
                            + Добавить вопрос
                        </button>
                    </div>

                    <div class="question-editor" id="questionEditor">
                        <div class="question-header">
                            <h3 id="questionHeader">1. Текст вопроса...</h3>
                        </div>

                        <div class="quiz-input-group">
                            <div class="custom-select-wrapper">
                                <div class="custom-select" onclick="toggleSelect()">
                                    <div class="select-selected">С вариантами ответов</div>
                                    <div class="select-items">
                                        <div data-value="multiple_choice" class="select-item"
                                            onclick="selectOption(this, event)">С вариантами ответов</div>
                                        <div data-value="true_false" class="select-item"
                                            onclick="selectOption(this, event)">Верно/Неверно</div>
                                    </div>
                                </div>
                                <input type="hidden" id="questionType" value="multiple_choice">
                            </div>
                        </div>

                        <div class="quiz-input-group">
                            <textarea id="questionText" placeholder="Введите текст вопроса"
                                style="min-height: 120px;"></textarea>
                        </div>

                        <div class="quiz-checkbox-group" id="multipleAnswersContainer" style="display: none;">
                            <input type="checkbox" id="multipleAnswers" onchange="toggleMultipleAnswers()">
                            <label for="multipleAnswers">Несколько правильных ответов</label>
                        </div>

                        <div class="answers-section">
                            <div class="answers-header">
                                <span class="answers-title" id="correctAnswerLabel">Правильный ответ?</span>
                            </div>
                            <div id="answersContainer">
                                <div class="answer-option">
                                    <input type="text" placeholder="Вариант ответа" class="answer-input">
                                    <div class="answer-controls">
                                        <input type="radio" name="correct" value="0" checked class="correct-radio">
                                        <button class="remove-answer-btn" onclick="removeAnswer(this)">
                                            <img src="assets/images/trash.svg" alt="Удалить" width="16" height="16">
                                        </button>
                                    </div>
                                </div>
                                <div class="answer-option">
                                    <input type="text" placeholder="Вариант ответа" class="answer-input">

                                    <div class="answer-controls">
                                        <input type="radio" name="correct" value="1" class="correct-radio">
                                        <button class="remove-answer-btn" onclick="removeAnswer(this)">
                                            <img src="assets/images/trash.svg" alt="Удалить" width="16" height="16">
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button class="add-answer-btn" onclick="addAnswerOption()" id="addAnswerBtn">+ Добавить
                            вариант</button>

                        <div class="quiz-actions">
                            <button class="quiz-btn prev-btn" onclick="showTab('settings')">← Назад</button>
                            <button class="quiz-btn save-btn" onclick="saveQuiz()">Сохранить викторину</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        let currentQuestions = [];
        let currentQuestionIndex = -1;
        let isMultipleAnswers = false;

        function showTab(tabName) {
            document.querySelectorAll('.quiz-tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            document.querySelectorAll('.quiz-tab').forEach(tab => {
                tab.classList.remove('active');
            });

            document.getElementById(tabName).classList.add('active');
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
        }

        function validateSettings() {
            const title = document.getElementById('quizTitle').value.trim();
            const countdownEnabled = document.getElementById('countdownEnabled').checked;
            const countdownSeconds = document.getElementById('countdownSeconds').value;
            const totalTime = parseFloat(document.getElementById('totalTime').value);

            if (!title) {
                alert('Пожалуйста, введите название викторины');
                document.getElementById('quizTitle').focus();
                return;
            }

            if (countdownEnabled) {
                if (countdownSeconds < 0 || countdownSeconds > 60) {
                    alert('Длительность отсчета должна быть от 0 до 60 секунд');
                    return;
                }
            }

            if (isNaN(totalTime) || totalTime < 0.5 || totalTime > 180) {
                alert('Общее время на викторину должно быть от 0.5 до 180 минут');
                return;
            }

            showTab('questions');
        }

        function toggleSelect() {
            const select = document.querySelector('.custom-select');
            select.classList.toggle('select-active');
        }

        function selectOption(element, event) {
            event.stopPropagation();

            const value = element.getAttribute('data-value');
            const text = element.textContent;

            document.querySelector('.select-selected').textContent = text;
            document.getElementById('questionType').value = value;

            document.querySelector('.custom-select').classList.remove('select-active');

            changeQuestionType();
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.custom-select')) {
                document.querySelector('.custom-select').classList.remove('select-active');
            }
        });

        function toggleStartTimeSettings() {
            const isPublic = document.getElementById('isPublic').checked;
            const startTimeSettings = document.getElementById('startTimeSettings');

            if (isPublic) {
                startTimeSettings.style.display = 'none';
            } else {
                startTimeSettings.style.display = 'block';
            }
        }

        function toggleCountdownDuration() {
            const countdownEnabled = document.getElementById('countdownEnabled').checked;
            const countdownDurationSetting = document.getElementById('countdownDurationSetting');

            if (countdownEnabled) {
                countdownDurationSetting.style.display = 'flex';
            } else {
                countdownDurationSetting.style.display = 'none';
            }
        }

        function toggleMultipleAnswers() {
            isMultipleAnswers = document.getElementById('multipleAnswers').checked;
            const correctInputs = document.querySelectorAll('.correct-radio');
            const label = document.getElementById('correctAnswerLabel');

            if (isMultipleAnswers) {
                label.textContent = 'Правильные ответы?';
                correctInputs.forEach(input => {
                    input.type = 'checkbox';
                    input.name = 'correct[]';
                });
            } else {
                label.textContent = 'Правильный ответ?';
                correctInputs.forEach(input => {
                    input.type = 'radio';
                    input.name = 'correct';
                });
            }
        }

        function changeQuestionType() {
            const type = document.getElementById('questionType').value;
            const answersContainer = document.getElementById('answersContainer');
            const addAnswerBtn = document.getElementById('addAnswerBtn');
            const multipleContainer = document.getElementById('multipleAnswersContainer');

            if (currentQuestions[currentQuestionIndex]) {
                currentQuestions[currentQuestionIndex].type = type;
            }

            if (type === 'multiple_choice') {
                multipleContainer.style.display = 'flex';
            } else {
                multipleContainer.style.display = 'none';
                isMultipleAnswers = false;
                document.getElementById('multipleAnswers').checked = false;
                toggleMultipleAnswers();
            }

            if (type === 'true_false') {
                answersContainer.innerHTML = `
                    <div class="answer-option">
                        <div class="answer-input-with-prefix">
                            <span class="answer-prefix">A</span>
                            <input type="text" value="Верно" readonly class="answer-input">
                        </div>
                        <div class="answer-controls">
                            <input type="radio" name="correct" value="0" checked class="correct-radio">
                            <button class="remove-answer-btn" style="display: none;">
                                <img src="assets/images/trash.svg" alt="Удалить" width="16" height="16">
                            </button>
                        </div>
                    </div>
                    <div class="answer-option">
                        <div class="answer-input-with-prefix">
                            <span class="answer-prefix">B</span>
                            <input type="text" value="Неверно" readonly class="answer-input">
                        </div>
                        <div class="answer-controls">
                            <input type="radio" name="correct" value="1" class="correct-radio">
                            <button class="remove-answer-btn" style="display: none;">
                                <img src="assets/images/trash.svg" alt="Удалить" width="16" height="16">
                            </button>
                        </div>
                    </div>
                `;
                addAnswerBtn.style.display = 'none';
            } else {
                currentQuestions[currentQuestionIndex].answers = [
                    { text: '', correct: true },
                    { text: '', correct: false }
                ];
                addAnswerBtn.style.display = 'block';
                updateAnswersUI(currentQuestions[currentQuestionIndex].answers);
            }
            saveCurrentQuestion();
        }

        function updateQuestionHeader() {
            if (currentQuestionIndex === -1) return;

            const question = currentQuestions[currentQuestionIndex];
            const questionText = question.text || 'Текст вопроса';
            document.getElementById('questionHeader').textContent = `${question.number}. ${questionText}`;
        }

        function addAnswerOption() {
            const type = document.getElementById('questionType').value;
            if (type === 'true_false') return;

            const answersContainer = document.getElementById('answersContainer');
            const answerCount = answersContainer.children.length;
            const letter = String.fromCharCode(65 + answerCount);

            const answerDiv = document.createElement('div');
            answerDiv.className = 'answer-option';
            answerDiv.innerHTML = `
                <div class="answer-input-with-prefix">
                    <span class="answer-prefix">${letter}</span>
                    <input type="text" placeholder="Вариант ответа" class="answer-input">
                </div>
                <div class="answer-controls">
                    <input type="${isMultipleAnswers ? 'checkbox' : 'radio'}" name="${isMultipleAnswers ? 'correct[]' : 'correct'}" value="${answerCount}" class="correct-radio">
                    <button class="remove-answer-btn" onclick="removeAnswer(this)">
                        <img src="assets/images/trash.svg" alt="Удалить" width="16" height="16">
                    </button>
                </div>
            `;

            answersContainer.appendChild(answerDiv);

            const removeButtons = answersContainer.querySelectorAll('.remove-answer-btn');
            if (removeButtons.length <= 2) {
                removeButtons.forEach(btn => btn.style.display = 'none');
            } else {
                removeButtons.forEach(btn => btn.style.display = 'block');
            }

            saveCurrentQuestion();
        }

        function removeAnswer(button) {
            const answerOption = button.closest('.answer-option');
            if (answerOption && answerOption.parentElement.children.length > 2) {
                answerOption.remove();
                updateAnswerPrefixes();

                const answersContainer = document.getElementById('answersContainer');
                const removeButtons = answersContainer.querySelectorAll('.remove-answer-btn');
                if (removeButtons.length <= 2) {
                    removeButtons.forEach(btn => btn.style.display = 'none');
                }

                saveCurrentQuestion();
            }
        }

        function updateAnswerPrefixes() {
            const answerOptions = document.querySelectorAll('.answer-option');
            answerOptions.forEach((option, index) => {
                const prefix = option.querySelector('.answer-prefix');
                if (prefix) {
                    prefix.textContent = String.fromCharCode(65 + index);
                }
            });
        }

        function removeQuestion(questionId, event) {
            event.stopPropagation();
            const index = currentQuestions.findIndex(q => q.id === questionId);
            if (index !== -1) {
                currentQuestions.splice(index, 1);

                currentQuestions.forEach((q, i) => {
                    q.number = i + 1;
                });

                const questionElement = document.querySelector(`[data-question-id="${questionId}"]`);
                if (questionElement) {
                    questionElement.remove();
                }

                if (currentQuestions.length > 0) {
                    const newIndex = Math.min(index, currentQuestions.length - 1);
                    selectQuestion(currentQuestions[newIndex].id);
                } else {
                    currentQuestionIndex = -1;
                    document.getElementById('questionEditor').style.display = 'none';
                }

                updateQuestionNumbers();
            }
        }

        function updateQuestionNumbers() {
            const questionItems = document.querySelectorAll('.question-item');
            questionItems.forEach((item, index) => {
                const numberSpan = item.querySelector('.question-number');
                if (numberSpan) {
                    numberSpan.textContent = `Вопрос ${index + 1}`;
                }
                const question = currentQuestions[index];
                if (question) {
                    question.number = index + 1;
                }
            });
        }

        function addQuestion() {
            const questionId = Date.now();
            const questionNumber = currentQuestions.length + 1;

            const questionItem = document.createElement('div');
            questionItem.className = 'question-item' + (currentQuestions.length === 0 ? ' active' : '');
            questionItem.setAttribute('data-question-id', questionId);
            questionItem.innerHTML = `
                <div class="question-item-header">
                    <span class="question-number">Вопрос ${questionNumber}</span>
                    <button class="remove-question-btn" onclick="removeQuestion(${questionId}, event)">
                        <img src="assets/images/trash.svg" alt="Удалить вопрос" width="14" height="14">
                    </button>
                </div>
                <div class="question-preview">Новый вопрос...</div>
            `;

            questionItem.addEventListener('click', () => selectQuestion(questionId));
            document.getElementById('questionsList').appendChild(questionItem);

            currentQuestions.push({
                id: questionId,
                type: 'multiple_choice',
                text: '',
                answers: [
                    { text: '', correct: true },
                    { text: '', correct: false }
                ],
                number: questionNumber
            });

            if (currentQuestions.length === 1) {
                selectQuestion(questionId);
                document.getElementById('questionEditor').style.display = 'block';
            }
            updateQuestionHeader();
        }

        function selectQuestion(questionId) {
            currentQuestionIndex = currentQuestions.findIndex(q => q.id === questionId);
            if (currentQuestionIndex === -1) return;

            const question = currentQuestions[currentQuestionIndex];
            const questionElement = document.querySelector(`[data-question-id="${questionId}"]`);

            document.querySelectorAll('.question-item').forEach(item => item.classList.remove('active'));
            if (questionElement) {
                questionElement.classList.add('active');
            }

            document.getElementById('questionType').value = question.type;
            document.querySelector('.select-selected').textContent = getQuestionTypeName(question.type);
            document.getElementById('questionText').value = question.text;

            const multipleContainer = document.getElementById('multipleAnswersContainer');
            if (question.type === 'multiple_choice') {
                multipleContainer.style.display = 'flex';
            } else {
                multipleContainer.style.display = 'none';
            }

            updateAnswersUI(question.answers);
            updateQuestionHeader();
        }

        function getQuestionTypeName(type) {
            const typeNames = {
                'multiple_choice': 'С вариантами ответов',
                'true_false': 'Верно/Неверно'
            };
            return typeNames[type] || type;
        }

        function updateAnswersUI(answers) {
            const answersContainer = document.getElementById('answersContainer');
            const type = currentQuestions[currentQuestionIndex]?.type || 'multiple_choice';

            answersContainer.innerHTML = '';

            answers.forEach((answer, index) => {
                const answerDiv = document.createElement('div');
                answerDiv.className = 'answer-option';

                const showRemoveBtn = type === 'multiple_choice' && index >= 2;

                answerDiv.innerHTML = `
                    <div class="answer-input-with-prefix">
                        <span class="answer-prefix">${String.fromCharCode(65 + index)}</span>
                        <input type="text" value="${answer.text}" placeholder="Вариант ответа" class="answer-input">
                    </div>
                    <div class="answer-controls">
                        <input type="${isMultipleAnswers ? 'checkbox' : 'radio'}" name="${isMultipleAnswers ? 'correct[]' : 'correct'}" value="${index}" ${answer.correct ? 'checked' : ''} class="correct-radio">
                        <button class="remove-answer-btn" onclick="removeAnswer(this)" ${showRemoveBtn ? '' : 'style="display: none;"'}>
                            <img src="assets/images/trash.svg" alt="Удалить" width="16" height="16">
                        </button>
                    </div>
                `;

                answersContainer.appendChild(answerDiv);
            });
        }

        function saveCurrentQuestion() {
            if (currentQuestionIndex === -1) return;

            const question = currentQuestions[currentQuestionIndex];
            question.text = document.getElementById('questionText').value;
            question.type = document.getElementById('questionType').value;

            const answerInputs = document.querySelectorAll('.answer-input');
            const correctInputs = document.querySelectorAll('.correct-radio');

            question.answers = [];
            answerInputs.forEach((input, index) => {
                question.answers.push({
                    text: input.value,
                    correct: correctInputs[index] ? correctInputs[index].checked : index === 0
                });
            });

            updateQuestionPreview(question);
            updateQuestionHeader();
        }

        function updateQuestionPreview(question) {
            const questionItem = document.querySelector(`[data-question-id="${question.id}"]`);
            if (questionItem) {
                const preview = questionItem.querySelector('.question-preview');
                if (preview) {
                    preview.textContent = question.text || 'Новый вопрос...';
                }
            }
        }

        async function saveQuiz() {
            saveCurrentQuestion();

            if (currentQuestions.length === 0) {
                alert('Добавьте хотя бы один вопрос!');
                return;
            }

            for (let question of currentQuestions) {
                if (!question.text.trim()) {
                    alert(`Вопрос ${question.number} не заполнен!`);
                    return;
                }

                if (question.type === 'multiple_choice') {
                    for (let answer of question.answers) {
                        if (!answer.text.trim()) {
                            alert(`В вопросе ${question.number} есть пустые варианты ответа!`);
                            return;
                        }
                    }

                    const hasCorrect = question.answers.some(answer => answer.correct);
                    if (!hasCorrect) {
                        alert(`В вопросе ${question.number} нет правильного ответа!`);
                        return;
                    }
                }
            }

            const quizData = {
                settings: getSettingsData(),
                questions: currentQuestions
            };

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'save_quiz',
                        data: JSON.stringify(quizData)
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Викторина успешно сохранена! Код доступа: ' + result.access_code);
                    window.location.href = 'my_quizzes.php';
                } else {
                    alert('Ошибка: ' + result.error);
                }
            } catch (error) {
                alert('Ошибка при сохранении: ' + error.message);
            }
        }

        function getSettingsData() {
            return {
                title: document.getElementById('quizTitle').value,
                description: document.getElementById('quizDescription').value,
                is_public: document.getElementById('isPublic').checked,
                start_date: document.getElementById('startDate').value,
                auto_start: document.getElementById('autoStart').checked,
                countdown_enabled: document.getElementById('countdownEnabled').checked,
                countdown_seconds: document.getElementById('countdownSeconds').value,
                total_time: document.getElementById('totalTime').value
            };
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('quizTitle').addEventListener('input', function () {
                document.getElementById('titleCounter').textContent = this.value.length;
            });

            document.getElementById('quizDescription').addEventListener('input', function () {
                document.getElementById('descCounter').textContent = this.value.length;
            });

            document.getElementById('questionText').addEventListener('input', function () {
                saveCurrentQuestion();
                updateQuestionHeader();
            });

            document.getElementById('isPublic').addEventListener('change', function () {
                toggleStartTimeSettings();
            });

            document.getElementById('countdownEnabled').addEventListener('change', function () {
                toggleCountdownDuration();
            });

            document.querySelectorAll('.select-item').forEach(item => {
                item.addEventListener('click', function () {
                    selectOption(this, event);
                });
            });

            toggleStartTimeSettings();
            toggleCountdownDuration();
            saveCurrentQuestion();
            addQuestion();
        });
    </script>

    <?php include 'footer.php'; ?>
</body>

</html>
