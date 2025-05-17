<?php
require_once 'config.php';

// Get sample hosts with profile pictures
$conn = connectDB();
$stmt = $conn->prepare("SELECT id, full_name, profile_picture FROM hosts WHERE profile_picture IS NOT NULL AND profile_picture != '' LIMIT 10");
$stmt->execute();
$hosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h1>Host Profile Picture Data</h1>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Name</th><th>Image Path in DB</th><th>Frontend Path</th><th>Admin Path</th><th>Image</th></tr>";

foreach ($hosts as $host) {
    $startsWithAssets = strpos($host['profile_picture'], 'assets/') === 0;
    $frontendPath = $startsWithAssets 
        ? $host['profile_picture'] 
        : 'assets/images/' . $host['profile_picture'];
    $adminPath = '../' . $frontendPath;
    
    echo "<tr>";
    echo "<td>{$host['id']}</td>";
    echo "<td>{$host['full_name']}</td>";
    echo "<td>" . htmlspecialchars($host['profile_picture']) . "</td>";
    echo "<td>" . htmlspecialchars($frontendPath) . "</td>";
    echo "<td>" . htmlspecialchars($adminPath) . "</td>";
    echo "<td><img src='$frontendPath' style='max-width: 100px; max-height: 100px;'></td>";
    echo "</tr>";
}

echo "</table>";
?> 