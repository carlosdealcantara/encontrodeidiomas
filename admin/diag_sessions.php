<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? '';
if ($token !== '83x9aZ2pLQw1') {
    http_response_code(403);
    die(json_encode(['error' => 'Acesso negado']));
}

$conn = connectDB();

$hasSessions = false;
try {
    $res = $conn->query("SHOW TABLES LIKE 'meeting_sessions'")->fetch();
    $hasSessions = !empty($res);
} catch (Exception $e) {}

$meetings = $conn->query("SELECT id, title, language_id, day_of_week, time_hour, active FROM meetings WHERE active=1")->fetchAll(PDO::FETCH_ASSOC);
$sessions = [];
if ($hasSessions) {
    $sessions = $conn->query("SELECT * FROM meeting_sessions WHERE active=1")->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode([
    'has_sessions' => $hasSessions,
    'meetings' => $meetings,
    'sessions' => $sessions
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
