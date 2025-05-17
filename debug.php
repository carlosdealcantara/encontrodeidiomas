<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include config
require_once 'config.php';

echo "<h1>Debug Page</h1>";

// Test database connection
try {
    $conn = connectDB();
    echo "<p>✅ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// Check events table structure
try {
    $stmt = $conn->prepare("DESCRIBE events");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Events Table Structure:</h2>";
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p>❌ Could not check events table structure: " . $e->getMessage() . "</p>";
}

// Test getEvents function
try {
    $events = getEvents();
    echo "<h2>Sample Events Data:</h2>";
    echo "<p>Found " . count($events) . " events</p>";
    
    if (count($events) > 0) {
        echo "<h3>First Event:</h3>";
        echo "<pre>";
        print_r($events[0]);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "<p>❌ getEvents failed: " . $e->getMessage() . "</p>";
}

// Test getEventsByLanguage function
try {
    // Get first language ID
    $languages = getLanguages();
    if (count($languages) > 0) {
        $firstLanguageId = $languages[0]['id'];
        $events = getEventsByLanguage($firstLanguageId);
        
        echo "<h2>Events for Language ID $firstLanguageId:</h2>";
        echo "<p>Found " . count($events) . " events</p>";
        
        if (count($events) > 0) {
            echo "<h3>First Event for this language:</h3>";
            echo "<pre>";
            print_r($events[0]);
            echo "</pre>";
        }
    } else {
        echo "<p>No languages found to test</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ getEventsByLanguage failed: " . $e->getMessage() . "</p>";
}
?> 