<?php
require_once __DIR__ . '/../config.php';

$activityFile = '/home/ubuntu/encontrodeidiomas/baileys-server/data/activity_log.json';
if (!file_exists($activityFile)) die("activity_log.json not found");

$json = file_get_contents($activityFile);
$activity = json_decode($json, true);

$conn = connectDB();
$changed = false;

foreach ($activity as $date => &$groups) {
    foreach ($groups as $groupJid => &$members) {
        foreach ($members as $memberJid => &$data) {
            if (empty($data['name']) || $data['name'] === 'Unknown' || $data['name'] === 'Desconhecido') {
                $stmt = $conn->prepare("SELECT member_name FROM mentoria_alunos WHERE member_jid = ? LIMIT 1");
                $stmt->execute([$memberJid]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['member_name'])) {
                    $data['name'] = $row['member_name'];
                    $changed = true;
                } else {
                    $stmt2 = $conn->prepare("SELECT member_name FROM mentoria_desafio_streaks WHERE member_jid = ? LIMIT 1");
                    $stmt2->execute([$memberJid]);
                    $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                    if ($row2 && !empty($row2['member_name'])) {
                        $data['name'] = $row2['member_name'];
                        $changed = true;
                    }
                }
            }
        }
    }
}

if ($changed) {
    file_put_contents($activityFile, json_encode($activity, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Fixed Unknown names in activity_log.json\n";
} else {
    echo "No Unknown names needed fixing\n";
}
