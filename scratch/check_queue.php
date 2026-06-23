<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();

$stmt = $conn->query("SELECT id, status, language_id, topico, drive_file_name FROM odysee_publish_queue");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($results);
echo "</pre>";
?>
