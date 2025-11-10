<?php
require_once __DIR__ . '/config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm = $_POST["confirm"] ?? "";

    if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
        $_SESSION['error'] = "Заполните все поля!";
        header("Location: ../register.html");
        exit;
    }

    if ($password !== $confirm) {
        $_SESSION['error'] = "Пароли не совпадают!";
        header("Location: ../register.html");
        exit;
    }

    try {
        $db = new Database();
        $conn = $db->getConnection();

        $check = $conn->prepare("SELECT id FROM users WHERE email=:email OR username=:username");
        $check->execute(['email'=>$email,'username'=>$username]);
        if ($check->fetch()) {
            $_SESSION['error'] = "Пользователь с таким именем или email уже существует!";
            header("Location: ../register.html");
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $insert = $conn->prepare("INSERT INTO users (username,email,password_hash) VALUES (:username,:email,:password)");
        $insert->execute([
            'username'=>$username,
            'email'=>$email,
            'password'=>$hashedPassword
        ]);

        $_SESSION['success'] = "Регистрация прошла успешно!";
        header("Location: ../login.html");
        exit;

    } catch (PDOException $e) {
        $_SESSION['error'] = "Ошибка при регистрации: " . $e->getMessage();
        header("Location: ../register.html");
        exit;
    }
}
?>
