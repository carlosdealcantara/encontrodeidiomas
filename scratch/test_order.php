<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();

$rows = $conn->query("
    SELECT l.id, l.name, l.flag_emoji, m.first_day, m.first_hour
    FROM languages l 
    LEFT JOIN (
        SELECT language_id, MIN(day_of_week) as first_day, MIN(time_hour) as first_hour 
        FROM meetings 
        WHERE active = 1 
        GROUP BY language_id
    ) m ON l.id = m.language_id
    WHERE l.active = 1 
    ORDER BY m.first_day ASC, m.first_hour ASC, l.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

print_r($rows);
