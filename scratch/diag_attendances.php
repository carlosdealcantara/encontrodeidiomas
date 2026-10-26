<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: text/plain; charset=utf-8');

$conn = connectDB();
$stmt = $conn->query("SELECT * FROM class_attendances ORDER BY id DESC LIMIT 20");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== CLASS ATTENDANCES ===\n";
foreach ($rows as $r) {
    echo "ID: {$r['id']} | Data: {$r['aula_date']} | Lang: {$r['lang_id']} | Nome: {$r['member_name']} | JID: {$r['member_jid']} | SchedId: {$r['schedule_id']}\n";
}

echo "\n=== MENTORIA_DAILY_SCORES (Ultimos dias) ===\n";
$stmt2 = $conn->query("SELECT * FROM mentoria_daily_scores WHERE dedication_pts >= 20 ORDER BY id DESC LIMIT 20");
$rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows2 as $r) {
    echo "ID: {$r['id']} | Data: {$r['score_date']} | Lang: {$r['lang_id']} | Nome: {$r['member_name']} | JID: {$r['member_jid']} | DedPts: {$r['dedication_pts']}\n";
}

echo "\n=== MENTORIA_CONFIGS (Grupos de Mentoria e Classes) ===\n";
$stmt3 = $conn->query("SELECT id, lang_id, day_of_week, start_time, session_type, is_active FROM class_schedule ORDER BY lang_id, day_of_week, start_time");
$rows3 = $stmt3->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows3 as $r) {
    echo "Sched {$r['id']} | Lang: {$r['lang_id']} | Day: {$r['day_of_week']} | Time: {$r['start_time']} | Type: {$r['session_type']} | Active: {$r['is_active']}\n";
}
