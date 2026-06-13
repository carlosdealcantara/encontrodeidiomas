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
    $stmt = $conn->prepare("SELECT id, start_time FROM class_schedule WHERE group_jid = ? AND day_of_week = ? AND is_active = 1 ORDER BY start_time ASC");
    $stmt->execute([$groupJid, $diaSemana]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $schedule = null;
    $nowObj = new DateTime();
    $startTimeObj = null;
    $maxUnattendObj = null;

    // Encontra a próxima aula válida (limite máximo para interagir é 14 min APÓS o início)
    foreach ($schedules as $s) {
        $stObj = new DateTime($hoje . ' ' . $s['start_time']);
        $maxUObj = clone $stObj;
        $maxUObj->modify('+14 minutes');
        
        if ($nowObj <= $maxUObj) {
            $schedule = $s;
            $startTimeObj = $stObj;
            $maxUnattendObj = $maxUObj;
            break;
        }
    }
    
    if (!$schedule) {
        die(json_encode(['success' => false, 'message' => "There is no class scheduled for today!"]));
    }
    
    $scheduleId = $schedule['id'];
    
    // Verifica prazo de marcação (1 hora antes)
    $deadlineObj = clone $startTimeObj;
    $deadlineObj->modify('-1 hour');
    $isPastDeadline = ($nowObj > $deadlineObj);

    // Pega a lista ATUAL de confirmados ANTES da ação
    $stmtList = $conn->prepare("SELECT member_name FROM class_attendances WHERE schedule_id = ? AND aula_date = ?");
    $stmtList->execute([$scheduleId, $hoje]);
    $currentAttendees = $stmtList->fetchAll(PDO::FETCH_COLUMN);
    $currentCount = count($currentAttendees);

    $dateEn = date('l, F jS', strtotime($hoje));
    $timeEn = (new DateTime($hoje . ' ' . $schedule['start_time']))->format('h:i A') . ' (UTC-3)';

    if ($action === 'attend') {
        
        if ($isPastDeadline) {
            // Recusa a marcação mas informa o status atual
            die(json_encode([
                'success' => false, 
                'reason' => 'deadline_passed',
                'class_confirmed' => ($currentCount > 0),
                'attendees' => $currentAttendees,
                'class_date' => $hoje,
                'class_time' => $schedule['start_time'],
                'class_date_en' => $dateEn,
                'class_time_en' => $timeEn
            ]));
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
        // Se já passou do prazo (1h antes), e a turma caiu pra zero, a aula é ativamente cancelada AGORA
        if ($isPastDeadline && $currentCount > 0 && $newCount === 0) {
            $cancelledNow = true;
        }

        echo json_encode([
            'success' => true, 
            'attendees' => $attendees,
            'cancelled_now' => $cancelledNow,
            'class_date' => $hoje,
            'class_time' => $schedule['start_time'],
            'class_date_en' => $dateEn,
            'class_time_en' => $timeEn
        ]);
        
    } elseif ($action === 'list' || $action === 'status') {
        
        echo json_encode([
            'success' => true, 
            'attendees' => $currentAttendees,
            'class_date' => $hoje,
            'class_time' => $schedule['start_time'],
            'class_date_en' => $dateEn,
            'class_time_en' => $timeEn,
            'deadline_passed' => $isPastDeadline
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log("Class API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
