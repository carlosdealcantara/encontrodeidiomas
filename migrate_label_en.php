<?php
require_once 'config.php';
$conn = connectDB();
$results = [];

$col = 'initiative_label_en';
$check = $conn->query("SHOW COLUMNS FROM hosts LIKE '$col'")->fetch();
if ($check) {
    $results[] = "✅ Coluna $col já existe.";
} else {
    try {
        $conn->exec("ALTER TABLE hosts ADD COLUMN initiative_label_en VARCHAR(100) DEFAULT NULL");
        $results[] = "✅ Coluna $col adicionada.";
    } catch (Exception $e) {
        $results[] = "❌ Erro: " . $e->getMessage();
    }
}

echo implode("<br>", $results);
echo "<br><br><strong>Delete este arquivo (migrate_label_en.php) após rodar!</strong>";
