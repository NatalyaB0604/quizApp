<?php
session_start();
require_once __DIR__ . '/config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $login = trim($_POST["login"] ?? "");
    $password = $_POST["password"] ?? "";

    $_SESSION['login_value'] = $login;
    $_SESSION['password_value'] = $password;

    if (empty($login) || empty($password)) {
        $_SESSION['error'] = "Введите логин и пароль!";
        header("Location: login.php");
        exit;
    }

    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = :login OR email = :login LIMIT 1");
    $stmt->execute(["login" => $login]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user["password_hash"])) {
        unset($_SESSION['login_value'], $_SESSION['password_value']);

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["role"] = $user["role"];

        header("Location: profile.php");
        exit;
    } else {
        $_SESSION['error'] = "Неверный логин (Email) или пароль!";
        header("Location: login.php");
        exit;
    }
}

$login_value = $_SESSION['login_value'] ?? '';
$password_value = $_SESSION['password_value'] ?? '';

unset($_SESSION['login_value'], $_SESSION['password_value']);
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Вход</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/header_footer.css">
    <link rel="stylesheet" href="assets/css/login_register.css">
    <link href="https://fonts.googleapis.com/css?family=Dela+Gothic+One:regular" rel="stylesheet" />
</head>

<body>
    <?php include 'header.php'; ?>

    <main class="login-container">
        <div class="login-box">
            <h2>Вход</h2>
            <form method="POST" class="login-form" id="loginForm">
                <div class="input-group">
                    <input
                        type="text"
                        name="login"
                        id="login"
                        required
                        placeholder="Имя пользователя (или Email)*"
                        value="<?php echo htmlspecialchars($login_value); ?>"
                    >
                </div>

                <div class="input-group password-group">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        required
                        placeholder="Пароль*"
                        value="<?php echo htmlspecialchars($password_value); ?>"
                    >
                    <img src="assets/images/visibility_off.svg" alt="Показать пароль" class="toggle-password"
                        id="togglePassword">
                </div>

                <button class="login-register-btn" type="submit">Войти</button>

                <p class="register-link">
                    Нет аккаунта? <a href="register.php">ЗАРЕГИСТРИРУЙТЕСЬ</a>
                </p>
            </form>
        </div>
    </main>

    <script>
         const passwordInput = document.getElementById("password");
        const toggleIcon = document.getElementById("togglePassword");

        if (toggleIcon && passwordInput) {
            toggleIcon.addEventListener("click", () => {
                const isHidden = passwordInput.type === "password";
                passwordInput.type = isHidden ? "text" : "password";
                toggleIcon.src = isHidden
                    ? "assets/images/visibility.svg"
                    : "assets/images/visibility_off.svg";
            });
        }

        <?php if (isset($_SESSION['error'])): ?>
            setTimeout(() => {
                alert("<?php echo $_SESSION['error']; ?>");
                <?php unset($_SESSION['error']); ?>
            }, 100);
        <?php endif; ?>

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const login = document.getElementById('login').value.trim();
            const password = document.getElementById('password').value.trim();

            if (!login || !password) {
                e.preventDefault();
                alert('Пожалуйста, заполните все поля!');
                return false;
            }

            return true;
        });
    </script>

    <?php include 'footer.php'; ?>
</body>

</html>
