<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
try {
    $stmt = $conn->query("SELECT id, name, odysee_auth_token, odysee_channel_name FROM languages WHERE name LIKE '%Inglês%'");
    $row = $stmt->fetch();
    print_r($row);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
