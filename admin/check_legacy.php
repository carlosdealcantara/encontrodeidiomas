<?php
require '../config.php';
try {
    $stmt = $conn->query("SELECT id, nome, group_id, ativo FROM meetup_whatsapp_groups WHERE group_id LIKE '%-%'");
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($res, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
