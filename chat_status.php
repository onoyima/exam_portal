<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

$now = time();
$statuses = [];
$examiners = $db->query("SELECT id FROM examiners")->fetchAll(PDO::FETCH_ASSOC);
foreach ($examiners as $ex) {
    $id = $ex['id'];
    // Get last message or last activity (if you have a user_activity table, use it; else, use chat_messages)
    $stmt = $db->prepare("SELECT MAX(created_at) as last_time FROM chat_messages WHERE sender_id=?");
    $stmt->execute([$id]);
    $last = $stmt->fetchColumn();
    $online = false;
    if ($last) {
        $last_ts = strtotime($last);
        if ($now - $last_ts < 120) $online = true;
    }
    $statuses[] = ['id' => $id, 'online' => $online];
}
echo json_encode(['statuses' => $statuses]); 