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
define('ASSET_VERSION', '1.7'); // Versão para cache-busting
define('DB_USER',    getenv('DB_USER')    ?: '');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('SITE_NAME',  getenv('SITE_NAME')  ?: 'Encontro de Idiomas');
define('ADMIN_EMAIL',getenv('ADMIN_EMAIL')?: '');
define('ADMIN_USER', getenv('ADMIN_USER') ?: 'admin');
define('ADMIN_PASS', getenv('ADMIN_PASS') ?: 'encontro2023');
define('SITE_URL',   'https://' . ($_SERVER['HTTP_HOST'] ?? 'encontrodeidiomas.com.br'));

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

// i18n Engine
require_once __DIR__ . '/lang/index.php';

function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function getDayName(int $dayNumber): string {
    return t('days.' . $dayNumber);
}

/**
 * Formata a hora de acordo com o idioma ativo
 * @param int $hour Hora em formato 24h (0-23)
 * @return string Hora formatada (ex: 19h ou 7 PM)
 */
function formatHour(int $hour): string {
    if (t('meta.lang_code') === 'en') {
        $period = ($hour >= 12) ? 'PM' : 'AM';
        $h12 = ($hour % 12);
        $h12 = ($h12 === 0) ? 12 : $h12;
        return "$h12 $period";
    }
    // Padrão Português (24h)
    return "{$hour}h";
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

function updateSetting(string $key, string $value): void {
    try {
        $conn = connectDB();
        $stmt = $conn->prepare(
            "INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v1)
             ON DUPLICATE KEY UPDATE setting_value = :v2"
        );
        $stmt->execute([':k' => $key, ':v1' => $value, ':v2' => $value]);
    } catch (Exception $e) {
        error_log("updateSetting error for key '$key': " . $e->getMessage());
    }
}

function getMeetings(): array {
    try {
        $conn = connectDB();
        $stmt = $conn->prepare("
            SELECT 
                m.*, 
                l.name AS language_name, l.name_en AS language_name_en, l.flag_code, l.flag_emoji,
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
        $stmt = $conn->query("SELECT * FROM useful_links WHERE active = 1 ORDER BY order_index DESC, id ASC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}
/**
 * Retorna a URL da foto do anfitrião com fallback inteligente entre ambientes
 * @param string|null $fileName Nome do arquivo no banco
 * @return string URL completa ou relativa para a imagem
 */
function getHostPhotoUrl(?string $fileName): string {
    $fallback = '/assets/images/HostSemFoto.png';
    if (empty($fileName) || $fileName === 'HostSemFoto.png') return $fallback;
    
    $is_admin = (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false);
    $relative_prefix = $is_admin ? '../' : '/';
    $v = defined('ASSET_VERSION') ? ASSET_VERSION : '1';
    
    // Caminho absoluto no disco para o check
    $filePath = __DIR__ . '/assets/images/' . $fileName;
    
    if (file_exists($filePath)) {
        return $relative_prefix . 'assets/images/' . $fileName . '?v=' . $v;
    } else {
        // Fallback dinâmico entre domínios (Dev <-> Prod)
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        $prodDomain    = 'viaei.com';
        $devDomain     = 'dev.encontrodeidiomas.com.br';
        $isProduction  = ($currentHost === $prodDomain || $currentHost === 'www.' . $prodDomain);
        
        if ($isProduction) {
            // Estamos na Produção -> Busca no Dev
            return 'https://' . $devDomain . '/assets/images/' . $fileName;
        } else {
            // Estamos no Dev (ou localhost) -> Busca na Produção
            return 'https://' . $prodDomain . '/assets/images/' . $fileName;
        }
    }
}

/**
 * Retorna mapa de slugs de dias da semana indexado por número do dia
 * Ex: ['1' => ['pt' => 'segunda', 'en' => 'monday'], ...]
 */
function getDaySlugMap(): array {
    try {
        $conn = connectDB();
        $rows = $conn->query("SELECT slug, lang, target_param_value FROM slugs WHERE type='day'")->fetchAll();
        $map = [];
        foreach ($rows as $r) {
            $map[$r['target_param_value']][$r['lang']] = $r['slug'];
        }
        return $map;
    } catch (Exception $e) { return []; }
}

/**
 * Retorna o slug de um dia da semana no idioma especificado
 */
function getDaySlug(int $dayNum, string $targetLang): ?string {
    try {
        $conn = connectDB();
        $stmt = $conn->prepare("SELECT slug FROM slugs WHERE type='day' AND lang=? AND target_param_value=? LIMIT 1");
        $stmt->execute([$targetLang, (string)$dayNum]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['slug'] : null;
    } catch (Exception $e) { return null; }
}

/**
 * Retorna o slug de uma cidade (universal, sem prefixo de lang)
 */
function getCitySlug(string $cityName): ?string {
    try {
        $conn = connectDB();
        $stmt = $conn->prepare("SELECT slug FROM slugs WHERE type='city' AND target_param_value=? LIMIT 1");
        $stmt->execute([$cityName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['slug'] : null;
    } catch (Exception $e) { return null; }
}
?>
