<?php
require '../config.php';
$conn = connectDB();

// Known emojis for languages
$flagUpdates = [
    8 => '🇨🇳', // Chinês
    10 => '🇵🇱', // Polonês
    14 => '🇮🇩', // Indonésio
];

foreach ($flagUpdates as $id => $emoji) {
    $stmt = $conn->prepare('UPDATE languages SET flag_emoji = :emoji WHERE id = :id AND (flag_emoji = "" OR flag_emoji IS NULL)');
    $stmt->execute(['emoji' => $emoji, 'id' => $id]);
    echo "Updated language ID $id to emoji $emoji \n";
}

$stmt = $conn->prepare('SELECT id, name, flag_emoji FROM languages WHERE flag_emoji = "" OR flag_emoji IS NULL');
$stmt->execute();
$missing = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Remaining missing flags: \n";
print_r($missing);
?>
