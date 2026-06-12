<?php
require_once __DIR__ . '/config.php';

try {
    $conn = connectDB();
    $sql = "CREATE TABLE IF NOT EXISTS `meetup_attendances` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `schedule_id` int(11) NOT NULL,
      `member_jid` varchar(255) NOT NULL,
      `member_name` varchar(255) DEFAULT NULL,
      `aula_date` date NOT NULL,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_attendance` (`schedule_id`,`member_jid`,`aula_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $conn->exec($sql);
    echo "Table created successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
