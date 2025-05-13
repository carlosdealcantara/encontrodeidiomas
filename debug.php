<?php
require_once 'config.php';

// Get data
$hosts = getHosts();
$languages = getLanguages();

// Create language map
$languageMap = [];
foreach ($languages as $language) {
    $languageMap[$language['id']] = $language['name'];
}

// Debug header
echo "<h1>Database Debug Information</h1>";

// Show Language Map
echo "<h2>Language Map (ID => Name)</h2>";
echo "<pre>";
print_r($languageMap);
echo "</pre>";

// Show Host Languages
echo "<h2>Host Languages</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Host Name</th><th>Raw Language IDs</th><th>Processed Languages</th></tr>";

foreach ($hosts as $host) {
    echo "<tr>";
    echo "<td>{$host['full_name']}</td>";
    echo "<td>{$host['languages']}</td>";
    
    $hostLanguages = [];
    if (!empty($host['languages'])) {
        $languageIds = explode(',', $host['languages']);
        foreach ($languageIds as $langId) {
            $langId = trim($langId);
            if (isset($languageMap[$langId])) {
                $hostLanguages[] = $languageMap[$langId] . " (ID: $langId)";
            } else {
                $hostLanguages[] = "Unknown language ID: $langId";
            }
        }
    }
    
    echo "<td>" . implode("<br>", $hostLanguages) . "</td>";
    echo "</tr>";
}

echo "</table>";
?> 