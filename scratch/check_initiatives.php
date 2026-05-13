<?php
require_once 'config.php';
$conn = connectDB();
$stmt = $conn->query("SELECT initiative_label, initiative_label_en FROM hosts WHERE initiative_label IS NOT NULL AND initiative_label != ''");
$rows = $stmt->fetchAll();
echo "Total initiatives: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "PT: " . $r['initiative_label'] . " | EN: " . ($r['initiative_label_en'] ?: '[EMPTY]') . "\n";
}
