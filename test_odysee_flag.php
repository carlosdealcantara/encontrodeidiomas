<?php
require_once "config.php";
$conn = connectDB();
$conn->exec("INSERT IGNORE INTO system_settings (chave, valor) VALUES ('wpp_odysee_ativo', '0')");
echo "OK";
