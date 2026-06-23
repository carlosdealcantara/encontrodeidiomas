<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
try {
    $conn->exec("DELETE FROM odysee_publish_queue WHERE language_id = 1");
    echo "Deleted queue for English.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
