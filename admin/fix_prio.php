<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();
$conn->exec("UPDATE useful_links SET order_index = 10 WHERE id = 5");
$conn->exec("UPDATE useful_links SET order_index = 9 WHERE id = 1");
echo "Prioridades ajustadas com sucesso!";
unlink(__FILE__);
