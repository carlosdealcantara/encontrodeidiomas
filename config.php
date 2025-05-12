<?php
// Site Configuration
define('SITE_NAME', 'Encontro de Idiomas');
define('ADMIN_EMAIL', 'carlosdealcantarajr@gmail.com');
define('DB_HOST', 'srv1437.hstgr.io');
define('DB_NAME', 'u879045076_central');
define('DB_USER', 'u879045076_carlos'); 
define('DB_PASS', '@Car8lafe');
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST']);

// Database connection
function connectDB() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
            DB_USER, 
            DB_PASS, 
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $conn;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Helper functions
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function getLanguages() {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT * FROM languages WHERE active = 1 ORDER BY name");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEvents() {
    $conn = connectDB();
    $stmt = $conn->prepare("
        SELECT e.*, l.name as language_name, l.flag_code, l.flag_emoji 
        FROM events e 
        JOIN languages l ON e.language_id = l.id 
        WHERE e.active = 1 
        ORDER BY e.day_of_week, e.time_hour
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getHosts() {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT * FROM hosts WHERE active = 1 ORDER BY full_name");
    $stmt->execute();
    $hosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug hosts
    /*
    foreach ($hosts as $index => $host) {
        if (!empty($host['languages'])) {
            error_log("Host {$host['full_name']} has languages: {$host['languages']}");
        } else {
            error_log("Host {$host['full_name']} has NO languages set");
        }
    }
    */
    
    return $hosts;
}

function getEventsByLanguage($languageId) {
    $conn = connectDB();
    $stmt = $conn->prepare("
        SELECT e.*, l.name as language_name, l.flag_code, l.flag_emoji 
        FROM events e 
        JOIN languages l ON e.language_id = l.id 
        WHERE e.active = 1 AND e.language_id = :language_id
        ORDER BY e.day_of_week, e.time_hour
    ");
    $stmt->bindParam(':language_id', $languageId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDayName($dayNumber) {
    $days = [
        1 => 'Segunda',
        2 => 'Terça',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sábado',
        7 => 'Domingo'
    ];
    return $days[$dayNumber] ?? '';
}
?> 