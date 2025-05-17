<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include config
require_once 'config.php';

echo "<h1>Events Table Structure</h1>";

// Test database connection
try {
    $conn = connectDB();
    
    // Get columns for events table
    $stmt = $conn->prepare("DESCRIBE events");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Events Table Columns:</h2>";
    echo "<table border='1' cellpadding='4'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Get sample event data
    $stmt = $conn->prepare("SELECT * FROM events LIMIT 1");
    $stmt->execute();
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($event) {
        echo "<h2>Sample Event Data:</h2>";
        echo "<pre>";
        print_r($event);
        echo "</pre>";
        
        // Check for description columns
        echo "<h2>Description Columns Check:</h2>";
        echo "<ul>";
        echo "<li>Has 'description' column: " . (isset($event['description']) ? "YES" : "NO") . "</li>";
        echo "<li>Has 'online_description' column: " . (isset($event['online_description']) ? "YES" : "NO") . "</li>";
        if (isset($event['description'])) {
            echo "<li>Value of 'description': " . (empty($event['description']) ? "EMPTY" : htmlspecialchars(substr($event['description'], 0, 100)) . "...") . "</li>";
        }
        if (isset($event['online_description'])) {
            echo "<li>Value of 'online_description': " . (empty($event['online_description']) ? "EMPTY" : htmlspecialchars(substr($event['online_description'], 0, 100)) . "...") . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No events found in the database.</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?> 