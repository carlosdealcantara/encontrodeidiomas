<?php
require_once __DIR__ . '/../config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

$conn = connectDB();
try {
    $conn->exec("ALTER TABLE mentoria_desafio_streaks ADD COLUMN member_name VARCHAR(255) NULL");
    echo "Column added.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
