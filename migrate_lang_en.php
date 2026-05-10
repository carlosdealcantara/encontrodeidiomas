<?php
require_once 'config.php';
$conn = connectDB();
$results = [];

// 1. Cria a coluna se não existir
$col = 'name_en';
$check = $conn->query("SHOW COLUMNS FROM languages LIKE '$col'")->fetch();
if (!$check) {
    try {
        $conn->exec("ALTER TABLE languages ADD COLUMN name_en VARCHAR(100) AFTER name");
        $results[] = "✅ Coluna $col adicionada.";
    } catch (Exception $e) {
        $results[] = "❌ Erro ao adicionar coluna: " . $e->getMessage();
    }
} else {
    $results[] = "✅ Coluna $col já existe.";
}

// 2. Popula os nomes em inglês para os idiomas existentes
$translations = [
    'Inglês'   => 'English',
    'Alemão'   => 'German',
    'Espanhol' => 'Spanish',
    'Francês'  => 'French',
    'Italiano' => 'Italian',
    'Japonês'  => 'Japanese',
    'Russo'    => 'Russian',
    'Coreano'  => 'Korean',
    'Chinês'   => 'Chinese',
    'Português'=> 'Portuguese',
    'Esperanto'=> 'Esperanto',
    'Holandês' => 'Dutch',
    'Indonésio'=> 'Indonesian'
];

$count = 0;
foreach ($translations as $pt => $en) {
    $stmt = $conn->prepare("UPDATE languages SET name_en = ? WHERE name = ? AND (name_en IS NULL OR name_en = '')");
    $stmt->execute([$en, $pt]);
    $count += $stmt->rowCount();
}

$results[] = "📈 $count nomes em inglês foram populados automaticamente.";

echo "<h3>Migração de Idiomas</h3>";
echo implode("<br>", $results);
echo "<br><br><strong>Pronto! Agora você pode conferir no Painel Admin. Delete este arquivo após rodar!</strong>";
