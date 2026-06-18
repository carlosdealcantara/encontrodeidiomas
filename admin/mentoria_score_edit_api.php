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

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['action'])) {
    if ($_GET['action'] === 'load') {
        $input = ['action' => 'load'];
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }
}

$action = $input['action'];
$hoje = date('Y-m-d');
$conn = connectDB();

try {
    if ($action === 'load') {
        // Obter configurações de grupo para saber os JIDs
        $config = getMentoriaConfig();
        $groupClasses = $config['groups']['our_classes']['jid'] ?? '';
        $groupDesafio = $config['groups']['desafio']['jid'] ?? '';
        $groupMusic   = $config['groups']['music_club']['jid'] ?? '';
        $groupGames   = $config['groups']['games']['jid'] ?? '';
        $groupVocab   = $config['groups']['new_word']['jid'] ?? '';

        // Obter atividade do dia
        $activity = fetchBaileysActivity($hoje);
        
        // Obter alunos presentes na aula
        $stmt = $conn->prepare("SELECT member_jid, member_name FROM class_attendances WHERE aula_date = ?");
        $stmt->execute([$hoje]);
        $attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $students = [];
        
        // Juntar tudo
        // Primeiro, adiciona todos que tiveram atividade
        foreach ($activity as $groupJid => $members) {
            foreach ($members as $memberJid => $stats) {
                if (!isset($students[$memberJid])) {
                    $students[$memberJid] = [
                        'jid' => $memberJid,
                        'name' => $stats['name'] ?? 'Unknown',
                        'class_attended' => false,
                        'audios' => 0,
                        'desafio' => 0,
                        'music' => 0,
                        'games' => 0,
                        'vocab' => 0
                    ];
                }
                
                // Mapeia de acordo com o grupo
                if ($groupJid === $groupDesafio) {
                    $students[$memberJid]['desafio'] = $stats['images_sent'] ?? 0;
                } elseif ($groupJid === $groupMusic) {
                    $students[$memberJid]['music'] = $stats['images_sent'] ?? 0;
                } elseif ($groupJid === $groupGames) {
                    $students[$memberJid]['games'] = $stats['images_sent'] ?? 0;
                } elseif ($groupJid === $groupVocab) {
                    $students[$memberJid]['vocab'] = $stats['images_sent'] ?? 0;
                }
                
                // Audios conta em qualquer grupo
                $students[$memberJid]['audios'] += $stats['audios_sent'] ?? 0;
            }
        }
        
        // Adiciona quem confirmou presença mas não falou nada
        foreach ($attendees as $att) {
            $jid = $att['member_jid'];
            if (!isset($students[$jid])) {
                $students[$jid] = [
                    'jid' => $jid,
                    'name' => $att['member_name'],
                    'class_attended' => false,
                    'audios' => 0,
                    'desafio' => 0,
                    'music' => 0,
                    'games' => 0,
                    'vocab' => 0
                ];
            }
            $students[$jid]['class_attended'] = true;
        }

        echo json_encode([
            'success' => true,
            'today' => $hoje,
            'students' => array_values($students)
        ]);
        
    } elseif ($action === 'toggle_attendance') {
        $memberJid = $input['member_jid'];
        $attended = $input['attended'];
        
        if ($attended) {
            // Find schedule for today (to insert)
            $diaSemana = date('N');
            $stmt = $conn->prepare("SELECT id FROM class_schedule WHERE day_of_week = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$diaSemana]);
            $scheduleId = $stmt->fetchColumn();
            
            if ($scheduleId) {
                // Name? Try to find from other table or just 'Student'
                $name = "Student";
                $ins = $conn->prepare("INSERT IGNORE INTO class_attendances (schedule_id, member_jid, member_name, aula_date) VALUES (?, ?, ?, ?)");
                $ins->execute([$scheduleId, $memberJid, $name, $hoje]);
            }
        } else {
            // Remove from class_attendances
            $del = $conn->prepare("DELETE FROM class_attendances WHERE member_jid = ? AND aula_date = ?");
            $del->execute([$memberJid, $hoje]);
        }
        echo json_encode(['success' => true]);

    } elseif ($action === 'edit_activity') {
        $memberJid = $input['member_jid'];
        $type = $input['type'];
        $value = (int)$input['value'];
        
        $config = getMentoriaConfig();
        $groupJid = '';
        $field = 'images_sent';
        
        if ($type === 'audios') {
            $groupJid = $config['groups']['our_classes']['jid'] ?? ''; // audios is counted anywhere, but lets use our_classes for the edit request
            $field = 'audios_sent';
        } elseif ($type === 'desafio') {
            $groupJid = $config['groups']['desafio']['jid'] ?? '';
        } elseif ($type === 'music') {
            $groupJid = $config['groups']['music_club']['jid'] ?? '';
        } elseif ($type === 'games') {
            $groupJid = $config['groups']['games']['jid'] ?? '';
        } elseif ($type === 'vocab') {
            $groupJid = $config['groups']['new_word']['jid'] ?? '';
        }

        if (empty($groupJid)) {
            echo json_encode(['success' => false, 'error' => 'Group not configured']);
            exit;
        }

        // Envia para o Node.js via API local
        $payload = [
            'apikey' => 'SenhaMeetups2026',
            'date' => $hoje,
            'group_jid' => $groupJid,
            'member_jid' => $memberJid,
            'field' => $field,
            'value' => $value
        ];

        // A API REST está em /mentoria-edit-activity
        $ch = curl_init(BAILEYS_API_URL_DIRECT . '/mentoria-edit-activity');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => "Node API Error: $httpCode - $res"]);
        }
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
