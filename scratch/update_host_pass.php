<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
updateSetting('hosts_app_password', '#eivoceai25');
echo "Senha atualizada!\n";
