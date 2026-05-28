<?php
$EVOLUTION_API_URL = "http://136.248.92.126:8080/instance/connectionState/meetups";
$EVOLUTION_API_KEY = "SenhaMeetups2026";
$ch = curl_init($EVOLUTION_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["apikey: " . $EVOLUTION_API_KEY]);
$response = curl_exec($ch);
echo $response;
?>
