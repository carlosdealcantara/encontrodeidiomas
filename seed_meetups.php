<?php
require_once __DIR__ . '/config.php';

try {
    $conn = connectDB();

    // Verificar se a tabela existe; criá-la se não existir
    $conn->exec("CREATE TABLE IF NOT EXISTS meetup_schedule (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_jid VARCHAR(255) NOT NULL,
        day_of_week TINYINT NOT NULL COMMENT '1=Seg, 2=Ter, 3=Qua, 4=Qui, 5=Sex, 6=Sab, 7=Dom',
        start_time TIME NOT NULL,
        meet_link VARCHAR(500) DEFAULT '',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Limpar registros anteriores para não duplicar
    $conn->exec("DELETE FROM meetup_schedule");

    // Configurar os encontros: Segunda a Quinta, 8h e 20h BRT
    $group_jid = '120363294371457065@g.us'; // JID do Our Meetups
    $link = 'meet.google.com/bru-wfke-spc';  // Link real do Google Meet

    $slots = [
        [1, '08:00'], // Segunda 8h
        [1, '20:00'], // Segunda 20h
        [2, '08:00'], // Terça 8h
        [2, '20:00'], // Terça 20h
        [3, '08:00'], // Quarta 8h
        [3, '20:00'], // Quarta 20h
        [4, '08:00'], // Quinta 8h
        [4, '20:00'], // Quinta 20h
    ];

    $stmt = $conn->prepare("INSERT INTO meetup_schedule (group_jid, day_of_week, start_time, meet_link, is_active) VALUES (?, ?, ?, ?, 1)");
    foreach ($slots as $slot) {
        $stmt->execute([$group_jid, $slot[0], $slot[1], $link]);
    }

    $count = $conn->query("SELECT COUNT(*) FROM meetup_schedule")->fetchColumn();
    echo "✅ Sucesso! $count horários cadastrados na tabela meetup_schedule.";

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>
