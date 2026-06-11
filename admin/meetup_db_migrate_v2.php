<?php
require_once __DIR__ . '/../config.php';

try {
    $conn = connectDB();
    
    // 1. Create meetup_schedule
    $conn->exec("
        CREATE TABLE IF NOT EXISTS meetup_schedule (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_jid VARCHAR(100) NOT NULL,
            day_of_week TINYINT NOT NULL,
            start_time TIME NOT NULL,
            meet_link VARCHAR(500) NOT NULL,
            is_active TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "Tabela meetup_schedule criada/verificada com sucesso.<br>";

    // 2. Create meetup_attendances
    $conn->exec("
        CREATE TABLE IF NOT EXISTS meetup_attendances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            schedule_id INT NOT NULL,
            member_jid VARCHAR(100) NOT NULL,
            member_name VARCHAR(100),
            aula_date DATE NOT NULL,
            confirmed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY no_duplicate (schedule_id, member_jid, aula_date)
        )
    ");
    echo "Tabela meetup_attendances criada/verificada com sucesso.<br>";

    // 3. Populate default schedules if empty
    $check = $conn->query("SELECT COUNT(*) FROM meetup_schedule")->fetchColumn();
    if ($check == 0) {
        $stmt = $conn->prepare("INSERT INTO meetup_schedule (group_jid, day_of_week, start_time, meet_link) VALUES (?, ?, ?, ?)");
        
        $groupJid = '120363228807801778@g.us'; // Our Meetups JID (from the screenshot)
        
        // Segunda (1), Quinta (4) às 20:00 - Link A
        $stmt->execute([$groupJid, 1, '20:00:00', 'meet.google.com/bru-wfke-spc']);
        $stmt->execute([$groupJid, 4, '20:00:00', 'meet.google.com/bru-wfke-spc']);
        
        // Terça (2), Quarta (3) às 13:00 - Link B
        $stmt->execute([$groupJid, 2, '13:00:00', 'meet.google.com/hmb-zqnz-zmp']);
        $stmt->execute([$groupJid, 3, '13:00:00', 'meet.google.com/hmb-zqnz-zmp']);
        
        echo "Horários padrão inseridos com sucesso.<br>";
    } else {
        echo "Horários já existem, pulando inserção inicial.<br>";
    }

} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage();
}
?>
