<?php
require_once 'config.php';

// Get host data with profile pictures
$conn = connectDB();
$stmt = $conn->prepare("SELECT id, full_name, profile_picture FROM hosts WHERE profile_picture IS NOT NULL AND profile_picture != '' LIMIT 10");
$stmt->execute();
$hosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h1>Host Profile Pictures Debug</h1>";

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr>
        <th>Host ID</th>
        <th>Host Name</th>
        <th>Raw Path</th>
        <th>Starts with assets/</th>
        <th>Full Path for Front-end</th>
        <th>Full Path for Admin</th>
        <th>Image Preview</th>
    </tr>";

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
    echo "<td>" . ($startsWithAssets ? 'Yes' : 'No') . "</td>";
    echo "<td>" . htmlspecialchars($frontendPath) . "</td>";
    echo "<td>" . htmlspecialchars($adminPath) . "</td>";
    echo "<td><img src='$frontendPath' style='max-width: 100px; max-height: 100px;'></td>";
    echo "</tr>";
}

echo "</table>";

// Also check all possible path variations
echo "<h2>Path Variations Test</h2>";

// Create a table to test different path scenarios
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr>
        <th>Scenario</th>
        <th>Path in Database</th>
        <th>Generated Frontend Path</th>
        <th>Generated Admin Path</th>
        <th>Frontend Display</th>
    </tr>";

// Test cases
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

foreach ($testCases as $test) {
    $db_path = $test['db_path'];
    $startsWithAssets = !empty($db_path) && strpos($db_path, 'assets/') === 0;
    
    $frontendPath = !empty($db_path) 
        ? ($startsWithAssets ? $db_path : 'assets/images/' . $db_path)
        : 'assets/images/HostSemFoto.png';
        
    $adminPath = '../' . $frontendPath;
    
    echo "<tr>";
    echo "<td>{$test['scenario']}</td>";
    echo "<td>" . htmlspecialchars($db_path) . "</td>";
    echo "<td>" . htmlspecialchars($frontendPath) . "</td>";
    echo "<td>" . htmlspecialchars($adminPath) . "</td>";
    echo "<td>Path would generate: <code>$frontendPath</code></td>";
    echo "</tr>";
}

echo "</table>";

// Check relative paths from admin directory
echo "<h2>Admin Directory Path Test</h2>";
echo "<p>This shows what paths would be needed when viewing from the admin directory</p>";
echo "<p>Current PHP path: " . __FILE__ . "</p>";

echo "<a href='admin/test_paths.php'>Test paths from admin directory</a>";

// Test paths for default images
echo "<h2>Default Image Test</h2>";
echo "<p>Front-end default: <code>assets/images/HostSemFoto.png</code></p>";
echo "<p>Admin default: <code>../assets/images/HostSemFoto.png</code></p>";

echo "<div style='display: flex; gap: 20px;'>";
echo "<div>";
echo "<h3>Front-end default image:</h3>";
echo "<img src='assets/images/HostSemFoto.png' style='max-width: 100px; max-height: 100px;'>";
echo "</div>";

echo "<div>";
echo "<h3>Admin path from root:</h3>";
echo "<img src='../assets/images/HostSemFoto.png' style='max-width: 100px; max-height: 100px;'>";
echo "</div>";
echo "</div>";
?> 