<?php
require_once __DIR__ . '/admin/includes/db.php';
$stmt = $pdo->query("SELECT id, name FROM languages");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['id'] . " = " . $row['name'] . "\n";
}
