<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include config
require_once 'config.php';

echo "<h1>Testing Online Events Loading</h1>";

try {
    // Test getting all events
    $events = getEvents();
    echo "<p>✅ getEvents function returned " . count($events) . " events</p>";
    
    // Inspect the first event
    if (count($events) > 0) {
        echo "<h2>First Event Data:</h2>";
        echo "<pre>";
        print_r($events[0]);
        echo "</pre>";
    }
    
    // Get events by language
    $languages = getLanguages();
    if (count($languages) > 0) {
        $firstLanguageId = $languages[0]['id'];
        echo "<h2>Events for Language ID {$firstLanguageId} ({$languages[0]['name']}):</h2>";
        
        $languageEvents = getEventsByLanguage($firstLanguageId);
        echo "<p>✅ getEventsByLanguage returned " . count($languageEvents) . " events</p>";
        
        if (count($languageEvents) > 0) {
            echo "<h3>First Language Event:</h3>";
            echo "<pre>";
            print_r($languageEvents[0]);
            echo "</pre>";
        }
    }
    
    // Test AJAX endpoints
    echo "<h2>Testing AJAX Endpoints:</h2>";
    
    // Check if AJAX files exist
    if (file_exists('ajax/get_language_events.php')) {
        echo "<p>✅ ajax/get_language_events.php file exists</p>";
    } else {
        echo "<p>❌ ajax/get_language_events.php file not found</p>";
    }
    
    if (file_exists('ajax/get_events_by_language.php')) {
        echo "<p>✅ ajax/get_events_by_language.php file exists</p>";
    } else {
        echo "<p>❌ ajax/get_events_by_language.php file not found</p>";
    }
    
    echo "<p>To test if online.php is working correctly, <a href='online.php'>visit the online events page</a>.</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?> 