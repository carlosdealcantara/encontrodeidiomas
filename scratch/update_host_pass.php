<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$conn->exec("UPDATE settings SET setting_value = '#eivoceai25' WHERE setting_key = 'hosts_app_password'");
echo "Senha atualizada!\n";
