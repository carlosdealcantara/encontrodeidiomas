<?php
$token = '8926635028:AAFtlj0HtwCyn0AqOmFyeyXg8gvzAAPuaxc';
$url = "https://api.telegram.org/bot{$token}/getUpdates";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
curl_close($ch);
echo $result;
