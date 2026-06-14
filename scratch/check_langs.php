<?php
header('Content-Type: text/plain; charset=utf-8');
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$rows = $conn->query("SELECT id, name, flag_emoji FROM languages WHERE active=1 ORDER BY name")->fetchAll();
foreach ($rows as $r) {
    echo $r['id'] . ' | ' . $r['flag_emoji'] . ' ' . $r['name'] . "\n";
}
