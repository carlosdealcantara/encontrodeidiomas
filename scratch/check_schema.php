<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables:\n" . implode(", ", $tables) . "\n\n";

if (in_array('meetup_schedules', $tables)) {
    $cols = $conn->query("DESCRIBE meetup_schedules")->fetchAll(PDO::FETCH_ASSOC);
    echo "meetup_schedules cols:\n";
    print_r($cols);
} elseif (in_array('meetups', $tables)) {
    $cols = $conn->query("DESCRIBE meetups")->fetchAll(PDO::FETCH_ASSOC);
    echo "meetups cols:\n";
    print_r($cols);
}
