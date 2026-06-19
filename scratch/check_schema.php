<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();

$cols = $conn->query("DESCRIBE meetings")->fetchAll(PDO::FETCH_ASSOC);
echo "meetings cols:\n";
print_r($cols);

$meetings = $conn->query("SELECT * FROM meetings LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
echo "meetings data:\n";
print_r($meetings);
