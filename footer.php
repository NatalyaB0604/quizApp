<footer>
    <ul>
        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin'): ?>
            <li><a href="moderation.php">Модерация</a></li>
            <li><a href="catalog.php">Каталог викторин</a></li>
        <?php elseif (isset($_SESSION['user_id'])): ?>
            <li><a href="play.php">Играть</a></li>
            <li><a href="my_quizzes.php">Мои викторины</a></li>
            <li><a href="catalog.php">Каталог викторин</a></li>
        <?php else: ?>
            <li><a href="play.php">Играть</a></li>
            <li><a href="catalog.php">Каталог викторин</a></li>
        <?php endif; ?>
    </ul>
</footer>
