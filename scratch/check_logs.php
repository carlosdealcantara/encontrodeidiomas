<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
echo "Testing DIRECT: ";
$ch = curl_init("http://136.248.92.126:3000/connection-status");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
echo curl_getinfo($ch, CURLINFO_HTTP_CODE) . " - " . curl_error($ch) . "\n";
curl_close($ch);
