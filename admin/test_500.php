<?php
$EVOLUTION_API_URL = "http://136.248.92.126:8080/message/sendText/meetups";
$EVOLUTION_API_KEY = "SenhaMeetups2026";
$payload = json_encode([
    "number" => "120363170363315874@g.us",
    "textMessage" => ["text" => "Teste"]
]);
$ch = curl_init($EVOLUTION_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "apikey: $EVOLUTION_API_KEY"]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "HTTP: $httpcode\nResponse: $response\n";
?>
