<?php
require_once __DIR__ . '/includes/whatsapp_helper.php';
$config = getMentoriaConfig();
echo "Template practice_cancel:<br>";
var_dump($config['templates']['practice_cancel'] ?? 'NOT_SET');
echo "<br>Template class_cancel:<br>";
var_dump($config['templates']['class_cancel'] ?? 'NOT_SET');
