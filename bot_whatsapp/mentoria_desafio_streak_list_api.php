<?php
/**
 * API: Retorna o Leaderboard de Streaks (All-Time Record e Active)
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$conn = connectDB();

try {
    // Top 5 All-Time Records
    $stmtAllTime = $conn->query("SELECT member_jid, member_name, longest_streak FROM mentoria_desafio_streaks WHERE longest_streak > 0 ORDER BY longest_streak DESC LIMIT 5");
    $allTime = $stmtAllTime->fetchAll(PDO::FETCH_ASSOC);

    // Top 5 Active Streaks (Must be greater than 0)
    $stmtActive = $conn->query("SELECT member_jid, member_name, current_streak FROM mentoria_desafio_streaks WHERE current_streak > 0 ORDER BY current_streak DESC LIMIT 5");
    $active = $stmtActive->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'allTime' => $allTime,
        'active' => $active
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
