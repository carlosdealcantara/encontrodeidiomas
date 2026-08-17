<?php
require 'config.php';
$stmt = $pdo->query("SELECT * FROM odysee_publish_queue WHERE id = 92");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
