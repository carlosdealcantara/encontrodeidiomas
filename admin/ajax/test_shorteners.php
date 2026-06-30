<?php
$url = "https://odysee.com/@EncontrodeIdiomasFrances/2026_06_29";

echo "Testing gg.gg...\n";
$ch = curl_init('http://gg.gg/create');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['custom_path' => '', 'url' => $url]);
$res = curl_exec($ch);
echo "Result: $res\n\n";

echo "Testing tinyurl.com...\n";
$ch3 = curl_init('https://tinyurl.com/api-create.php?url=' . urlencode($url));
curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
$res3 = curl_exec($ch3);
echo "Result: $res3\n\n";
