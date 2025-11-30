<?php
session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT
        q.id AS quiz_id,
        q.title,
        r.score AS best_score,
        r.completed_at,
        r.time_spent,
        (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS total_questions,
        r.id AS result_id
    FROM results r
    JOIN quizzes q ON r.quiz_id = q.id
    WHERE r.user_id = :user_id
      AND r.id = (
        SELECT ri.id
        FROM results ri
        WHERE ri.quiz_id = r.quiz_id
          AND ri.user_id = :user_id
        ORDER BY ri.score DESC, ri.completed_at DESC
        LIMIT 1
      )
    GROUP BY q.id
    ORDER BY r.completed_at DESC
");

$stmt->execute(['user_id' => $user_id]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Мои результаты</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/header_footer.css">
    <link rel="stylesheet" href="assets/css/my_results.css">
    <link href="https://fonts.googleapis.com/css?family=Dela+Gothic+One:regular" rel="stylesheet" />
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="my-results-container">
        <div class="results-header">
            <h2 class="results-title">Мои результаты</h2>
            <p class="results-subtitle">Только лучшие попытки по каждой викторине.</p>
        </div>

        <?php if (empty($results)): ?>
            <div class="empty-state">
                <p>Вы ещё не проходили ни одной викторины</p>
            </div>
        <?php else: ?>
            <section class="results-list-section">
                <div class="results-list">
                    <?php
                    $months = [1=>'Янв.',2=>'Фев.',3=>'Мар.',4=>'Апр.',5=>'Мая',6=>'Июн.',7=>'Июл.',8=>'Авг.',9=>'Сен.',10=>'Окт.',11=>'Нояб.',12=>'Дек.'];
                    foreach ($results as $r):
                        $date = new DateTime($r['completed_at']);
                        $date_str = $date->format('d') . ' ' . $months[(int)$date->format('n')] . ' ' . $date->format('Y');
                        $time_str = $date->format('H:i');

                        $percent = $r['total_questions'] > 0 ? round($r['best_score'] / $r['total_questions'] * 100) : 0;

                        $timeSpent = $r['time_spent'] ? floor($r['time_spent']/60).' мин '.($r['time_spent']%60).' сек' : '—';
                    ?>
                        <div class="result-item">
                            <div class="result-start-info">
                                <span class="result-date"><?php echo $date_str; ?></span>
                                <span class="vertical-bar">|</span>
                                <span class="result-time"><?php echo $time_str; ?></span>
                            </div>

                            <h3 class="result-title"><?php echo htmlspecialchars($r['title']); ?></h3>

                            <div class="result-score">
                                <?php echo $r['best_score']; ?> / <?php echo $r['total_questions']; ?>
                                <div>(<?php echo $percent; ?>%)</div>
                            </div>

                            <div class="result-time-spent">
                                <?php echo $timeSpent; ?>
                            </div>

                            <a href="results.php?quiz_id=<?php echo $r['quiz_id']; ?>&best=1" class="view-details-btn">
                                Подробнее
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>
