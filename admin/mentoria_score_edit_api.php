<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Aceita action via GET ou via JSON body
$input = json_decode(file_get_contents('php://input'), true) ?? [];
if (!isset($input['action'])) {
    $input['action'] = $_GET['action'] ?? '';
}
if (empty($input['action'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$action = $input['action'];
$hoje = date('Y-m-d');
$conn = connectDB();

try {
    if ($action === 'load') {
        $config = getMentoriaConfig();
        
        // Chaves exatas como estão no mentoria_config.json
        $jidDesafio      = $config['groups']['desafio']['jid']      ?? '';
        $jidMusic        = $config['groups']['music']['jid']         ?? '';
        $jidVocabulary   = $config['groups']['vocabulary']['jid']    ?? '';
        $jidGames        = $config['groups']['games']['jid']         ?? '';
        $jidPronunciation= $config['groups']['pronunciation']['jid'] ?? '';

        // Atividade bruta do dia
        $activity = fetchBaileysActivity($hoje);

        // Quem confirmou presença na aula hoje
        $stmt = $conn->prepare("SELECT member_jid, member_name FROM class_attendances WHERE aula_date = ?");
        $stmt->execute([$hoje]);
        $attendeesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $attendeeJids = array_column($attendeesRaw, 'member_jid');

        $students = [];

        foreach ($activity as $groupJid => $members) {
            foreach ($members as $memberJid => $stats) {
                if (!isset($students[$memberJid])) {
                    $students[$memberJid] = [
                        'jid'            => $memberJid,
                        'name'           => $stats['name'] ?? 'Unknown',
                        'class_attended' => in_array($memberJid, $attendeeJids),
                        'pronun'         => 0,
                        'desafio'        => 0,
                        'music'          => 0,
                        'games'          => 0,
                        'vocab'          => 0,
                    ];
                }

                // Mapeia cada grupo para a coluna certa
                if ($groupJid === $jidPronunciation) {
                    // pronun = áudios no grupo de pronúncia
                    $students[$memberJid]['pronun'] += (int)($stats['audios_sent'] ?? 0);
                } elseif ($groupJid === $jidDesafio) {
                    $students[$memberJid]['desafio'] += (int)($stats['images_sent'] ?? 0);
                } elseif ($groupJid === $jidMusic) {
                    $students[$memberJid]['music'] += (int)($stats['images_sent'] ?? 0);
                } elseif ($groupJid === $jidGames) {
                    $students[$memberJid]['games'] += (int)($stats['images_sent'] ?? 0);
                } elseif ($groupJid === $jidVocabulary) {
                    $students[$memberJid]['vocab'] += (int)($stats['images_sent'] ?? 0);
                }
                // mensagens de outros grupos (our_classes, the_lounge) não geram colunas aqui
            }
        }

        // Adiciona quem confirmou presença mas não teve atividade em nenhum grupo
        foreach ($attendeesRaw as $att) {
            $jid = $att['member_jid'];
            if (!isset($students[$jid])) {
                $students[$jid] = [
                    'jid'            => $jid,
                    'name'           => $att['member_name'],
                    'class_attended' => true,
                    'pronun'         => 0,
                    'desafio'        => 0,
                    'music'          => 0,
                    'games'          => 0,
                    'vocab'          => 0,
                ];
            }
        }

        echo json_encode([
            'success'  => true,
            'today'    => $hoje,
            'students' => array_values($students),
        ]);

    } elseif ($action === 'toggle_attendance') {
        $memberJid = $input['member_jid'] ?? '';
        $attended  = (bool)($input['attended'] ?? false);

        if ($attended) {
            $diaSemana = date('N');
            $stmt = $conn->prepare("SELECT id FROM class_schedule WHERE day_of_week = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$diaSemana]);
            $scheduleId = $stmt->fetchColumn();

            if ($scheduleId) {
                // Tenta recuperar o nome do aluno pelo activity_log
                $activity = fetchBaileysActivity($hoje);
                $name = 'Student';
                foreach ($activity as $members) {
                    if (isset($members[$memberJid]['name'])) {
                        $name = $members[$memberJid]['name'];
                        break;
                    }
                }
                $ins = $conn->prepare("INSERT IGNORE INTO class_attendances (schedule_id, member_jid, member_name, aula_date) VALUES (?, ?, ?, ?)");
                $ins->execute([$scheduleId, $memberJid, $name, $hoje]);
            }
        } else {
            $del = $conn->prepare("DELETE FROM class_attendances WHERE member_jid = ? AND aula_date = ?");
            $del->execute([$memberJid, $hoje]);
        }
        echo json_encode(['success' => true]);

    } elseif ($action === 'edit_activity') {
        $memberJid = $input['member_jid'] ?? '';
        $type      = $input['type'] ?? '';
        $value     = (int)($input['value'] ?? 0);

        $config = getMentoriaConfig();

        $map = [
            'pronun'  => ['group_key' => 'pronunciation', 'field' => 'audios_sent'],
            'desafio' => ['group_key' => 'desafio',       'field' => 'images_sent'],
            'music'   => ['group_key' => 'music',         'field' => 'images_sent'],
            'games'   => ['group_key' => 'games',         'field' => 'images_sent'],
            'vocab'   => ['group_key' => 'vocabulary',    'field' => 'images_sent'],
        ];

        if (!isset($map[$type])) {
            echo json_encode(['success' => false, 'error' => 'Unknown type']);
            exit;
        }

        $groupKey = $map[$type]['group_key'];
        $field    = $map[$type]['field'];
        $groupJid = $config['groups'][$groupKey]['jid'] ?? '';

        if (empty($groupJid)) {
            echo json_encode(['success' => false, 'error' => "Grupo '$groupKey' não configurado."]);
            exit;
        }

        $payload = [
            'apikey'     => 'SenhaMeetups2026',
            'date'       => $hoje,
            'group_jid'  => $groupJid,
            'member_jid' => $memberJid,
            'field'      => $field,
            'value'      => $value,
        ];

        $ch = curl_init(BAILEYS_API_URL_DIRECT . '/mentoria-edit-activity');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        $res      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => "Node API Error: $httpCode — $res"]);
        }
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
