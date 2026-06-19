<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

$updates = [
    'Inglês' => ['emoji' => '🇺🇸'],
    'Libras' => ['emoji' => '👋'],
    'Servo-croata' => ['emoji' => '🇷🇸'],
    'Português' => ['emoji' => '🇧🇷'],
    'Coreano' => ['emoji' => '🇰🇷']
];

foreach ($updates as $name => $data) {
    $stmt = $conn->prepare("UPDATE languages SET flag_emoji = ? WHERE name = ?");
    $stmt->execute([$data['emoji'], $name]);
    echo "Updated $name to {$data['emoji']}\n";
}

echo "Done.";
?>
