<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Главная</title>
</head>
<body>
    <h2>Добро пожаловать, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>
    <p>Твоя роль: <?= $_SESSION['role'] ?></p>
    <a href="logout.php">Выйти</a>
</body>
</html>
