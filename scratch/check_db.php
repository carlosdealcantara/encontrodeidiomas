<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once dirname(__DIR__) . '/config.php';
try {
    $conn = connectDB();
    $stmt = $conn->query("SELECT * FROM odysee_publish_queue ORDER BY id DESC LIMIT 5");
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($res, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
