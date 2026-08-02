<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
require_once dirname(__DIR__) . '/includes/whatsapp_helper.php';
$result = enviarWhatsApp('5511999999999@s.whatsapp.net', 'Teste 123', 'meetup_cron');
print_r($result);
