<?php
require_once "config.php";
require_once "includes/whatsapp_helper.php";
$data = fetchBaileysActivity('2026-06-30');
echo json_encode($data, JSON_PRETTY_PRINT);
