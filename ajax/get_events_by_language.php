<?php
header('Content-Type: application/json');

// Validate input
if (!isset($_GET['language_id']) || !is_numeric($_GET['language_id'])) {
    echo json_encode([
        'error' => 'Invalid language ID',
        'events' => [],
        'language' => ''
    ]);
    exit;
}

// Get language ID
$languageId = (int)$_GET['language_id'];

// Include configuration
require_once '../config.php';

try {
    // Get language info
    $conn = connectDB();
    $languageStmt = $conn->prepare("SELECT * FROM languages WHERE id = :id AND active = 1");
    $languageStmt->bindParam(':id', $languageId, PDO::PARAM_INT);
    $languageStmt->execute();
    $language = $languageStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$language) {
        echo json_encode([
            'error' => 'Language not found',
            'events' => [],
            'language' => ''
        ]);
        exit;
    }
    
    // Get events for this language
    $events = getEventsByLanguage($languageId);

    // Sort events by day of week and time
    usort($events, function($a, $b) {
        if ($a['day_of_week'] == $b['day_of_week']) {
            return $a['time_hour'] - $b['time_hour'];
        }
        return $a['day_of_week'] - $b['day_of_week'];
    });
    
    // Return JSON response
    echo json_encode([
        'events' => $events,
        'language' => $language['name']
    ]);
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'events' => [],
        'language' => ''
    ]);
}
?> 