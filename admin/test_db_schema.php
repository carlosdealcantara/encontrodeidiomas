<?php
require_once '../config.php';
$c = connectDB();
$stmt = $c->query("SELECT id, nome, proximo_vencimento FROM mentoria_alunos ORDER BY id DESC LIMIT 10");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
