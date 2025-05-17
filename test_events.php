<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Database connection
    $dbHost = 'srv1437.hstgr.io';
    $dbName = 'u879045076_central';
    $dbUser = 'u879045076_carlos';
    $dbPass = '@Car8lafe';
    
    $conn = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "<h2>Connected to database successfully!</h2>";
    
    // Simple direct query to events table
    echo "<h2>Testing direct query to events table:</h2>";
    
    $stmt = $conn->query("SHOW COLUMNS FROM events");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Columns in events table:</h3>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>{$column['Field']} ({$column['Type']})</li>";
    }
    echo "</ul>";
    
    // Try to query events with specific columns
    echo "<h2>Testing explicit query for description columns:</h2>";
    $stmt = $conn->query("SELECT id, language_id, day_of_week, time_hour, description, online_description FROM events LIMIT 5");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($events) > 0) {
        echo "<h3>Sample data from first " . count($events) . " events:</h3>";
        echo "<table border='1' cellpadding='4'>";
        echo "<tr><th>ID</th><th>Language</th><th>Day</th><th>Hour</th><th>description</th><th>online_description</th></tr>";
        
        foreach ($events as $event) {
            echo "<tr>";
            echo "<td>{$event['id']}</td>";
            echo "<td>{$event['language_id']}</td>";
            echo "<td>{$event['day_of_week']}</td>";
            echo "<td>{$event['time_hour']}</td>";
            echo "<td>" . (isset($event['description']) ? substr($event['description'], 0, 50) . "..." : "NOT SET") . "</td>";
            echo "<td>" . (isset($event['online_description']) ? substr($event['online_description'], 0, 50) . "..." : "NOT SET") . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No events found in database.</p>";
    }
    
} catch (Exception $e) {
    echo "<h2>Error occurred:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    
    // Show trace for debugging
    echo "<pre>";
    $e->getTraceAsString();
    echo "</pre>";
}
?> 