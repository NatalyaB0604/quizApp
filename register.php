<?php
session_start();
require_once __DIR__ . '/config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm = $_POST["confirm"] ?? "";

    $_SESSION['register_username'] = $username;
    $_SESSION['register_email'] = $email;
    $_SESSION['register_password'] = $password;
    $_SESSION['register_confirm'] = $confirm;

    if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
        $_SESSION['error'] = "Заполните все поля";
        header("Location: register.php");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Некорректный адрес электронной почты";
        header("Location: register.php");
        exit;
    }

    if (strlen($password) < 5) {
        $_SESSION['error'] = "Длина пароля должна быть не менее 5 символов";
        header("Location: register.php");
        exit;
    }

    if ($password !== $confirm) {
        $_SESSION['error'] = "Пароли не совпадают";
        header("Location: register.php");
        exit;
    }

    try {
        $db = new Database();
        $conn = $db->getConnection();

        $check = $conn->prepare("SELECT id FROM users WHERE email=:email OR username=:username");
        $check->execute(['email' => $email, 'username' => $username]);
        if ($check->fetch()) {
            $_SESSION['error'] = "Пользователь с таким именем или email уже существует";
            header("Location: register.php");
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $insert = $conn->prepare("INSERT INTO users (username,email,password_hash) VALUES (:username,:email,:password)");
        $insert->execute([
            'username' => $username,
            'email' => $email,
            'password' => $hashedPassword
        ]);

        unset(
            $_SESSION['register_username'],
            $_SESSION['register_email'],
            $_SESSION['register_password'],
            $_SESSION['register_confirm']
        );

        $_SESSION['success'] = "Регистрация прошла успешно!";
        header("Location: login.php");
        exit;

    } catch (PDOException $e) {
        $_SESSION['error'] = "Ошибка при регистрации: " . $e->getMessage();
        header("Location: register.php");
        exit;
    }
}

$username_value = $_SESSION['register_username'] ?? '';
$email_value = $_SESSION['register_email'] ?? '';
$password_value = $_SESSION['register_password'] ?? '';
$confirm_value = $_SESSION['register_confirm'] ?? '';

if (!isset($_SESSION['error'])) {
    unset(
        $_SESSION['register_username'],
        $_SESSION['register_email'],
        $_SESSION['register_password'],
        $_SESSION['register_confirm']
    );
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Регистрация</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/header_footer.css">
    <link rel="stylesheet" href="assets/css/login_register.css">
    <link href="https://fonts.googleapis.com/css?family=Dela+Gothic+One:regular" rel="stylesheet" />
</head>

<body>
    <?php include 'header.php'; ?>

    <main class="login-container">
        <div class="login-box">
            <h2>Регистрация</h2>
            <form method="POST" class="login-form" id="registerForm" novalidate>
                <div class="input-group">
                    <input type="text" name="username" id="username" required placeholder="Имя пользователя*"
                        value="<?= htmlspecialchars($username_value) ?>">
                    <div class="field-error" id="usernameError"></div>
                </div>

                <div class="input-group">
                    <input type="email" name="email" id="email" required placeholder="Email*"
                        value="<?= htmlspecialchars($email_value) ?>">
                    <div class="field-error" id="emailError"></div>
                </div>

                <div class="input-group password-group">
                    <input type="password" name="password" id="password" required placeholder="Пароль*"
                        value="<?= htmlspecialchars($password_value) ?>">
                    <img src="assets/images/visibility_off.svg" alt="Показать пароль" class="toggle-password"
                        id="togglePassword1">
                    <div class="field-error" id="passwordError"></div>
                </div>

                <div class="input-group password-group">
                    <input type="password" name="confirm" id="confirm" required placeholder="Подтвердите пароль*"
                        value="<?= htmlspecialchars($confirm_value) ?>">
                    <img src="assets/images/visibility_off.svg" alt="Показать пароль" class="toggle-password"
                        id="togglePassword2">
                    <div class="field-error" id="confirmError"></div>
                </div>

                <button class="login-register-btn" type="submit">Зарегистрироваться</button>

                <p class="register-link">
                    Уже зарегистрированы? <a href="login.php">ВОЙТИ</a>
                </p>
            </form>
        </div>
    </main>

    <script>
        (function () {
            const form = document.getElementById('registerForm');
            const fields = {
                username: document.getElementById('username'),
                email: document.getElementById('email'),
                password: document.getElementById('password'),
                confirm: document.getElementById('confirm')
            };

            const errors = {
                username: document.getElementById('usernameError'),
                email: document.getElementById('emailError'),
                password: document.getElementById('passwordError'),
                confirm: document.getElementById('confirmError')
            };

            const touched = {
                username: false,
                email: false,
                password: false,
                confirm: false
            };

            function validateEmail(value) {
                const pattern = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
                return pattern.test(value);
            }

            function setError(fieldName, message) {
                const input = fields[fieldName];
                const errEl = errors[fieldName];
                if (message) {
                    input.classList.add('error-field');
                    errEl.textContent = message;
                    errEl.classList.add('visible');
                } else {
                    input.classList.remove('error-field');
                    errEl.textContent = '';
                    errEl.classList.remove('visible');
                }
            }

            function validateField(fieldName) {
                const value = fields[fieldName].value;
                switch (fieldName) {
                    case 'username':
                        if (value.trim() === '') {
                            setError('username', 'Введите имя пользователя');
                            return false;
                        } else {
                            setError('username', '');
                            return true;
                        }
                    case 'email':
                        if (value.trim() === '') {
                            setError('email', 'Введите email');
                            return false;
                        } else if (!validateEmail(value.trim())) {
                            setError('email', 'Некорректный адрес электронной почты');
                            return false;
                        } else {
                            setError('email', '');
                            return true;
                        }
                    case 'password':
                        if (value.length < 5) {
                            setError('password', 'Длина пароля должна быть 5 и более символов');
                            return false;
                        } else {
                            setError('password', '');
                            if (touched.confirm) validateField('confirm');
                            return true;
                        }
                    case 'confirm':
                        const passVal = fields.password.value;
                        if (value === '') {
                            setError('confirm', 'Подтвердите пароль');
                            return false;
                        } else if (value !== passVal) {
                            setError('confirm', 'Пароли не совпадают');
                            return false;
                        } else {
                            setError('confirm', '');
                            return true;
                        }
                    default:
                        return true;
                }
            }

            Object.keys(fields).forEach(name => {
                const fld = fields[name];

                fld.addEventListener('focus', () => {
                    if (!touched[name]) {
                        touched[name] = true;
                    }
                });

                fld.addEventListener('input', () => {
                    if (!touched[name]) touched[name] = true;
                    validateField(name);
                });

                fld.addEventListener('blur', () => {
                    if (!touched[name]) touched[name] = true;
                    validateField(name);
                });
            });

            const toggle1 = document.getElementById("togglePassword1");
            const toggle2 = document.getElementById("togglePassword2");

            function toggleVisibility(icon, input) {
                const hidden = input.type === "password";
                input.type = hidden ? "text" : "password";
                icon.src = hidden ? "assets/images/visibility.svg" : "assets/images/visibility_off.svg";
            }

            if (toggle1) {
                toggle1.addEventListener("click", () => toggleVisibility(toggle1, fields.password));
            }

            if (toggle2) {
                toggle2.addEventListener("click", () => toggleVisibility(toggle2, fields.confirm));
            }

            form.addEventListener('submit', (e) => {
                Object.keys(touched).forEach(k => touched[k] = true);

                const validationResults = Object.keys(fields).map(k => validateField(k));
                const allValid = validationResults.every(v => v === true);

                if (!allValid) {
                    e.preventDefault();
                    for (const k of Object.keys(fields)) {
                        if (fields[k].classList.contains('error-field')) {
                            fields[k].focus();
                            break;
                        }
                    }
                }
            });
        })();

        <?php if (isset($_SESSION['error'])): ?>
            setTimeout(() => {
                alert("<?php echo $_SESSION['error']; ?>");
                <?php unset($_SESSION['error']); ?>
            }, 100);
        <?php endif; ?>
    </script>

    <?php include 'footer.php'; ?>
</body>

</html>
