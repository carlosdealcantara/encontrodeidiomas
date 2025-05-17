<?php
require_once 'config.php';

// Get host data with language information
$conn = connectDB();
$stmt = $conn->prepare("SELECT id, full_name, languages FROM hosts WHERE languages IS NOT NULL AND languages != '' LIMIT 5");
$stmt->execute();
$hosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all languages for mapping
$languages = getLanguages();
$languageMap = [];
foreach ($languages as $language) {
    $languageMap[$language['id']] = $language['name'];
}

echo "<h1>Host Languages Debug</h1>";
echo "<h2>All Language IDs and Names</h2>";
echo "<pre>";
print_r($languageMap);
echo "</pre>";

echo "<h2>Sample Host Language Data</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Host ID</th><th>Host Name</th><th>Raw Language Data</th><th>Language Type</th><th>Processed Languages</th></tr>";

foreach ($hosts as $host) {
    echo "<tr>";
    echo "<td>{$host['id']}</td>";
    echo "<td>{$host['full_name']}</td>";
    echo "<td>" . htmlspecialchars($host['languages']) . "</td>";
    
    // Determine if languages are stored as IDs or names
    $languageType = "Unknown";
    $processedLanguages = [];
    
    if (!empty($host['languages'])) {
        $languageValues = explode(',', $host['languages']);
        $firstValue = trim($languageValues[0]);
        
        if (is_numeric($firstValue) && isset($languageMap[$firstValue])) {
            $languageType = "IDs";
            foreach ($languageValues as $langId) {
                $langId = trim($langId);
                if (isset($languageMap[$langId])) {
                    $processedLanguages[] = $languageMap[$langId] . " (ID: $langId)";
                }
            }
        } else {
            $languageType = "Names";
            $processedLanguages = array_map('trim', $languageValues);
        }
    }
    
    echo "<td>$languageType</td>";
    echo "<td>" . implode("<br>", $processedLanguages) . "</td>";
    echo "</tr>";
}

echo "</table>";
?> 