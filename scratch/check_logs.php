<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$stmt = $conn->query("SELECT * FROM meetup_whatsapp_groups");
print_r($stmt->fetchAll());
