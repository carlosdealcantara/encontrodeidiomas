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
$nowStr = date('H:i:s');

try {
    $conn = connectDB();
    
    // Calcula o horário limite (agora + 1 hora). O start_time precisa ser maior que isso.
    $limiteObj = new DateTime();
    $limiteObj->modify('+1 hour');
    $limiteStr = $limiteObj->format('H:i:s');

    // Procura o PRÓXIMO encontro agendado para HOJE cuja janela de inscrição ainda esteja aberta
    $diaSemana = date('N'); // 1 = Segunda, 7 = Domingo
    $stmt = $conn->prepare("SELECT id, start_time FROM meetup_schedule WHERE group_jid = ? AND day_of_week = ? AND is_active = 1 AND start_time >= ? ORDER BY start_time ASC LIMIT 1");
    $stmt->execute([$groupJid, $diaSemana, $limiteStr]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$schedule) {
        die(json_encode(['success' => false, 'message' => "There is no class scheduled for today!"]));
    }
    
    $scheduleId = $schedule['id'];
    $startTime = $schedule['start_time'];
    
    // Verifica prazo (1 hora antes)
    $startTimeObj = new DateTime($hoje . ' ' . $startTime);
    $deadlineObj = clone $startTimeObj;
    $deadlineObj->modify('-1 hour');
    $nowObj = new DateTime();
    
    if ($nowObj > $deadlineObj) {
        die(json_encode(['success' => false, 'message' => "The deadline to confirm attendance has passed!"]));
    }

    if ($action === 'attend') {
        // Salva a presença
        $insert = $conn->prepare("INSERT IGNORE INTO meetup_attendances (schedule_id, member_jid, member_name, aula_date) VALUES (?, ?, ?, ?)");
        $insert->execute([$scheduleId, $memberJid, $memberName, $hoje]);
        
        // Pega a lista atualizada
        $stmtList = $conn->prepare("SELECT member_name FROM meetup_attendances WHERE schedule_id = ? AND aula_date = ?");
        $stmtList->execute([$scheduleId, $hoje]);
        $attendees = $stmtList->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode(['success' => true, 'attendees' => $attendees]);
        
    } elseif ($action === 'unattend') {
        // Remove a presença
        $del = $conn->prepare("DELETE FROM meetup_attendances WHERE schedule_id = ? AND member_jid = ? AND aula_date = ?");
        $del->execute([$scheduleId, $memberJid, $hoje]);
        
        // Pega a lista atualizada
        $stmtList = $conn->prepare("SELECT member_name FROM meetup_attendances WHERE schedule_id = ? AND aula_date = ?");
        $stmtList->execute([$scheduleId, $hoje]);
        $attendees = $stmtList->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode(['success' => true, 'attendees' => $attendees]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log("Meetup API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
