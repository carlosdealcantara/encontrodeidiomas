<?php
require_once __DIR__ . '/../config.php';

// Permite apenas POST com JSON
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['action'], $input['group_jid'], $input['member_jid'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Bad request']));
}

$action = $input['action'];
$groupJid = $input['group_jid'];
$memberJid = $input['member_jid'];
$memberName = $input['member_name'] ?? 'Desconhecido';

// Determina a data de "hoje" BRT
$hoje = date('Y-m-d');

try {
    $conn = connectDB();
    
    // Busca todas as aulas ativas de hoje para este grupo
    $diaSemana = date('N'); // 1 = Segunda, 7 = Domingo
    $stmt = $conn->prepare("SELECT id, start_time, session_type FROM class_schedule WHERE group_jid = ? AND day_of_week = ? AND is_active = 1 ORDER BY start_time ASC");
    $stmt->execute([$groupJid, $diaSemana]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $schedule = null;
    $nowObj = new DateTime();
    $startTimeObj = null;

    if (isset($input['schedule_id'])) {
        $requestedId = (int)$input['schedule_id'];
        foreach ($schedules as $s) {
            if ($s['id'] == $requestedId) {
                $schedule = $s;
                $startTimeObj = new DateTime($hoje . ' ' . $s['start_time']);
                break;
            }
        }
    } elseif (isset($input['schedule_position'])) {
        $pos = (int)$input['schedule_position'];
        if ($pos >= 1 && $pos <= count($schedules)) {
            $schedule = $schedules[$pos - 1];
            $startTimeObj = new DateTime($hoje . ' ' . $schedule['start_time']);
        }
    } else {
        if (count($schedules) > 1 && in_array($action, ['attend', 'unattend'])) {
            die(json_encode(['success' => false, 'reason' => 'multiple_sessions_require_id', 'schedules_count' => count($schedules)]));
        }
        
        // Encontra a próxima aula válida (limite máximo para interagir é 14 min APÓS o início)
        foreach ($schedules as $s) {
            $stObj = new DateTime($hoje . ' ' . $s['start_time']);
            $maxUObj = clone $stObj;
            $maxUObj->modify('+14 minutes');
            
            if ($nowObj <= $maxUObj) {
                $schedule = $s;
                $startTimeObj = $stObj;
                break;
            }
        }
    }
    
    if (!$schedule) {
        die(json_encode(['success' => false, 'message' => "There is no class scheduled for today!"]));
    }
    
    $scheduleId = $schedule['id'];
    
    // Pega a lista ATUAL de confirmados ANTES da ação
    $stmtList = $conn->prepare("SELECT member_name FROM class_attendances WHERE schedule_id = ? AND aula_date = ? ORDER BY id ASC");
    $stmtList->execute([$scheduleId, $hoje]);
    $currentAttendees = $stmtList->fetchAll(PDO::FETCH_COLUMN);
    $currentCount = count($currentAttendees);

    // Verifica se a aula/encontro já está confirmada pelo quórum
    $isClassConfirmed = false;
    if ($schedule['session_type'] === 'practice') {
        $isClassConfirmed = ($currentCount >= 2);
    } else {
        $isClassConfirmed = ($currentCount >= 1);
    }

    // Verifica prazos de marcação
    $deadlineObj = clone $startTimeObj;
    $deadlineObj->modify('-1 hour');
    $isPast1HourDeadline = ($nowObj > $deadlineObj);
    $isPastStartTime = ($nowObj > $startTimeObj);

    // Constrói o daily_summary (resumo de todos os eventos do dia)
    function buildDailySummary($conn, $schedules, $hoje) {
        $dailySummary = [];
        $position = 1;
        $stmtList = $conn->prepare("SELECT member_name FROM class_attendances WHERE schedule_id = ? AND aula_date = ? ORDER BY id ASC");
        foreach ($schedules as $s) {
            $stmtList->execute([$s['id'], $hoje]);
            $atts = $stmtList->fetchAll(PDO::FETCH_COLUMN);
            $dailySummary[] = [
                'schedule_id' => $s['id'],
                'position' => $position,
                'session_type' => $s['session_type'],
                'start_time' => $s['start_time'],
                'attendees' => $atts
            ];
            $position++;
        }
        return $dailySummary;
    }

    $dateEn = date('l, F jS', strtotime($hoje));
    $timeEn = (new DateTime($hoje . ' ' . $schedule['start_time']))->format('h:i A') . ' (UTC-3)';

    if ($action === 'attend') {
        
        $attendanceBlocked = false;
        
        if ($isClassConfirmed) {
            // Se já está confirmada, bloqueia apenas se passou do horário de início
            if ($isPastStartTime) {
                $attendanceBlocked = true;
            }
        } else {
            // Se não está confirmada, bloqueia no prazo original (1 hora antes)
            if ($isPast1HourDeadline) {
                $attendanceBlocked = true;
            }
        }

        if ($attendanceBlocked) {
            // Recusa a marcação mas informa o status atual
            die(json_encode([
                'success' => false, 
                'reason' => 'deadline_passed',
                'class_confirmed' => $isClassConfirmed,
                'attendees' => $currentAttendees,
                'class_date' => $hoje,
                'class_time' => $schedule['start_time'],
                'class_date_en' => $dateEn,
                'class_time_en' => $timeEn
            ]));
        }

        if ($memberName === 'Desconhecido') {
            // Tenta achar o nome em class_attendances anteriores
            $stmtName = $conn->prepare("SELECT member_name FROM class_attendances WHERE member_jid = ? AND member_name != 'Desconhecido' AND member_name IS NOT NULL ORDER BY id DESC LIMIT 1");
            $stmtName->execute([$memberJid]);
            $knownName = $stmtName->fetchColumn();
            
            if (!$knownName) {
                // Tenta no mentoria_desafio_streaks
                $stmtName2 = $conn->prepare("SELECT member_name FROM mentoria_desafio_streaks WHERE member_jid = ? AND member_name != 'Desconhecido' AND member_name IS NOT NULL AND member_name != '' LIMIT 1");
                $stmtName2->execute([$memberJid]);
                $knownName = $stmtName2->fetchColumn();
            }
            
            if ($knownName) {
                $memberName = $knownName;
            }
        }

        // Salva a presença
        $insert = $conn->prepare("INSERT IGNORE INTO class_attendances (schedule_id, member_jid, member_name, aula_date) VALUES (?, ?, ?, ?)");
        $insert->execute([$scheduleId, $memberJid, $memberName, $hoje]);
        
        // Pega a lista atualizada
        $stmtList->execute([$scheduleId, $hoje]);
        $attendees = $stmtList->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            'success' => true, 
            'attendees' => $attendees,
            'daily_summary' => buildDailySummary($conn, $schedules, $hoje),
            'class_date' => $hoje,
            'class_time' => $schedule['start_time'],
            'class_date_en' => $dateEn,
            'class_time_en' => $timeEn
        ]);
        
    } elseif ($action === 'unattend') {
        
        // Remove a presença
        $del = $conn->prepare("DELETE FROM class_attendances WHERE schedule_id = ? AND member_jid = ? AND aula_date = ?");
        $del->execute([$scheduleId, $memberJid, $hoje]);
        
        $stmtList->execute([$scheduleId, $hoje]);
        $attendees = $stmtList->fetchAll(PDO::FETCH_COLUMN);
        $newCount = count($attendees);
        
        $cancelledNow = false;
        // Se já passou do prazo original (1h antes), e a turma caiu pra zero, a aula é ativamente cancelada AGORA
        if ($isPast1HourDeadline && $currentCount > 0 && $newCount === 0) {
            $cancelledNow = true;
        }

        echo json_encode([
            'success' => true, 
            'attendees' => $attendees,
            'daily_summary' => buildDailySummary($conn, $schedules, $hoje),
            'cancelled_now' => $cancelledNow,
            'class_date' => $hoje,
            'class_time' => $schedule['start_time'],
            'class_date_en' => $dateEn,
            'class_time_en' => $timeEn
        ]);
        
    } elseif ($action === 'list' || $action === 'status') {
        
        $attendanceBlocked = ($isClassConfirmed ? $isPastStartTime : $isPast1HourDeadline);
        
        echo json_encode([
            'success' => true, 
            'attendees' => $currentAttendees,
            'daily_summary' => buildDailySummary($conn, $schedules, $hoje),
            'class_date' => $hoje,
            'class_time' => $schedule['start_time'],
            'class_date_en' => $dateEn,
            'class_time_en' => $timeEn,
            'deadline_passed' => $attendanceBlocked,
            'schedules_count' => count($schedules) // Útil para o bot saber se precisa listar opções
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log("Class API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
