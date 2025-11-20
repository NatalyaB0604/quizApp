<?php
session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT username, email, password_hash FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_username = trim($_POST['username'] ?? '');
    $new_email = trim($_POST['email'] ?? '');

    $updates = [];
    $params = [];

    if ($new_username && $new_username !== $user['username']) {
        $updates[] = "username = ?";
        $params[] = $new_username;
    }

    if ($new_email && $new_email !== $user['email']) {
        $updates[] = "email = ?";
        $params[] = $new_email;
    }

    if (!empty($updates)) {
        $params[] = $_SESSION['user_id'];
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        if (isset($new_username) && $new_username !== $user['username']) {
            $_SESSION['username'] = $new_username;
            $user['username'] = $new_username;
        }
        if (isset($new_email) && $new_email !== $user['email']) {
            $user['email'] = $new_email;
        }

        $_SESSION['success_main'] = "Изменения успешно сохранены!";
    }

    header("Location: profile.php#main");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $_SESSION['current_password_value'] = $current;
    $_SESSION['new_password_value'] = $new;
    $_SESSION['confirm_password_value'] = $confirm;

    if (password_verify($current, $user['password_hash'])) {
        if (strlen($new) < 5) {
            $_SESSION['error_pass'] = "Длина пароля должна быть не менее 5 символов";
        } elseif ($new !== $confirm) {
            $_SESSION['error_pass'] = "Пароли не совпадают";
        } else {
            $new_hash = password_hash($new, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $upd->execute([$new_hash, $_SESSION['user_id']]);
            $_SESSION['success_pass'] = "Пароль успешно изменён!";

            unset($_SESSION['current_password_value'], $_SESSION['new_password_value'], $_SESSION['confirm_password_value']);
        }
    } else {
        $_SESSION['error_pass'] = "Неверный текущий пароль";
    }

    header("Location: profile.php#password");
    exit;
}

$current_password_value = $_SESSION['current_password_value'] ?? '';
$new_password_value = $_SESSION['new_password_value'] ?? '';
$confirm_password_value = $_SESSION['confirm_password_value'] ?? '';

if (!isset($_SESSION['error_pass'])) {
    unset($_SESSION['current_password_value'], $_SESSION['new_password_value'], $_SESSION['confirm_password_value']);
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Личный кабинет</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/header_footer.css">
    <link rel="stylesheet" href="assets/css/profile.css">
    <link href="https://fonts.googleapis.com/css?family=Dela+Gothic+One:regular" rel="stylesheet" />
</head>

<body>
    <?php include 'header.php'; ?>

    <main>
        <div class="account-container">
            <div class="account-sidebar">
                <div class="avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
                <p><strong><?= htmlspecialchars($user['username']) ?></strong></p>
                <div class="sidebar-menu">
                    <a href="#main" data-tab="main" class="active" onclick="showTab('main')">Основные настройки</a>
                    <a href="#password" data-tab="password" onclick="showTab('password')">Смена пароля</a>
                </div>
            </div>

            <div class="account-content">

                <div id="main" class="tab-content">
                    <h2>Основные настройки</h2>
                    <form method="post" id="profileForm">
                        <div class="input-group">
                            <input type="text" name="username" id="username"
                                value="<?= htmlspecialchars($user['username']) ?>" disabled
                                placeholder="Имя пользователя*">
                            <img src="assets/images/edit.svg" class="edit-icon" data-field="username">
                        </div>

                        <div class="input-group">
                            <input type="email" name="email" id="email" value="<?= htmlspecialchars($user['email']) ?>"
                                disabled placeholder="Email*">
                            <img src="assets/images/edit.svg" class="edit-icon" data-field="email">
                        </div>

                        <div class="form-actions" id="actionButtons" style="display:none;">
                            <input type="submit" class="update-btn" name="update_profile" value="Сохранить изменения">
                            <button type="button" class="cancel-btn" id="cancelEdit">Отмена</button>
                        </div>
                    </form>
                </div>

                <div id="password" class="tab-content" style="display:none;">
                    <h2>Смена пароля</h2>
                    <form method="post" id="passwordForm">
                        <div class="input-group password-group">
                            <input type="password" name="current_password" id="current_password"
                                value="<?= htmlspecialchars($current_password_value) ?>" required
                                placeholder="Текущий пароль*">
                            <img src="assets/images/visibility_off.svg" class="toggle-password"
                                data-target="current_password">
                        </div>

                        <div class="input-group password-group">
                            <input type="password" name="new_password" id="new_password"
                                value="<?= htmlspecialchars($new_password_value) ?>" required
                                placeholder="Новый пароль*">
                            <img src="assets/images/visibility_off.svg" class="toggle-password"
                                data-target="new_password">
                            <div class="field-error" id="newPasswordError"></div>
                        </div>

                        <div class="input-group password-group">
                            <input type="password" name="confirm_password" id="confirm_password"
                                value="<?= htmlspecialchars($confirm_password_value) ?>" required
                                placeholder="Подтвердите пароль*">
                            <img src="assets/images/visibility_off.svg" class="toggle-password"
                                data-target="confirm_password">
                            <div class="field-error" id="confirmPasswordError"></div>
                        </div>

                        <input type="submit" class="update-btn" name="update_password" value="Обновить пароль">
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            document.getElementById(tab).style.display = 'block';
            document.querySelectorAll('.sidebar-menu a').forEach(el => el.classList.remove('active'));
            document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
            location.hash = tab;
        }

        window.addEventListener('load', () => {
            const tab = location.hash.replace('#', '') || 'main';
            showTab(tab);

            <?php if (isset($_SESSION['success_main'])): ?>
                setTimeout(() => {
                    alert("<?php echo $_SESSION['success_main']; ?>");
                    <?php unset($_SESSION['success_main']); ?>
                }, 100);
            <?php endif; ?>

            <?php if (isset($_SESSION['success_pass'])): ?>
                setTimeout(() => {
                    alert("<?php echo $_SESSION['success_pass']; ?>");
                    <?php unset($_SESSION['success_pass']); ?>
                }, 100);
            <?php endif; ?>

            <?php if (isset($_SESSION['error_pass'])): ?>
                setTimeout(() => {
                    alert("<?php echo $_SESSION['error_pass']; ?>");
                    <?php unset($_SESSION['error_pass']); ?>
                    showTab('password');
                }, 100);
            <?php endif; ?>
        });

        document.querySelectorAll('.edit-icon').forEach(icon => {
            icon.addEventListener('click', () => {
                const field = document.getElementById(icon.dataset.field);
                field.disabled = false;
                field.focus();
                document.getElementById('actionButtons').style.display = 'flex';
            });
        });

        document.getElementById('cancelEdit').addEventListener('click', () => window.location.reload());

        document.querySelectorAll('.toggle-password').forEach(icon => {
            icon.addEventListener('click', () => {
                const input = document.getElementById(icon.dataset.target);
                const hidden = input.type === 'password';
                input.type = hidden ? 'text' : 'password';
                icon.src = hidden ? 'assets/images/visibility.svg' : 'assets/images/visibility_off.svg';
            });
        });

        (function () {
            const form = document.getElementById('passwordForm');
            const newPass = document.getElementById('new_password');
            const confirmPass = document.getElementById('confirm_password');
            const newErr = document.getElementById('newPasswordError');
            const confErr = document.getElementById('confirmPasswordError');

            function setError(el, msg, errEl) {
                el.classList.toggle('error-field', !!msg);
                errEl.textContent = msg || '';
                errEl.classList.toggle('visible', !!msg);
            }

            function validate() {
                let ok = true;

                if (newPass.value.length < 5) {
                    setError(newPass, 'Длина пароля должна быть 5 и более символов', newErr);
                    ok = false;
                } else {
                    setError(newPass, '', newErr);
                }

                if (!confirmPass.value) {
                    setError(confirmPass, 'Подтвердите пароль', confErr);
                    ok = false;
                } else if (confirmPass.value !== newPass.value) {
                    setError(confirmPass, 'Пароли не совпадают', confErr);
                    ok = false;
                } else {
                    setError(confirmPass, '', confErr);
                }

                return ok;
            }

            [newPass, confirmPass].forEach(f => f.addEventListener('input', validate));

            form.addEventListener('submit', e => {
                if (!validate()) {
                    e.preventDefault();
                    alert('Пожалуйста, исправьте ошибки в форме');
                }
            });
        })();
    </script>

    <?php include 'footer.php'; ?>
</body>

</html>
