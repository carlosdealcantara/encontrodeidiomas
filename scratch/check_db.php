<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$stmt = $conn->prepare("UPDATE languages SET odysee_auth_token = '2EuEcv9s2kkgtyxREkd9G8tkZG5v2LFx' WHERE name LIKE '%Servo%'");
$stmt->execute();
echo "Updated " . $stmt->rowCount() . " rows for ServoCroata.";
