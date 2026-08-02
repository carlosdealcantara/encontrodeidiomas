<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$stmtMeetings = $conn->prepare("SELECT id, language_id, time_hour FROM meetings WHERE active = 1 AND day_of_week = 4");
$stmtMeetings->execute();
print_r($stmtMeetings->fetchAll());
