<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? '';
if ($token !== '83x9aZ2pLQw1') {
    http_response_code(403);
    die(json_encode(['error' => 'Acesso negado']));
}

try {
    $conn = connectDB();

    // 1. Idioma Francês
    $stmtLang = $conn->query("SELECT * FROM languages WHERE name LIKE '%Franc%' OR name_en LIKE '%French%'");
    $langs = $stmtLang->fetchAll(PDO::FETCH_ASSOC);

    // 2. Replays recentes de Francês
    $stmtReplays = $conn->query("
        SELECT r.*, l.name as lang_name 
        FROM meetup_replays r 
        JOIN languages l ON r.language_id = l.id 
        WHERE l.name LIKE '%Franc%' OR l.name_en LIKE '%French%' 
        ORDER BY r.id DESC LIMIT 15
    ");
    $replays = $stmtReplays->fetchAll(PDO::FETCH_ASSOC);

    // 3. Queue recente do Odysee de Francês
    $stmtQueue = $conn->query("
        SELECT q.*, l.name as lang_name 
        FROM odysee_publish_queue q 
        JOIN languages l ON q.language_id = l.id 
        WHERE l.name LIKE '%Franc%' OR l.name_en LIKE '%French%' 
        ORDER BY q.id DESC LIMIT 15
    ");
    $queue = $stmtQueue->fetchAll(PDO::FETCH_ASSOC);

    // 4. Meetings de Francês
    $stmtMeetings = $conn->query("
        SELECT m.*, l.name as lang_name 
        FROM meetings m 
        JOIN languages l ON m.language_id = l.id 
        WHERE l.name LIKE '%Franc%' OR l.name_en LIKE '%French%'
    ");
    $meetings = $stmtMeetings->fetchAll(PDO::FETCH_ASSOC);

    // 5. Últimas 10 entradas gerais na fila do Odysee
    $stmtAllQueue = $conn->query("
        SELECT q.id, q.drive_file_name, q.language_id, l.name as lang_name, q.status, q.titulo_final, q.replay_parte, q.semana, q.created_at, q.error_message
        FROM odysee_publish_queue q
        LEFT JOIN languages l ON q.language_id = l.id
        ORDER BY q.id DESC LIMIT 10
    ");
    $allQueue = $stmtAllQueue->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'langs' => $langs,
        'meetings' => $meetings,
        'replays' => $replays,
        'queue_french' => $queue,
        'recent_general_queue' => $allQueue
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
