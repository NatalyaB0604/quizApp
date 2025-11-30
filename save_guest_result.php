<?php
session_start();
require_once __DIR__ . '/config.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['ok' => false, 'error' => 'no input']);
    exit;
}

$quiz_id = isset($data['quiz_id']) ? (int)$data['quiz_id'] : null;
$score = isset($data['score']) ? (int)$data['score'] : 0;
$time_spent = isset($data['time_spent']) ? (int)$data['time_spent'] : 0;
$answers = $data['answers'] ?? [];

if (!$quiz_id) {
    echo json_encode(['ok' => false, 'error' => 'no quiz id']);
    exit;
}

if (!isset($_SESSION['guest_results'])) {
    $_SESSION['guest_results'] = [];
}

$_SESSION['guest_results'][$quiz_id] = [
    'score' => $score,
    'time_spent' => $time_spent,
    'answers' => $answers,
    'completed_at' => time()
];

echo json_encode(['ok' => true]);
