<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();

$rows = $conn->query("SELECT id, name, flag_emoji, active FROM languages ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: text/plain; charset=utf-8');
echo "id | name | flag | active\n";
echo str_repeat('-', 50) . "\n";
foreach ($rows as $r) {
    echo "{$r['id']} | {$r['name']} | {$r['flag_emoji']} | {$r['active']}\n";
}
