<?php
require_once __DIR__ . '/config.php';
$conn = connectDB();
$stmt = $conn->query("SELECT id, name FROM languages");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['id'] . " = " . $row['name'] . "\n";
}
