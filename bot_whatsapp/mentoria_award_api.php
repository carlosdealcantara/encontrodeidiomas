<?php
/**
 * API: Atribuição Manual de Pontos pelo Admin
 * Chamada pelo bot Node.js ao receber comandos !1 a !5
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['group_jid']) || !isset($data['member_jid']) || !isset($data['points']) || !isset($data['level'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$groupJid = $data['group_jid'];
$groupKey = $data['group_key'] ?? 'unknown';
$memberJid = $data['member_jid'];
$memberName = $data['member_name'] ?? 'Unknown';
$points = (int)$data['points'];
$level = (int)$data['level'];
// Date in BRT
$hoje = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');

$conn = connectDB();

try {
    // Garantir que a tabela existe
    $conn->exec("
    CREATE TABLE IF NOT EXISTS mentoria_dedicated_pts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        group_jid VARCHAR(100) NOT NULL,
        group_key VARCHAR(50) NOT NULL,
        member_jid VARCHAR(100) NOT NULL,
        member_name VARCHAR(255) NULL,
        points INT NOT NULL,
        level TINYINT NOT NULL,
        awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_date_member (date, member_jid)
    )");

    $stmt = $conn->prepare("
        INSERT INTO mentoria_dedicated_pts (date, group_jid, group_key, member_jid, member_name, points, level)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$hoje, $groupJid, $groupKey, $memberJid, $memberName, $points, $level]);

    echo json_encode([
        'success' => true,
        'points' => $points,
        'level' => $level,
        'member_name' => $memberName,
        'group_key' => $groupKey
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
