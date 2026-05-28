<?php
require 'config.php';
$stmt = $conn->prepare("UPDATE settings SET setting_value = '@Novafase25ei' WHERE setting_key = 'hosts_app_password'");
$stmt->execute();
if ($stmt->rowCount() == 0) {
    $conn->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('hosts_app_password', '@Novafase25ei')");
}
echo 'Senha do Hosts App atualizada!';
?>
