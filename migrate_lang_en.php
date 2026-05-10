<?php
require_once 'config.php';
$conn = connectDB();
$results = [];

$col = 'name_en';
$check = $conn->query("SHOW COLUMNS FROM languages LIKE '$col'")->fetch();
if ($check) {
    $results[] = "✅ Coluna $col já existe.";
} else {
    try {
        $conn->exec("ALTER TABLE languages ADD COLUMN name_en VARCHAR(100) AFTER name");
        $results[] = "✅ Coluna $col adicionada com sucesso.";
    } catch (Exception $e) {
        $results[] = "❌ Erro ao adicionar $col: " . $e->getMessage();
    }
}

echo implode("<br>", $results);
echo "<br><br><strong>Delete este arquivo (migrate_lang_en.php) após rodar!</strong>";
