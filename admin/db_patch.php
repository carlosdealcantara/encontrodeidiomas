<?php
require_once __DIR__ . '/../config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

$conn = connectDB();
try {
    $stmt = $conn->query("DESCRIBE mentoria_alunos");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
