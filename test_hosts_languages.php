<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// Test direct database connection
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
        DB_USER, 
        DB_PASS, 
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "<p>✅ Database connection successful!</p>";
} catch (PDOException $e) {
    die("<p>❌ Database connection failed: " . $e->getMessage() . "</p>");
}

// Get data directly with a custom query - specifically targeting the languages column
try {
    $stmt = $conn->prepare("SELECT id, full_name, languages FROM hosts WHERE active = 1 ORDER BY full_name");
    $stmt->execute();
    $hosts_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>✅ Raw hosts query executed successfully! Found " . count($hosts_raw) . " hosts.</p>";
} catch (PDOException $e) {
    die("<p>❌ Raw hosts query failed: " . $e->getMessage() . "</p>");
}

// Get languages for mapping
try {
    $stmt = $conn->prepare("SELECT id, name FROM languages WHERE active = 1 ORDER BY name");
    $stmt->execute();
    $languages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>✅ Languages query executed successfully! Found " . count($languages) . " languages.</p>";
} catch (PDOException $e) {
    die("<p>❌ Languages query failed: " . $e->getMessage() . "</p>");
}

// Create language maps (both string and int keys)
$languageMap = [];
$languageMapInt = [];
foreach ($languages as $language) {
    $id = $language['id'];
    $languageMap[$id] = $language['name'];
    $languageMapInt[(int)$id] = $language['name'];
}

echo "<h2>Testing Hosts Languages Column</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr style='background-color: #f0f0f0;'><th>Host ID</th><th>Host Name</th><th>Raw Languages</th><th>Language IDs</th><th>Mapped Languages</th></tr>";

foreach ($hosts_raw as $host) {
    echo "<tr>";
    echo "<td>{$host['id']}</td>";
    echo "<td>{$host['full_name']}</td>";
    echo "<td>" . (empty($host['languages']) ? "<em>empty</em>" : htmlspecialchars($host['languages'])) . "</td>";
    
    // Process language IDs
    $languageIds = [];
    if (!empty($host['languages'])) {
        $languageIds = array_map('trim', explode(',', $host['languages']));
    }
    echo "<td>" . implode(', ', $languageIds) . "</td>";
    
    // Map IDs to names
    $hostLanguages = [];
    foreach ($languageIds as $langId) {
        // Try both string and int lookup
        if (isset($languageMap[$langId])) {
            $hostLanguages[] = "<span style='color:green'>" . $languageMap[$langId] . " (string key: $langId)</span>";
        } elseif (isset($languageMapInt[(int)$langId])) {
            $hostLanguages[] = "<span style='color:blue'>" . $languageMapInt[(int)$langId] . " (int key: $langId)</span>";
        } else {
            $hostLanguages[] = "<span style='color:red'>Unknown ID: $langId</span>";
        }
    }
    
    echo "<td>" . (empty($hostLanguages) ? "<em>No languages mapped</em>" : implode('<br>', $hostLanguages)) . "</td>";
    echo "</tr>";
}

echo "</table>";

// Check if code for filtering by language is working
echo "<h2>Testing Language Filtering</h2>";
echo "<p>This tests whether hosts can be filtered by a specific language correctly.</p>";

// Choose a language to test filtering
$testLanguage = !empty($languages) ? $languages[0]['name'] : '';
$testLanguageId = !empty($languages) ? $languages[0]['id'] : '';

if (!empty($testLanguage)) {
    echo "<p>Testing filtering for language: <strong>{$testLanguage}</strong> (ID: {$testLanguageId})</p>";
    
    // Hosts that should match this language
    $matchingHosts = [];
    foreach ($hosts_raw as $host) {
        if (!empty($host['languages'])) {
            $hostLangIds = array_map('trim', explode(',', $host['languages']));
            
            // Check if host has this language (using both string and int comparison)
            if (in_array($testLanguageId, $hostLangIds) || in_array((string)$testLanguageId, $hostLangIds)) {
                $matchingHosts[] = $host['full_name'];
            }
        }
    }
    
    if (!empty($matchingHosts)) {
        echo "<p>✅ Found " . count($matchingHosts) . " hosts that speak {$testLanguage}:</p>";
        echo "<ul>";
        foreach ($matchingHosts as $name) {
            echo "<li>{$name}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>❌ No hosts found that speak {$testLanguage}. This could indicate a problem with the language filtering.</p>";
    }
} else {
    echo "<p>❌ No languages available to test filtering with.</p>";
}

// Test function used in equipe.php
echo "<h2>Testing the getHosts() Function</h2>";
$hosts_function = getHosts();
echo "<p>The getHosts() function returned " . count($hosts_function) . " hosts.</p>";

// Compare with direct query
$matching = count($hosts_function) === count($hosts_raw);
echo $matching 
    ? "<p>✅ The number of hosts matches the direct query. This suggests getHosts() is working correctly.</p>"
    : "<p>❌ The number of hosts doesn't match the direct query. This suggests getHosts() might not be working correctly.</p>";

// Check if languages column is actually being returned by getHosts()
if (!empty($hosts_function)) {
    $firstHost = $hosts_function[0];
    echo "<p>First host from getHosts() has these fields: " . implode(', ', array_keys($firstHost)) . "</p>";
    
    if (isset($firstHost['languages'])) {
        echo "<p>✅ The 'languages' column is included in the getHosts() result.</p>";
    } else {
        echo "<p>❌ The 'languages' column is NOT included in the getHosts() result. This is the root of the problem!</p>";
    }
}
?> 