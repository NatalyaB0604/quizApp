<footer>
    <ul>
        <li><a href="play.php">Играть</a></li>
        <?php if (isset($_SESSION['user_id'])): ?>
            <li><a href="my_quizzes.php">Мои викторины</a></li>
        <?php endif; ?>
        <li><a href="catalog.php">Каталог викторин</a></li>
    </ul>
</footer>
