<?php
require_once '../config.php';
$c = connectDB();
$stmt = $c->query("SELECT id, cenario, ativo, ativo_telegram FROM mentoria_mensagens");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
