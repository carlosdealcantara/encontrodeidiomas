<?php
/**
 * API: Registro de Streak em Tempo Real (Chamado pelo Node.js)
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// O Baileys envia os dados via POST JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['member_jid'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$memberJid = $data['member_jid'];
$memberName = $data['member_name'] ?? '';
$hoje = date('Y-m-d');
$ontem = (new DateTime())->modify('-1 day')->format('Y-m-d');

$conn = connectDB();

try {
    // Garantir que a tabela existe
    $conn->exec("
    CREATE TABLE IF NOT EXISTS mentoria_desafio_streaks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_jid VARCHAR(100) NOT NULL,
        member_name VARCHAR(255) NULL,
        current_streak INT DEFAULT 0,
        longest_streak INT DEFAULT 0,
        last_completed_date DATE NULL,
        total_completions INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_member (member_jid)
    )");

    // Buscar o streak atual do membro
    $stmt = $conn->prepare("SELECT * FROM mentoria_desafio_streaks WHERE member_jid = ?");
    $stmt->execute([$memberJid]);
    $streakData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($streakData) {
        $lastCompleted = $streakData['last_completed_date'];
        
        if ($lastCompleted === $hoje) {
            // Já enviou imagem hoje e já foi computado
            echo json_encode([
                'success' => true, 
                'already_computed' => true,
                'streak' => $streakData['current_streak'],
                'longest_streak' => $streakData['longest_streak'],
                'total_completions' => $streakData['total_completions']
            ]);
            exit;
        }

        $currentStreak = (int)$streakData['current_streak'];
        $longestStreak = (int)$streakData['longest_streak'];
        $totalCompletions = (int)$streakData['total_completions'];

        if ($lastCompleted === $ontem) {
            // Sequência mantida
            $currentStreak++;
        } else {
            // Sequência quebrada
            $currentStreak = 1;
        }

        if ($currentStreak > $longestStreak) {
            $longestStreak = $currentStreak;
        }

        $totalCompletions++;

        // Atualizar no banco
        $update = $conn->prepare("
            UPDATE mentoria_desafio_streaks 
            SET current_streak = ?, longest_streak = ?, last_completed_date = ?, total_completions = ?, member_name = ?
            WHERE member_jid = ?
        ");
        $update->execute([$currentStreak, $longestStreak, $hoje, $totalCompletions, $memberName, $memberJid]);

    } else {
        // Primeiro envio da vida
        $currentStreak = 1;
        $insert = $conn->prepare("
            INSERT INTO mentoria_desafio_streaks 
            (member_jid, member_name, current_streak, longest_streak, last_completed_date, total_completions)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([$memberJid, $memberName, 1, 1, $hoje, 1]);
    }

    // Verificar se atingiu um milestone
    $milestones = [3, 7, 10, 15, 30, 60, 90, 100, 150, 200, 365];
    $isMilestone = in_array($currentStreak, $milestones);

    echo json_encode([
        'success' => true,
        'already_computed' => false,
        'streak' => $currentStreak,
        'longest_streak' => $longestStreak,
        'total_completions' => $totalCompletions,
        'is_milestone' => $isMilestone
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
