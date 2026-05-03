<?php
require_once 'config.php';
$links = getUsefulLinks();
echo json_encode($links, JSON_PRETTY_PRINT);
