<?php
// ============================================================
// ENCONTRO DE IDIOMAS - Configuração Central
// ============================================================
// Este arquivo lê as credenciais do arquivo .env (nunca
// armazene senhas diretamente aqui).
// .env e config.php estão no .gitignore por segurança.
// ============================================================

// --- Leitor de .env ---
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // ignora comentários
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// --- Constantes de Configuração ---
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')    ?: '');
define('DB_USER',    getenv('DB_USER')    ?: '');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('SITE_NAME',  getenv('SITE_NAME')  ?: 'Encontro de Idiomas');
define('ADMIN_EMAIL',getenv('ADMIN_EMAIL')?: '');
define('SITE_URL',   'https://' . ($_SERVER['HTTP_HOST'] ?? 'encontrodeidiomas.com.br'));

// --- Conexão com o Banco de Dados ---
function connectDB(): PDO {
    static $conn = null;
    if ($conn !== null) return $conn; // Singleton: uma conexão por request

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
        // Em produção, não exibe detalhes do erro ao usuário
        error_log("Erro de conexão com BD: " . $e->getMessage());
        die("Serviço temporariamente indisponível. Tente novamente em instantes.");
    }
}

// --- Funções Auxiliares ---
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function getDayName(int $dayNumber): string {
    $days = [
        1 => 'Segunda',
        2 => 'Terça',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sábado',
        7 => 'Domingo',
    ];
    return $days[$dayNumber] ?? '';
}

// --- Consultas ao Banco de Dados ---

function getLanguages(): array {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT * FROM languages WHERE active = 1 ORDER BY name");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getEvents(): array {
    $conn = connectDB();
    $stmt = $conn->prepare("
        SELECT
            e.id, e.language_id, e.day_of_week, e.time_hour,
            e.title, e.description, e.meet_link, e.youtube_link,
            e.whatsapp_group_link, e.instagram_link, e.active,
            l.name AS language_name, l.flag_code, l.flag_emoji
        FROM events e
        JOIN languages l ON e.language_id = l.id
        WHERE e.active = 1
        ORDER BY e.day_of_week, e.time_hour
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getEventsByLanguage(int $languageId): array {
    $conn = connectDB();
    $stmt = $conn->prepare("
        SELECT
            e.id, e.language_id, e.day_of_week, e.time_hour,
            e.title, e.description, e.meet_link, e.youtube_link,
            e.whatsapp_group_link, e.instagram_link, e.active,
            l.name AS language_name, l.flag_code, l.flag_emoji
        FROM events e
        JOIN languages l ON e.language_id = l.id
        WHERE e.active = 1 AND e.language_id = :language_id
        ORDER BY e.day_of_week, e.time_hour
    ");
    $stmt->bindParam(':language_id', $languageId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getHosts(): array {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT * FROM hosts WHERE status = 'ativo' ORDER BY full_name");
    $stmt->execute();
    return $stmt->fetchAll();
}
?>
