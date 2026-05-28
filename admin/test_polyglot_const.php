<?php
require '../config.php';
try {
    $stmt = $conn->query("SELECT nome, group_id FROM meetup_whatsapp_groups WHERE nome LIKE '%Polyglot Const%'");
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($res, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
