<?php
require_once 'config.php';
$links = getUsefulLinks();
foreach ($links as $l) {
    echo "ID: " . $l['id'] . " | Title: " . $l['title'] . "\n";
}
