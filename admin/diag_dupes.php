<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

echo "<h2>Diagnóstico: Grupos com group_id duplicado</h2>";

$stmt = $conn->query("
    SELECT group_id, COUNT(*) as total, GROUP_CONCAT(id ORDER BY id) as ids, GROUP_CONCAT(nome ORDER BY id SEPARATOR ' | ') as nomes
    FROM meetup_whatsapp_groups
    GROUP BY group_id
    HAVING COUNT(*) > 1
    ORDER BY total DESC
");
$dupes = $stmt->fetchAll();

if (empty($dupes)) {
    echo "<p style='color:green'>Nenhum group_id duplicado encontrado no banco.</p>";
} else {
    echo "<p style='color:red'>ENCONTROU " . count($dupes) . " group_id(s) com duplicatas:</p><ul>";
    foreach ($dupes as $d) {
        echo "<li><b>{$d['nomes']}</b> ({$d['total']}x) — group_id: {$d['group_id']} — IDs no banco: {$d['ids']}</li>";
    }
    echo "</ul>";
}

if (isset($_GET['fix_dupes'])) {
    $conn->exec("
        DELETE g1 FROM meetup_whatsapp_groups g1
        INNER JOIN meetup_whatsapp_groups g2
        WHERE g1.group_id = g2.group_id AND g1.id > g2.id
    ");
    echo "<p style='color:green'>Duplicatas removidas!</p>";
}
