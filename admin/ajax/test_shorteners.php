<?php
$url = "https://odysee.com/@EncontrodeIdiomasFrances/2026_06_29";
echo "Testing clck.ru...\n";
$ch = curl_init('https://clck.ru/--');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['url' => $url]);
$res = curl_exec($ch);
echo "Result: $res\n\n";

echo "Testing cleanuri.com...\n";
$ch2 = curl_init('https://cleanuri.com/api/v1/shorten');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, ['url' => $url]);
$res2 = curl_exec($ch2);
echo "Result: $res2\n\n";

echo "Testing tinyurl.com...\n";
$ch3 = curl_init('https://tinyurl.com/api-create.php?url=' . urlencode($url));
curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
$res3 = curl_exec($ch3);
echo "Result: $res3\n\n";
