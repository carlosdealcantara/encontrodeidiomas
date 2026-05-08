<?php
/**
 * i18n Audit Tool
 * Compara pt.json e en.json para encontrar chaves faltantes.
 */

function getKeys($array, $prefix = '') {
    $keys = [];
    foreach ($array as $key => $value) {
        $fullKey = $prefix . $key;
        if (is_array($value)) {
            $keys = array_merge($keys, getKeys($value, $fullKey . '.'));
        } else {
            $keys[] = $fullKey;
        }
    }
    return $keys;
}

$pt = json_decode(file_get_contents(__DIR__ . '/pt.json'), true);
$en = json_decode(file_get_contents(__DIR__ . '/en.json'), true);

$ptKeys = getKeys($pt);
$enKeys = getKeys($en);

$missingInEn = array_diff($ptKeys, $enKeys);
$missingInPt = array_diff($enKeys, $ptKeys);

echo "--- Auditoria de i18n ---\n";

if (empty($missingInEn) && empty($missingInPt)) {
    echo "✅ Tudo sincronizado! (Total: " . count($ptKeys) . " chaves)\n";
} else {
    if (!empty($missingInEn)) {
        echo "❌ Faltando no EN:\n";
        foreach ($missingInEn as $k) echo "  - $k\n";
    }
    if (!empty($missingInPt)) {
        echo "❌ Faltando no PT:\n";
        foreach ($missingInPt as $k) echo "  - $k\n";
    }
}
