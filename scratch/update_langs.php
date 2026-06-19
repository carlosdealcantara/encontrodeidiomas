<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

$updates = [
    'Inglês' => ['emoji' => '🇬🇧', 'greeting' => 'Welcome!'],
    'Espanhol' => ['emoji' => '🇪🇸', 'greeting' => '¡Bienvenidos!'],
    'Francês' => ['emoji' => '🇫🇷', 'greeting' => 'Bienvenue!'],
    'Português' => ['emoji' => '🇧🇷', 'greeting' => 'Bem-vindos!'],
    'Italiano' => ['emoji' => '🇮🇹', 'greeting' => 'Benvenuti!'],
    'Alemão' => ['emoji' => '🇩🇪', 'greeting' => 'Willkommen!'],
    'Japonês' => ['emoji' => '🇯🇵', 'greeting' => 'ようこそ!'],
    'Mandarim' => ['emoji' => '🇨🇳', 'greeting' => '欢迎!'],
    'Russo' => ['emoji' => '🇷🇺', 'greeting' => 'Добро пожаловать!'],
    'Coreano' => ['emoji' => '🇰🇷', 'greeting' => '환영합니다!']
];

foreach ($updates as $name => $data) {
    $stmt = $conn->prepare("UPDATE languages SET flag_emoji = ?, greeting = ? WHERE name = ?");
    $stmt->execute([$data['emoji'], $data['greeting'], $name]);
    echo "Updated $name\n";
}

echo "Done.";
?>
