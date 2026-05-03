<?php
require_once 'config.php';
$conn = connectDB();

echo "### MEETINGS (Instagram Links)\n";
$stmt = $conn->query("SELECT language_id, instagram_link FROM meetings WHERE instagram_link IS NOT NULL AND instagram_link != '' LIMIT 10");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Lang ID: {$row['language_id']} - Link: {$row['instagram_link']}\n";
}

echo "\n### LANGUAGES (Instagram Links)\n";
$stmt = $conn->query("SELECT id, name, instagram_link FROM languages");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']} - Name: {$row['name']} - Link: '{$row['instagram_link']}'\n";
}
