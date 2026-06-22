<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$stmt = $conn->query("UPDATE odysee_publish_queue SET status = 'error', error_message = 'Cancelado por teste' WHERE id != 1");
echo "Cancelled " . $stmt->rowCount() . " other jobs.";
