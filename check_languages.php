<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

echo "<h1>Host Languages Check</h1>";

// Test the debugging function
$hostsDebug = getHostsDebug();

if (isset($hostsDebug['ERROR'])) {
    echo "<p style='color:red;font-weight:bold;'>ERROR: " . $hostsDebug['ERROR'] . "</p>";
} else {
    echo "<p>Successfully retrieved " . count($hostsDebug) . " hosts with languages information.</p>";
    
    // Get all languages to create a map
    $languages = getLanguages();
    $languageMap = [];
    foreach ($languages as $language) {
        $languageMap[$language['id']] = $language['name'];
    }
    
    echo "<h2>Languages Available</h2>";
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Name</th></tr>";
    foreach ($languages as $language) {
        echo "<tr><td>{$language['id']}</td><td>{$language['name']}</td></tr>";
    }
    echo "</table>";
    
    echo "<h2>Host Languages Data</h2>";
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr><th>Host Name</th><th>Has Languages?</th><th>Raw Language Data</th><th>Language IDs</th><th>Mapped Languages</th></tr>";
    
    foreach ($hostsDebug as $host) {
        echo "<tr>";
        echo "<td>{$host['full_name']}</td>";
        echo "<td>" . ($host['has_languages'] ? "YES" : "NO") . "</td>";
        echo "<td>" . htmlspecialchars($host['languages_raw']) . "</td>";
        
        // Display language IDs
        echo "<td>";
        if (!empty($host['language_ids'])) {
            foreach ($host['language_ids'] as $langId) {
                echo htmlspecialchars($langId) . "<br>";
            }
        } else {
            echo "None";
        }
        echo "</td>";
        
        // Map and display language names
        echo "<td>";
        if (!empty($host['language_ids'])) {
            foreach ($host['language_ids'] as $langId) {
                $langId = trim($langId); // Ensure no whitespace
                if (isset($languageMap[$langId])) {
                    echo "<span style='color:green'>" . $languageMap[$langId] . "</span><br>";
                } elseif (isset($languageMap[(int)$langId])) {
                    echo "<span style='color:blue'>" . $languageMap[(int)$langId] . "</span><br>";
                } else {
                    echo "<span style='color:red'>Unknown ID: " . htmlspecialchars($langId) . "</span><br>";
                }
            }
        } else {
            echo "None";
        }
        echo "</td>";
        
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Check if any host has empty languages
    $emptyLanguagesHosts = array_filter($hostsDebug, function($host) {
        return !$host['has_languages'];
    });
    
    if (!empty($emptyLanguagesHosts)) {
        echo "<h2>Hosts with NO Languages Set</h2>";
        echo "<p>The following hosts have no languages set in the database:</p>";
        echo "<ul>";
        foreach ($emptyLanguagesHosts as $host) {
            echo "<li>" . htmlspecialchars($host['full_name']) . "</li>";
        }
        echo "</ul>";
        echo "<p>These hosts will only show the default language (Português) in the interface.</p>";
    }
    
    // Check filtering by a specific language
    if (!empty($languages)) {
        $testLang = $languages[0]; // Use first language for testing
        echo "<h2>Testing Filtering by Language: {$testLang['name']}</h2>";
        
        $matchingHosts = [];
        foreach ($hostsDebug as $host) {
            if (!empty($host['language_ids'])) {
                foreach ($host['language_ids'] as $langId) {
                    $langId = trim($langId);
                    if ($langId == $testLang['id'] || (int)$langId === (int)$testLang['id']) {
                        $matchingHosts[] = $host['full_name'];
                        break;
                    }
                }
            }
        }
        
        if (!empty($matchingHosts)) {
            echo "<p>Hosts that speak {$testLang['name']}:</p>";
            echo "<ul>";
            foreach ($matchingHosts as $hostName) {
                echo "<li>" . htmlspecialchars($hostName) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>No hosts found that speak {$testLang['name']}.</p>";
        }
    }
}
?> 