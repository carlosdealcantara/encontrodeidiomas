<?php
$token = 'invalid123';
$url = "https://api.na-backend.odysee.com/api/v1/proxy";
$data = json_encode(["jsonrpc" => "2.0", "method" => "user_me", "id" => 1]);
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Cookie: auth_token=' . $token
]);
$res = curl_exec($ch);
curl_close($ch);

echo $res;
