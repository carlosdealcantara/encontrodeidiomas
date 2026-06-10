<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

$stmt = $conn->query("SELECT id, name, flag_emoji, greeting FROM languages");
$langs = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($langs as $l) {
    echo "ID: {$l['id']} | Name: {$l['name']} | Emoji: '{$l['flag_emoji']}' | Greeting: '{$l['greeting']}'\n";
}
?>
