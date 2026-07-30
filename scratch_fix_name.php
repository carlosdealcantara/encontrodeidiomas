<?php
require_once __DIR__ . '/config.php';
$conn = connectDB();
// Encontra registros Unknown hoje
$hoje = date('Y-m-d');
$stmt = $conn->prepare("SELECT * FROM mentoria_dedicated_pts WHERE date = ? AND member_name = 'Unknown'");
$stmt->execute([$hoje]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $memberJid = $row['member_jid'];
    
    // Tenta achar em class_attendances
    $stmtN = $conn->prepare("SELECT member_name FROM class_attendances WHERE member_jid = ? AND member_name != 'Desconhecido' AND member_name IS NOT NULL ORDER BY id DESC LIMIT 1");
    $stmtN->execute([$memberJid]);
    $found = $stmtN->fetchColumn();
    
    if (!$found) {
        $stmtN2 = $conn->prepare("SELECT member_name FROM mentoria_desafio_streaks WHERE member_jid = ? AND member_name != 'Desconhecido' AND member_name IS NOT NULL LIMIT 1");
        $stmtN2->execute([$memberJid]);
        $found = $stmtN2->fetchColumn();
    }
    
    if ($found) {
        $update = $conn->prepare("UPDATE mentoria_dedicated_pts SET member_name = ? WHERE id = ?");
        $update->execute([$found, $row['id']]);
        echo "Updated ID {$row['id']} to name $found<br>";
    } else {
        echo "Could not find name for JID $memberJid<br>";
    }
}
echo "Done.";
