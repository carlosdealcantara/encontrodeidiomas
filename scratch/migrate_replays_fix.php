<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();

try {
    // Verificar estrutura atual da tabela
    $cols = $conn->query("DESCRIBE meetup_replays")->fetchAll(PDO::FETCH_ASSOC);
    echo "Estrutura atual:\n";
    foreach ($cols as $col) {
        echo "  {$col['Field']} ({$col['Type']}) Key:{$col['Key']}\n";
    }

    // Verificar a chave primária atual
    $indexes = $conn->query("SHOW INDEX FROM meetup_replays WHERE Key_name = 'PRIMARY'")->fetchAll();
    echo "\nChave primária atual:\n";
    foreach ($indexes as $idx) {
        echo "  Coluna: {$idx['Column_name']}, Seq: {$idx['Seq_in_index']}\n";
    }

    // Se a PK ainda é só language_id (não composta), corrigir
    $pkCols = array_column($indexes, 'Column_name');
    if (!in_array('semana', $pkCols)) {
        echo "\nAtualizando chave primária para (language_id, semana)...\n";
        $conn->exec("ALTER TABLE meetup_replays DROP PRIMARY KEY, ADD PRIMARY KEY (language_id, semana)");
        echo "Chave primária atualizada!\n";
    } else {
        echo "\nChave primária já está correta (language_id + semana). Nada a fazer.\n";
    }

} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
