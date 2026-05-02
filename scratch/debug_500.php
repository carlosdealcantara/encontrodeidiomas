<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../config.php';
$meetings = getMeetings();
echo "Total de encontros: " . count($meetings);
echo "<pre>";
print_r($meetings);
echo "</pre>";
