<?php
require_once 'config.php';

// Get all hosts and languages
$hosts = getHosts();
$languages = getLanguages();

// Create language map (both string and integer keys)
$languageMap = [];
$languageMapInt = [];
foreach ($languages as $language) {
    $id = $language['id'];
    $languageMap[$id] = $language['name'];
    $languageMapInt[(int)$id] = $language['name']; // Cast to integer
}

// Output language map for debugging
echo "<h2>Language Map</h2>";
echo "<pre>";
print_r($languages);
echo "</pre>";

echo "<h2>Testing Host Languages</h2>";
foreach ($hosts as $host) {
    echo "<h3>{$host['full_name']}</h3>";
    echo "<p>Raw language data: <code>" . htmlspecialchars($host['languages']) . "</code></p>";
    
    // Process languages
    $hostLanguages = [];
    if (!empty($host['languages'])) {
        $languageIds = explode(',', $host['languages']);
        
        echo "<p>Language IDs: <code>" . implode(', ', $languageIds) . "</code></p>";
        
        foreach ($languageIds as $langId) {
            $langId = trim($langId);
            $numericLangId = (int)$langId;
            
            echo "<p>Checking langId: <code>$langId</code> (numeric: <code>$numericLangId</code>)</p>";
            echo "<p>Exists in string map: " . (isset($languageMap[$langId]) ? "YES → {$languageMap[$langId]}" : "NO") . "</p>";
            echo "<p>Exists in int map: " . (isset($languageMapInt[$numericLangId]) ? "YES → {$languageMapInt[$numericLangId]}" : "NO") . "</p>";
            
            // Try both string and numeric lookups
            if (isset($languageMap[$langId])) {
                $hostLanguages[] = $languageMap[$langId] . " (string key)";
            } elseif (isset($languageMapInt[$numericLangId])) {
                $hostLanguages[] = $languageMapInt[$numericLangId] . " (int key)";
            } else {
                $hostLanguages[] = "Unknown language ID: $langId";
            }
        }
    } else {
        echo "<p>No languages set for this host</p>";
    }
    
    echo "<p>Processed languages: <code>" . implode(', ', $hostLanguages) . "</code></p>";
    echo "<hr>";
}
?> 