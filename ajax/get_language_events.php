<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$languageId = isset($_GET['language_id']) ? (int)$_GET['language_id'] : 0;
if (!$languageId) { echo json_encode([]); exit; }

$events = getEventsByLanguage($languageId);
echo json_encode($events);
