<?php
require_once 'config.php';
$conn = connectDB();
$results = [];

// Lista de colunas para adicionar (se não existirem)
$columns = [
    'initiative_label'          => "ALTER TABLE hosts ADD COLUMN initiative_label VARCHAR(100) DEFAULT NULL",
    'initiative_label_en'       => "ALTER TABLE hosts ADD COLUMN initiative_label_en VARCHAR(100) DEFAULT NULL",
    'initiative_url'            => "ALTER TABLE hosts ADD COLUMN initiative_url VARCHAR(255) DEFAULT NULL",
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
            $results[] = "✅ Coluna $col adicionada com sucesso.";
        } catch (Exception $e) {
            $results[] = "❌ Erro ao adicionar $col: " . $e->getMessage();
        }
    }
}

// Migração de Dados: Técnica -> Bastidores
try {
    $stmt = $conn->query("SELECT id, category FROM hosts WHERE category LIKE '%Técnica%'");
    $hosts = $stmt->fetchAll();
    $count = 0;
    foreach ($hosts as $h) {
        $newCat = str_replace('Técnica', 'Bastidores', $h['category']);
        $up = $conn->prepare("UPDATE hosts SET category = ? WHERE id = ?");
        $up->execute([$newCat, $h['id']]);
        $count++;
    }
    $results[] = "🔄 $count registros migrados de 'Técnica' para 'Bastidores'.";
} catch (Exception $e) {
    $results[] = "❌ Erro na migração de categorias: " . $e->getMessage();
}

echo "<h2>Migração de Produção - Encontro de Idiomas</h2>";
echo implode("<br>", $results);
echo "<br><br><strong style='color:red;'>⚠️ DELETE ESTE ARQUIVO (MIGRATE_PRODUCAO.php) IMEDIATAMENTE APÓS A EXECUÇÃO!</strong>";
