<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();
$stmt = $conn->query("UPDATE mentoria_alunos SET status_pagamento = 'Isento' WHERE status_aluno = 'Vitalício' AND status_pagamento != 'Isento'");
echo "Linhas afetadas: " . $stmt->rowCount() . "\n";
