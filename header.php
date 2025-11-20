<?php
?>
<header>
    <a href="index.php" class="logo">quizzzApp</a>
    <nav>
        <ul>
            <li><a href="play.php">Играть</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="my_quizzes.php">Мои викторины</a></li>
            <?php endif; ?>
            <li><a href="catalog.php">Каталог викторин</a></li>
        </ul>
    </nav>
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="profile-wrapper">
            <button class="profile-btn" id="profileBtn">
                <img src="assets/images/account_circle.svg" alt="Профиль">
            </button>
            <ul class="dropdown" id="profileDropdown">
                <li><a href="profile.php">Личный кабинет</a></li>
                <li><a href="my_quizzes.php">Мои викторины</a></li>
                <li><a href="results.php">Результаты викторин</a></li>
                <li><a href="logout.php">Выход</a></li>
            </ul>
        </div>
    <?php else: ?>
        <a class="login-btn" href="login.php">Войти</a>
    <?php endif; ?>
</header>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const profileBtn = document.getElementById('profileBtn');
        const profileDropdown = document.getElementById('profileDropdown');

        if (profileBtn && profileDropdown) {
            profileBtn.addEventListener('click', function (event) {
                event.stopPropagation();
                profileDropdown.classList.toggle('active');
            });

            document.addEventListener('click', function () {
                profileDropdown.classList.remove('active');
            });

            profileDropdown.addEventListener('click', function (event) {
                event.stopPropagation();
            });
        }
    });
</script>
