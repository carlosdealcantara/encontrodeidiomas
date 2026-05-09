<?php
require_once 'config.php';
$conn = connectDB();
$results = [];

// 1. Criar novas colunas
$columns = [
    'initiative_description'    => "ALTER TABLE hosts ADD COLUMN initiative_description TEXT DEFAULT NULL",
    'initiative_description_en' => "ALTER TABLE hosts ADD COLUMN initiative_description_en TEXT DEFAULT NULL",
];

foreach ($columns as $col => $sql) {
    $check = $conn->query("SHOW COLUMNS FROM hosts LIKE '$col'")->fetch();
    if ($check) {
        $results[] = "✅ Coluna $col já existe.";
    } else {
        try {
            $conn->exec($sql);
            $results[] = "✅ Coluna $col adicionada.";
        } catch (Exception $e) {
            $results[] = "❌ Erro em $col: " . $e->getMessage();
        }
    }
}

// 2. Migrar "Técnica" para "Bastidores" na coluna category
try {
    // Busca todos os hosts que têm 'Técnica' na string de categorias
    $stmt = $conn->query("SELECT id, category FROM hosts WHERE category LIKE '%Técnica%'");
    $hosts = $stmt->fetchAll();
    $count = 0;
    foreach ($hosts as $h) {
        $newCat = str_replace('Técnica', 'Bastidores', $h['category']);
        $up = $conn->prepare("UPDATE hosts SET category = ? WHERE id = ?");
        $up->execute([$newCat, $h['id']]);
        $count++;
    }
    $results[] = "🔄 $count registros atualizados de 'Técnica' para 'Bastidores'.";
} catch (Exception $e) {
    $results[] = "❌ Erro na migração de categorias: " . $e->getMessage();
}

echo implode("<br>", $results);
echo "<br><br><strong>Delete este arquivo (migrate_exp.php) após rodar!</strong>";
