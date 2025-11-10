<?php
session_start();
require_once __DIR__ . '/config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $login = trim($_POST["login"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($login) || empty($password)) {
        die("Введите логин и пароль!");
    }

    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = :login OR email = :login LIMIT 1");
    $stmt->execute(["login" => $login]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user["password_hash"])) {
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["role"] = $user["role"];

        header("Location: index.php");
        exit;
    } else {
        $_SESSION['error'] = "Неверный логин или пароль!";
        header("Location: ../login.html");
        exit;
    }
}
?>
