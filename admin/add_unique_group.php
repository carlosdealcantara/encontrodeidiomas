<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

try {
    // Add UNIQUE constraint to group_id
    $conn->exec("ALTER TABLE meetup_whatsapp_groups ADD UNIQUE (group_id)");
    echo "UNIQUE constraint added to group_id successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
