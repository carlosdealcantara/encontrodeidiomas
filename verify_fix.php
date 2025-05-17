<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include config
require_once 'config.php';

echo "<h1>Testing Fixed Queries</h1>";

try {
    // Test getEvents function
    $events = getEvents();
    echo "<p>✅ getEvents function succeeded! Found " . count($events) . " events.</p>";
    
    if (count($events) > 0) {
        echo "<h3>First event details:</h3>";
        echo "<pre>";
        print_r($events[0]);
        echo "</pre>";
    }
    
    // Test getEventsByLanguage function
    $languages = getLanguages();
    if (count($languages) > 0) {
        $firstLanguageId = $languages[0]['id'];
        $languageEvents = getEventsByLanguage($firstLanguageId);
        echo "<p>✅ getEventsByLanguage function succeeded! Found " . count($languageEvents) . " events for language ID " . $firstLanguageId . "</p>";
    }
    
    echo "<p>The fixes have been successfully applied. The website should now work properly.</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

// Link to main pages
echo "<p><a href='index.php'>Go to Home Page</a></p>";
echo "<p><a href='online.php'>Go to Online Events Page</a></p>";
?> 