<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

$config = getMentoriaConfig();
$targetGroup = $config['groups']['the_lounge']['jid'] ?? null;

$message = "Test message";

echo "Target Group: " . var_export($targetGroup, true) . "\n";
echo "Message: " . var_export($message, true) . "\n";

$result = enviarWhatsApp($targetGroup, $message, 'mentoria_ranking');
print_r($result);
