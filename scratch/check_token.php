<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
try {
    $stmt = $conn->query("SELECT name, odysee_auth_token FROM languages WHERE name = 'Inglês'");
    $row = $stmt->fetch();
    echo "Idioma: " . $row['name'] . "\n";
    echo "Token length: " . strlen($row['odysee_auth_token']) . "\n";
    echo "Token prefix: " . substr($row['odysee_auth_token'], 0, 10) . "...\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
