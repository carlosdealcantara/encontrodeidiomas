<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

echo "Checking events table for null language_id...\n";
$stmt = $conn->query("SELECT id, title, language_id FROM events WHERE language_id IS NULL");
$nullLangs = $stmt->fetchAll();

if (empty($nullLangs)) {
    echo "No events with NULL language_id found.\n";
    echo "Checking if all language_id exist in languages table...\n";
    $stmt = $conn->query("SELECT e.id, e.title, e.language_id FROM events e LEFT JOIN languages l ON e.language_id = l.id WHERE l.id IS NULL");
    $missingLangs = $stmt->fetchAll();
    if (empty($missingLangs)) {
        echo "All events have valid language_id.\n";
    } else {
        echo "Found events with missing language_id in languages table:\n";
        print_r($missingLangs);
    }
} else {
    echo "Found events with NULL language_id:\n";
    print_r($nullLangs);
}
