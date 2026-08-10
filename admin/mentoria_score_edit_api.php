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

$input = json_decode(file_get_contents('php://input'), true) ?? [];
if (!isset($input['action'])) {
    $input['action'] = $_GET['action'] ?? '';
}
if (empty($input['action'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$action = $input['action'];
$hoje   = date('Y-m-d');
$conn   = connectDB();

// Nomes amigáveis para os grupos
const GROUP_LABELS = [
    'our_classes'   => 'Our Classes',
    'the_lounge'    => 'The Lounge',
    'desafio'       => 'Desafio',
    'pronunciation' => 'Pronún.',
    'music'         => 'Music Lab',
    'vocabulary'    => 'Vocab.',
    'games'         => 'Games',
    'homework'      => 'Homework',
];

try {
    // ────────────────────────────────────────────────────
    // ACTION: load
    // ────────────────────────────────────────────────────
    if ($action === 'load') {
        $config   = getMentoriaConfig();
        $adminJid = $config['admin_jid'] ?? '';

        // Monta lista ordenada de grupos com JID e nome amigável
        $groupsOrdered = [];
        foreach ($config['groups'] ?? [] as $key => $gData) {
            if (!empty($gData['jid'])) {
                $groupsOrdered[] = [
                    'jid'  => $gData['jid'],
                    'key'  => $key,
                    'name' => GROUP_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key)),
                ];
            }
        }

        // JIDs para a seção de Atividades
        $jidPronun = $config['groups']['pronunciation']['jid'] ?? '';
        $jidDesafio = $config['groups']['desafio']['jid']      ?? '';
        $jidMusic   = $config['groups']['music']['jid']         ?? '';
        $jidGames   = $config['groups']['games']['jid']         ?? '';
        $jidVocab   = $config['groups']['vocabulary']['jid']    ?? '';

        $activity = fetchBaileysActivity($hoje);

        // Presença na aula: conta quantas sessões cada aluno confirmou
        $stmt = $conn->prepare("
            SELECT member_jid, member_name, COUNT(*) as session_count
            FROM class_attendances
            WHERE aula_date = ?
            GROUP BY member_jid
        ");
        $stmt->execute([$hoje]);
        $attendeesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Map jid -> count
        $attendeeCount = [];
        foreach ($attendeesRaw as $row) {
            $attendeeCount[$row['member_jid']] = (int)$row['session_count'];
        }

        $students = [];  // seção Atividades
        $socialMap = []; // seção Social (por membro → por grupo)

        foreach ($activity as $groupJid => $members) {
            foreach ($members as $memberJid => $stats) {
                if ($memberJid === $adminJid) continue;
                if (str_ends_with($memberJid, '@g.us')) continue;

                $name = $stats['name'] ?? 'Unknown';
                if ($name === 'Unknown' || trim($name) === '') {
                    $stmtName = $conn->prepare("SELECT member_name FROM mentoria_desafio_streaks WHERE member_jid = ? LIMIT 1");
                    $stmtName->execute([$memberJid]);
                    $rowName = $stmtName->fetch(PDO::FETCH_ASSOC);
                    if ($rowName && !empty($rowName['member_name'])) {
                        $name = $rowName['member_name'];
                    }
                }
                
                // Ignora o perfil da Staff mesmo se ele usou um device linkado diferente
                if (stripos($name, 'Staff') !== false) continue;

                // --- Seção ATIVIDADES ---
                if (!isset($students[$memberJid])) {
                    $students[$memberJid] = [
                        'jid'               => $memberJid,
                        'name'              => $name,
                        'class_count'       => $attendeeCount[$memberJid] ?? 0,
                        'pronun'            => 0,
                        'desafio'           => 0,
                        'music'             => 0,
                        'games'             => 0,
                        'vocab'             => 0,
                        'manual_pts'        => [],
                    ];
                }
                if ($groupJid === $jidPronun)   $students[$memberJid]['pronun']  += (int)($stats['audios_sent']  ?? 0);
                if ($groupJid === $jidDesafio)  $students[$memberJid]['desafio'] += (int)($stats['images_sent'] ?? 0);
                if ($groupJid === $jidMusic)    $students[$memberJid]['music']   += (int)($stats['images_sent'] ?? 0);
                if ($groupJid === $jidGames)    $students[$memberJid]['games']   += (int)($stats['images_sent'] ?? 0);
                if ($groupJid === $jidVocab)    $students[$memberJid]['vocab']   += (int)($stats['images_sent'] ?? 0);

                // --- Seção SOCIAL ---
                if (!isset($socialMap[$memberJid])) {
                    $socialMap[$memberJid] = ['jid' => $memberJid, 'name' => $name, 'by_group' => []];
                }
                $socialMap[$memberJid]['by_group'][$groupJid] = [
                    'messages'        => (int)($stats['messages']        ?? 0),
                    'images_sent'     => (int)($stats['images_sent']     ?? 0),
                    'audios_sent'     => (int)($stats['audios_sent']     ?? 0),
                    'reactions_given' => (int)($stats['reactions_given'] ?? 0),
                ];
            }
        }

        // Adiciona alunos que confirmaram presença mas não tiveram atividade
        foreach ($attendeesRaw as $att) {
            $jid = $att['member_jid'];
            if (!isset($students[$jid])) {
                $students[$jid] = [
                    'jid' => $jid, 'name' => $att['member_name'],
                    'class_attended' => true,
                    'pronun' => 0, 'desafio' => 0, 'music' => 0, 'games' => 0, 'vocab' => 0,
                    'manual_pts' => [],
                ];
            }
        }

        // Busca pontos manuais
        $stmtPts = $conn->prepare("
            SELECT member_jid, member_name, group_key, SUM(points) as group_pts
            FROM mentoria_dedicated_pts
            WHERE date = ?
            GROUP BY member_jid, group_key
        ");
        $stmtPts->execute([$hoje]);
        $manualPoints = $stmtPts->fetchAll(PDO::FETCH_ASSOC);

        foreach ($manualPoints as $row) {
            $jid = $row['member_jid'];
            $cleanMJid = preg_replace('/:\d+@/', '@', $jid);
            if ($cleanMJid === preg_replace('/:\d+@/', '@', $adminJid)) continue;
            
            $mName = $row['member_name'] ?: 'Unknown';
            if (stripos($mName, 'Staff') !== false || stripos($mName, 'Test') !== false) continue;

            if (!isset($students[$jid])) {
                if ($mName === 'Unknown') {
                    $stmtName = $conn->prepare("SELECT nome FROM mentoria_alunos WHERE telefone = ? LIMIT 1");
                    $phoneOnly = preg_replace('/\D/', '', explode('@', $jid)[0]);
                    $stmtName->execute([$phoneOnly]);
                    $rowName = $stmtName->fetch(PDO::FETCH_ASSOC);
                    if ($rowName) $mName = $rowName['nome'];
                }
                
                $students[$jid] = [
                    'jid'          => $jid,
                    'name'         => $mName,
                    'class_count'  => $attendeeCount[$jid] ?? 0,
                    'pronun'       => 0,
                    'desafio'      => 0,
                    'music'        => 0,
                    'games'        => 0,
                    'vocab'        => 0,
                    'manual_pts'   => [],
                ];
            }
            if (!isset($students[$jid]['manual_pts'])) {
                $students[$jid]['manual_pts'] = [];
            }
            $students[$jid]['manual_pts'][$row['group_key']] = (int)$row['group_pts'];
        }

        // Calcula totais do Social e transforma em array
        $social = [];
        foreach ($socialMap as $jid => $s) {
            $totalInteractions = 0;
            $totalReactions    = 0;
            foreach ($s['by_group'] as $g) {
                $totalInteractions += $g['messages'] + $g['images_sent'] + $g['audios_sent'];
                $totalReactions    += $g['reactions_given'];
            }
            $s['total_interactions'] = $totalInteractions;
            $s['total_reactions']    = $totalReactions;
            $social[] = $s;
        }

        // Ordena social por total de interações desc
        usort($social, fn($a, $b) => $b['total_interactions'] <=> $a['total_interactions']);

        echo json_encode([
            'success'        => true,
            'today'          => $hoje,
            'students'       => array_values($students),
            'groups_ordered' => $groupsOrdered,
            'social'         => $social,
        ]);

    // ────────────────────────────────────────────────────
    // ACTION: set_attendance_count
    // Ajusta a quantidade de sessões confirmadas de um aluno
    // ────────────────────────────────────────────────────
    } elseif ($action === 'set_attendance_count') {
        $memberJid    = $input['member_jid'] ?? '';
        $desiredCount = max(0, (int)($input['count'] ?? 0));

        if (empty($memberJid)) {
            echo json_encode(['success' => false, 'error' => 'member_jid required']);
            exit;
        }

        // Conta quantas linhas existem hoje
        $stmtCount = $conn->prepare("SELECT COUNT(*) FROM class_attendances WHERE member_jid = ? AND aula_date = ?");
        $stmtCount->execute([$memberJid, $hoje]);
        $current = (int)$stmtCount->fetchColumn();

        if ($desiredCount > $current) {
            // Precisa adicionar linhas
            // Busca um schedule_id válido para hoje
            $diaSemana = date('N');
            $stmtSched = $conn->prepare("SELECT id FROM class_schedule WHERE day_of_week = ? AND is_active = 1");
            $stmtSched->execute([$diaSemana]);
            $schedules = $stmtSched->fetchAll(PDO::FETCH_COLUMN);

            // Pega o nome do aluno
            $activity = fetchBaileysActivity($hoje);
            $name = 'Student';
            foreach ($activity as $members) {
                if (isset($members[$memberJid]['name'])) { $name = $members[$memberJid]['name']; break; }
            }

            $toAdd = $desiredCount - $current;
            $schedIdx = 0;
            for ($i = 0; $i < $toAdd; $i++) {
                // Usa schedule IDs disponíveis em rotação, ou o mesmo se houver apenas 1
                $scheduleId = $schedules[$schedIdx % count($schedules)] ?? null;
                if (!$scheduleId) break;
                // Usa INSERT (não IGNORE) para permitir múltiplas linhas por aluno
                $ins = $conn->prepare("INSERT INTO class_attendances (schedule_id, member_jid, member_name, aula_date) VALUES (?, ?, ?, ?)");
                $ins->execute([$scheduleId, $memberJid, $name, $hoje]);
                $schedIdx++;
            }
        } elseif ($desiredCount < $current) {
            // Precisa remover o excesso (remove as mais recentes)
            $toRemove = $current - $desiredCount;
            $del = $conn->prepare("DELETE FROM class_attendances WHERE member_jid = ? AND aula_date = ? ORDER BY id DESC LIMIT $toRemove");
            $del->execute([$memberJid, $hoje]);
        }
        // Confirma o total final
        $stmtFinal = $conn->prepare("SELECT COUNT(*) FROM class_attendances WHERE member_jid = ? AND aula_date = ?");
        $stmtFinal->execute([$memberJid, $hoje]);
        $finalCount = (int)$stmtFinal->fetchColumn();
        echo json_encode(['success' => true, 'count' => $finalCount, 'pts' => $finalCount * 20]);

    // ────────────────────────────────────────────────────
    // ACTION: toggle_attendance  (mantido por compatibilidade legada)
    // ────────────────────────────────────────────────────
    } elseif ($action === 'toggle_attendance') {
        $memberJid = $input['member_jid'] ?? '';
        $attended  = (bool)($input['attended'] ?? false);

        if ($attended) {
            $diaSemana = date('N');
            $stmt = $conn->prepare("SELECT id FROM class_schedule WHERE day_of_week = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$diaSemana]);
            $scheduleId = $stmt->fetchColumn();

            if ($scheduleId) {
                $activity = fetchBaileysActivity($hoje);
                $name = 'Student';
                foreach ($activity as $members) {
                    if (isset($members[$memberJid]['name'])) { $name = $members[$memberJid]['name']; break; }
                }
                $ins = $conn->prepare("INSERT IGNORE INTO class_attendances (schedule_id, member_jid, member_name, aula_date) VALUES (?, ?, ?, ?)");
                $ins->execute([$scheduleId, $memberJid, $name, $hoje]);
            }
        } else {
            $del = $conn->prepare("DELETE FROM class_attendances WHERE member_jid = ? AND aula_date = ?");
            $del->execute([$memberJid, $hoje]);
        }
        echo json_encode(['success' => true]);

    // ────────────────────────────────────────────────────
    // ACTION: edit_activity  (seção de Atividades)
    // ────────────────────────────────────────────────────
    } elseif ($action === 'edit_activity') {
        $memberJid = $input['member_jid'] ?? '';
        $type      = $input['type']       ?? '';
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
            echo json_encode(['success' => false, 'error' => 'Unknown activity type']);
            exit;
        }
        $groupKey = $map[$type]['group_key'];
        $field    = $map[$type]['field'];
        $groupJid = $config['groups'][$groupKey]['jid'] ?? '';

        if (empty($groupJid)) {
            echo json_encode(['success' => false, 'error' => "Grupo '$groupKey' não configurado."]);
            exit;
        }

        $payload = ['apikey' => 'SenhaMeetups2026', 'date' => $hoje, 'group_jid' => $groupJid,
                    'member_jid' => $memberJid, 'field' => $field, 'value' => $value];
        [$httpCode, $res] = _callNode('/mentoria-edit-activity', $payload);
        echo $httpCode === 200
            ? json_encode(['success' => true])
            : json_encode(['success' => false, 'error' => "Node: $httpCode — $res"]);

    // ────────────────────────────────────────────────────
    // ACTION: edit_social  (mensagens ou reações por grupo)
    // ────────────────────────────────────────────────────
    } elseif ($action === 'edit_social') {
        $memberJid = $input['member_jid'] ?? '';
        $groupJid  = $input['group_jid']  ?? '';
        $field     = $input['field']      ?? '';
        $value     = (int)($input['value'] ?? 0);

        $allowed = ['messages', 'reactions_given', 'images_sent', 'audios_sent'];
        if (!in_array($field, $allowed) || empty($groupJid) || empty($memberJid)) {
            echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
            exit;
        }

        $payload = ['apikey' => 'SenhaMeetups2026', 'date' => $hoje, 'group_jid' => $groupJid,
                    'member_jid' => $memberJid, 'field' => $field, 'value' => $value];
        [$httpCode, $res] = _callNode('/mentoria-edit-activity', $payload);
        echo $httpCode === 200
            ? json_encode(['success' => true])
            : json_encode(['success' => false, 'error' => "Node: $httpCode — $res"]);

    // ────────────────────────────────────────────────────
    // ACTION: edit_streak
    // ────────────────────────────────────────────────────
    } elseif ($action === 'edit_streak') {
        $memberJid = $input['member_jid'] ?? '';
        $currentStreak = isset($input['current_streak']) ? (int)$input['current_streak'] : null;
        $longestStreak = isset($input['longest_streak']) ? (int)$input['longest_streak'] : null;
        $totalCompletions = isset($input['total_completions']) ? (int)$input['total_completions'] : null;
        $lastCompleted = !empty($input['last_completed_date']) ? $input['last_completed_date'] : null;

        if (empty($memberJid) || $currentStreak === null || $longestStreak === null || $totalCompletions === null || $lastCompleted === null) {
            echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE mentoria_desafio_streaks SET current_streak = ?, longest_streak = ?, total_completions = ?, last_completed_date = ? WHERE member_jid = ?");
        $stmt->execute([$currentStreak, $longestStreak, $totalCompletions, $lastCompleted, $memberJid]);

        echo json_encode(['success' => true]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// Helper interno
function _callNode(string $endpoint, array $payload): array {
    $ch = curl_init(BAILEYS_API_URL_DIRECT . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'apikey: SenhaMeetups2026'
        ],
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $res];
}
