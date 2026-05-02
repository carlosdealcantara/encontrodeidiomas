<?php
require_once 'config.php';
$conn = connectDB();

$tables = ['hosts', 'languages', 'events'];

foreach ($tables as $table) {
    echo "--- Table: $table ---\n";
    try {
        $stmt = $conn->query("DESCRIBE $table");
        while ($row = $stmt->fetch()) {
            echo "{$row['Field']} - {$row['Type']} - {$row['Null']} - {$row['Key']} - {$row['Default']}\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
