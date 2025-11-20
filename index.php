<?php
session_start();
require_once __DIR__ . '/config.php';

$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare("
    SELECT q.id, q.title, q.description, q.access_code, q.created_at, q.created_by
    FROM quizzes q
    WHERE q.public = TRUE
    ORDER BY q.created_at DESC
");

$stmt->execute();
$catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);

$months = [
    1 => 'Янв.',
    2 => 'Фев.',
    3 => 'Мар.',
    4 => 'Апр.',
    5 => 'Мая',
    6 => 'Июн.',
    7 => 'Июл.',
    8 => 'Авг.',
    9 => 'Сен.',
    10 => 'Окт.',
    11 => 'Нояб.',
    12 => 'Дек.'
];
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>quizzzApp</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/header_footer.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link href="https://fonts.googleapis.com/css?family=Dela+Gothic+One:regular" rel="stylesheet" />
</head>

<body>
    <?php include 'header.php'; ?>

    <main>
        <section class="section1">
            <h2>Для участия в викторине введите код</h2>
            <form action="start_quiz.php" method="GET">
                <input type="text" name="code" placeholder="123456" required>
                <button type="submit">Играть</button>
            </form>
        </section>
        <h2 class="catalog-title">Каталог викторин</h2>
        <section class="section2">
            <?php if (empty($catalog)): ?>
                <div class="empty-state">
                <p>Пока нет публичных викторин</p>
            </div>
            <?php else: ?>
                <div class="catalog-list">
                    <?php foreach ($catalog as $quiz): ?>
                        <?php
                        $created = strtotime($quiz['created_at']);
                        $date_str = date('d', $created) . ' ' . $months[date('n', $created)] . ' ' . date('Y', $created);

                        $author_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                        $author_stmt->execute([$quiz['created_by']]);
                        $author = $author_stmt->fetch(PDO::FETCH_ASSOC);
                        $author_name = $author ? $author['username'] : 'Неизвестный автор';
                        ?>

                        <div class="catalog-item">
                            <div class="catalog-top">
                                <div class="catalog-date"><?php echo $date_str; ?></div>
                                <div class="catalog-author"><?php echo htmlspecialchars($author_name); ?></div>
                            </div>

                            <h3 class="catalog-name">
                                <?php echo htmlspecialchars($quiz['title']); ?>
                            </h3>

                            <?php if (!empty($quiz['description'])): ?>
                                <p class="catalog-description">
                                    <?php echo nl2br(htmlspecialchars($quiz['description'])); ?>
                                </p>
                            <?php endif; ?>

                            <a href="start_quiz.php?code=<?php echo urlencode($quiz['access_code']); ?>" class="catalog-play-btn">
                                Играть
                            </a>
                        </div>

                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php include 'footer.php'; ?>
</body>

</html>
