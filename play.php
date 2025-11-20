<?php
session_start();
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Играть - quizzzApp</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/header_footer.css">
    <link rel="stylesheet" href="assets/css/play.css">
    <link href="https://fonts.googleapis.com/css?family=Dela+Gothic+One:regular" rel="stylesheet" />
</head>

<body>
    <?php include 'header.php'; ?>

    <main>
        <section class="section-play">
            <a href="index.php" class="logo">quizzzApp</a>
            <h2>Введите код для участия в викторине</h2>
            <form action="start_quiz.php" method="GET">
                <input type="text" name="code" placeholder="123456" required>
                <button type="submit">Играть</button>
            </form>
        </section>

        <section class="section-create-quiz">
            <p class="create-quiz-text">
                Создай свою викторину на
                <a href="<?= isset($_SESSION['user_id']) ? 'create_quiz.php' : 'login.php' ?>">
                    quizzzApp
                </a>
            </p>
        </section>
    </main>

    <?php include 'footer.php'; ?>
</body>

</html>
