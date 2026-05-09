<?php
// ============================================================
// ENCONTRO DE IDIOMAS - Configuração Central
// ============================================================
date_default_timezone_set('America/Sao_Paulo');
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')    ?: '');
define('ASSET_VERSION', '1.6'); // Versão para cache-busting
define('DB_USER',    getenv('DB_USER')    ?: '');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('SITE_NAME',  getenv('SITE_NAME')  ?: 'Encontro de Idiomas');
define('ADMIN_EMAIL',getenv('ADMIN_EMAIL')?: '');
define('ADMIN_USER', getenv('ADMIN_USER') ?: 'admin');
define('ADMIN_PASS', getenv('ADMIN_PASS') ?: 'encontro2023');
define('SITE_URL',   'https://' . ($_SERVER['HTTP_HOST'] ?? 'encontrodeidiomas.com.br'));

// i18n Engine
require_once __DIR__ . '/lang/index.php';

function connectDB(): PDO {
    static $conn = null;
    if ($conn !== null) return $conn;
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        return $conn;
    } catch (PDOException $e) {
        error_log("Erro de conexão com BD: " . $e->getMessage());
        die("Serviço temporariamente indisponível.");
    }
}

function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function getDayName(int $dayNumber): string {
    return t('days.' . $dayNumber);
}


function getLanguages(): array {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT * FROM languages WHERE active = 1 ORDER BY name");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getHosts(): array {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT * FROM hosts WHERE status = 'ativo' ORDER BY full_name");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getSettings(): array {
    try {
        $conn = connectDB();
        $stmt = $conn->query("SELECT setting_key, setting_value FROM settings");
        $results = $stmt->fetchAll();
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (PDOException $e) {
        return [];
    }
}

function getSetting(string $key, $default = ''): string {
    static $settings = null;
    if ($settings === null) $settings = getSettings();
    return $settings[$key] ?? $default;
}

function getMeetings(): array {
    try {
        $conn = connectDB();
        $stmt = $conn->prepare("
            SELECT 
                m.*, 
                l.name AS language_name, l.flag_code, l.flag_emoji,
                l.whatsapp_link AS final_whatsapp,
                l.instagram_link AS final_instagram,
                COALESCE(h.full_name, h_auto.full_name) AS host_name,
                COALESCE(h.profile_picture, h_auto.profile_picture) AS host_photo
            FROM meetings m
            JOIN languages l ON m.language_id = l.id
            LEFT JOIN hosts h ON (m.host_id = h.id AND h.status = 'ativo')
            LEFT JOIN hosts h_auto ON (
                h.id IS NULL 
                AND h_auto.status = 'ativo' 
                AND h_auto.category LIKE '%Online%'
                AND h_auto.languages LIKE CONCAT('%', l.name, '%')
            )
            WHERE m.active = 1
            GROUP BY m.id
            ORDER BY m.day_of_week, m.time_hour
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getUsefulLinks(): array {
    try {
        $conn = connectDB();
        $stmt = $conn->query("SELECT * FROM useful_links WHERE active = 1 ORDER BY order_index DESC, title ASC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}
?>
