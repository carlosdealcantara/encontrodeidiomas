<?php
require_once dirname(__DIR__) . '/config.php';
$stmt = $conn->query("SELECT id, language_id, status, titulo_final, created_at, updated_at FROM odysee_publish_queue ORDER BY id DESC LIMIT 5;");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
