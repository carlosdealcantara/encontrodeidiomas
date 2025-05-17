<?php
require_once '../config.php';

echo "<h1>Admin Directory Path Tests</h1>";
echo "<p>Current PHP path: " . __FILE__ . "</p>";

// Test cases for different path formats
$testCases = [
    [
        'scenario' => 'Path with assets/ prefix',
        'db_path' => 'assets/images/sample.jpg',
    ],
    [
        'scenario' => 'Path without assets/ prefix',
        'db_path' => 'sample.jpg',
    ],
    [
        'scenario' => 'Path in subdirectory',
        'db_path' => 'hosts/sample.jpg',
    ],
    [
        'scenario' => 'Default image',
        'db_path' => '',
    ]
];

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr>
        <th>Scenario</th>
        <th>DB Path</th>
        <th>Generated Path</th>
        <th>Image Tag</th>
    </tr>";

foreach ($testCases as $test) {
    $db_path = $test['db_path'];
    $startsWithAssets = !empty($db_path) && strpos($db_path, 'assets/') === 0;
    
    // This is the corrected path handling for the admin directory
    $imagePath = !empty($db_path) 
        ? '../' . ($startsWithAssets ? $db_path : 'assets/images/' . $db_path)
        : '../assets/images/HostSemFoto.png';
    
    $imageTag = "<img src='$imagePath' style='max-width: 100px; max-height: 100px;'>";
    
    echo "<tr>";
    echo "<td>{$test['scenario']}</td>";
    echo "<td>" . htmlspecialchars($db_path) . "</td>";
    echo "<td>" . htmlspecialchars($imagePath) . "</td>";
    echo "<td>$imageTag</td>";
    echo "</tr>";
}

echo "</table>";

// Test sample hosts from database
echo "<h2>Sample Hosts from Database</h2>";

$conn = connectDB();
$stmt = $conn->prepare("SELECT id, full_name, profile_picture FROM hosts WHERE profile_picture IS NOT NULL AND profile_picture != '' LIMIT 5");
$stmt->execute();
$hosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr>
        <th>Host ID</th>
        <th>Host Name</th>
        <th>Raw Path</th>
        <th>Generated Path</th>
        <th>Image Preview</th>
    </tr>";

foreach ($hosts as $host) {
    $startsWithAssets = strpos($host['profile_picture'], 'assets/') === 0;
    $imagePath = '../' . ($startsWithAssets 
        ? $host['profile_picture'] 
        : 'assets/images/' . $host['profile_picture']);
    
    echo "<tr>";
    echo "<td>{$host['id']}</td>";
    echo "<td>{$host['full_name']}</td>";
    echo "<td>" . htmlspecialchars($host['profile_picture']) . "</td>";
    echo "<td>" . htmlspecialchars($imagePath) . "</td>";
    echo "<td><img src='$imagePath' style='max-width: 100px; max-height: 100px;'></td>";
    echo "</tr>";
}

echo "</table>";

echo "<p><a href='../debug_images.php'>Back to main debug page</a></p>";
?> 