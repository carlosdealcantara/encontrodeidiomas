<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include config
require_once 'config.php';

echo "<h1>Hosts Table Structure</h1>";

// Test database connection
try {
    $conn = connectDB();
    
    // Get columns for hosts table
    $stmt = $conn->prepare("DESCRIBE hosts");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Hosts Table Columns:</h2>";
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
    
    // Get sample host data
    $stmt = $conn->prepare("SELECT * FROM hosts LIMIT 1");
    $stmt->execute();
    $host = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($host) {
        echo "<h2>Sample Host Data:</h2>";
        echo "<pre>";
        print_r($host);
        echo "</pre>";
        
        // Check for description columns
        echo "<h2>Description Columns Check:</h2>";
        echo "<ul>";
        echo "<li>Has 'description' column: " . (array_key_exists('description', $host) ? "YES" : "NO") . "</li>";
        echo "<li>Has 'online_description' column: " . (array_key_exists('online_description', $host) ? "YES" : "NO") . "</li>";
        echo "<li>Has 'inperson_description' column: " . (array_key_exists('inperson_description', $host) ? "YES" : "NO") . "</li>";
        echo "<li>Has 'technical_description' column: " . (array_key_exists('technical_description', $host) ? "YES" : "NO") . "</li>";
        
        if (array_key_exists('description', $host)) {
            echo "<li>Value of 'description': " . (empty($host['description']) ? "EMPTY" : htmlspecialchars(substr($host['description'], 0, 100)) . "...") . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No hosts found in the database.</p>";
    }
    
    // Link to main pages
    echo "<p><a href='equipe.php'>Go to Team Page</a></p>";
    echo "<p><a href='online.php'>Go to Online Events Page</a></p>";
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?> 