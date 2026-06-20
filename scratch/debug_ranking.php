<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

$ontem = '2026-06-18';
$activity = fetchBaileysActivity($ontem);
$config = getMentoriaConfig();

$adminJid = $config['admin_jid'] ?? "556192666148@s.whatsapp.net";
$memberStats = [];
$SCORING_RULES = [
    'pronunciation' => [ ['field' => 'audios_sent', 'pts' => 5, 'emoji' => '🗣️'] ],
    'desafio'       => [ ['field' => 'images_sent', 'pts' => 5, 'emoji' => '📚'] ],
    'music'         => [ ['field' => 'images_sent', 'pts' => 4, 'emoji' => '🎶'] ],
    'games'         => [ ['field' => 'images_sent', 'pts' => 2, 'emoji' => '🧩'] ],
    'vocabulary'    => [ ['field' => 'images_sent', 'pts' => 1, 'emoji' => '📒'] ]
];

foreach ($config['groups'] as $groupKey => $groupData) {
    $groupJid = $groupData['jid'] ?? '';
    if (!$groupJid) continue;
    
    if (isset($activity[$groupJid])) {
        foreach ($activity[$groupJid] as $memberJid => $data) {
            if ($memberJid === $adminJid) continue;
            if (str_ends_with($memberJid, '@g.us')) continue;

            if (isset($SCORING_RULES[$groupKey])) {
                if (!isset($memberStats[$memberJid])) {
                    $memberStats[$memberJid] = ['name' => $data['name'] ?? 'Unknown', 'total_pts' => 0, 'emojis' => []];
                }
                
                foreach ($SCORING_RULES[$groupKey] as $rule) {
                    $field = $rule['field'];
                    if (!empty($data[$field]) && $data[$field] > 0) {
                        $memberStats[$memberJid]['total_pts'] += $rule['pts'];
                        $memberStats[$memberJid]['emojis'][] = $rule['emoji'];
                    }
                }
            }
        }
    }
}

$stmt = $conn->prepare("SELECT member_jid, member_name FROM class_attendances WHERE aula_date = ?");
$stmt->execute([$ontem]);
$attendances = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($attendances as $att) {
    $memberJid = $att['member_jid'];
    if (str_ends_with($memberJid, '@g.us')) continue;
    
    if (!isset($memberStats[$memberJid])) {
        $memberStats[$memberJid] = ['name' => $att['member_name'], 'total_pts' => 0, 'emojis' => []];
    }
    $memberStats[$memberJid]['total_pts'] += 20; // 20 pts pela aula
    $memberStats[$memberJid]['emojis'][] = '🖥️';
}

$memberStats = array_filter($memberStats, fn($m) => $m['total_pts'] > 0);
uasort($memberStats, fn($a, $b) => $b['total_pts'] <=> $a['total_pts']);

echo json_encode($memberStats, JSON_PRETTY_PRINT);
