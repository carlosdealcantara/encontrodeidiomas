<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/components.php';

$mock_event = [
    'id' => 1,
    'language_name' => 'Inglês',
    'flag_emoji' => '🇺🇸',
    'time_hour' => '14:00',
    'room_link' => '#',
    'host_name' => 'Teste Host',
    'host_photo' => 'carlos.jpg'
];

echo "Testando renderização do card...\n";
renderEventCard($mock_event);
echo "\nSucesso!";
