<?php
require_once __DIR__ . '/config.php';
$conn = connectDB();
try {
    $stmt = $conn->prepare("INSERT INTO meetup_whatsapp_groups (nome, group_id, categoria, ativo) VALUES ('Our Meetups', '120363228807801778@g.us', 'mentoria', 1) ON DUPLICATE KEY UPDATE categoria='mentoria'");
    $stmt->execute();
    echo "Success";
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
