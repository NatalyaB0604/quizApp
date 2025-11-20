<?php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$db = new Database();
$conn = $db->getConnection();

$data = json_decode(file_get_contents('php://input'), true);
if(!$data){ echo json_encode(['ok'=>false,'error'=>'no input']); exit; }

$quiz_id = isset($data['quiz_id']) ? (int)$data['quiz_id'] : null;
$user_id = isset($data['user_id']) ? (int)$data['user_id'] : null;
$score = isset($data['score']) ? (int)$data['score'] : 0;
$answers = $data['answers'] ?? []; // question_id => [answer_id,...]

if(!$user_id){
    echo json_encode(['ok'=>false,'saved'=>false,'message'=>'guest_not_saved']);
    exit;
}

try{
    $stmt = $conn->prepare("INSERT INTO results (quiz_id,user_id,score) VALUES (?,?,?)");
    $stmt->execute([$quiz_id,$user_id,$score]);
    $result_id = $conn->lastInsertId();

    $ua_stmt = $conn->prepare("INSERT INTO user_answers (result_id, question_id, answer_id) VALUES (?,?,?)");
    foreach($answers as $question_id => $answer_ids){
        foreach($answer_ids as $aid){
            $ua_stmt->execute([$result_id,$question_id,$aid]);
        }
    }

    echo json_encode(['ok'=>true,'saved'=>true]);
}catch(Exception $e){
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
