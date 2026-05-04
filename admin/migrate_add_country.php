<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

$steps = [];

// 1. Add country column
try {
    $conn->exec("ALTER TABLE in_person_events ADD COLUMN country VARCHAR(100) DEFAULT 'Brasil' AFTER state");
    $steps[] = "✅ Coluna 'country' adicionada.";
} catch (PDOException $e) {
    $steps[] = "ℹ️ Coluna 'country' já existe (ou erro: " . $e->getMessage() . ")";
}

// 2. Set country for Paraguay (state = 'PY')
$r = $conn->exec("UPDATE in_person_events SET country = 'Paraguai', state = NULL WHERE state = 'PY'");
$steps[] = "✅ $r registro(s) → country = 'Paraguai'";

// 3. Set country for Argentina (state = 'AG' or 'AR')
$r = $conn->exec("UPDATE in_person_events SET country = 'Argentina', state = NULL WHERE state IN ('AG','AR')");
$steps[] = "✅ $r registro(s) → country = 'Argentina'";

// 4. Ensure all others default to Brasil
$r = $conn->exec("UPDATE in_person_events SET country = 'Brasil' WHERE country IS NULL OR country = ''");
$steps[] = "✅ $r registro(s) confirmados como 'Brasil'";

// 5. Verify
try {
    $rows = $conn->query("SELECT id, city, state, country FROM in_person_events ORDER BY country, city")->fetchAll();
    $steps[] = "<br><strong>Registros após migração:</strong><br>";
    foreach ($rows as $r) {
        $steps[] = "  • [{$r['country']}] {$r['city']}" . ($r['state'] ? " - {$r['state']}" : "");
    }
} catch (PDOException $e) {
    $steps[] = "Erro ao verificar: " . $e->getMessage();
}

echo "<pre style='font-family:monospace;line-height:2;padding:20px;'>";
echo implode("\n", $steps);
echo "</pre>";
?>
