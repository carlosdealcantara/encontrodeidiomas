<?php
header('Content-Type: text/plain');
require '../config.php';
try {
    // Check German hosts and meetings
    $stmt = $conn->query("
        SELECT h.id, h.full_name, h.status, h.languages, h.category
        FROM hosts h
        WHERE h.languages LIKE '%Alemão%'
        ORDER BY h.status
    ");
    $hosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "=== Hosts with Alemão ===\n";
    foreach ($hosts as $h) {
        echo "ID:{$h['id']} | {$h['full_name']} | status:{$h['status']} | langs:{$h['languages']} | cat:{$h['category']}\n";
    }
    
    echo "\n=== Meetings for Alemão (language_id=5) ===\n";
    $stmt2 = $conn->query("
        SELECT m.id, m.day_of_week, m.time_hour, m.host_id, h.full_name as host_name, h.status as host_status
        FROM meetings m
        LEFT JOIN hosts h ON m.host_id = h.id
        WHERE m.language_id = 5 AND m.active = 1
    ");
    $meetings = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    foreach ($meetings as $m) {
        echo "MeetID:{$m['id']} | day:{$m['day_of_week']} | time:{$m['time_hour']} | host_id:{$m['host_id']} | host:{$m['host_name']} | host_status:{$m['host_status']}\n";
    }
    
    echo "\n=== Chinese language (id=8) flag_emoji ===\n";
    $stmt3 = $conn->query("SELECT id, name, flag_emoji FROM languages WHERE id=8");
    print_r($stmt3->fetch(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
