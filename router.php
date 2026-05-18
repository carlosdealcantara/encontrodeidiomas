<?php
/**
 * router.php — Front Controller de Slugs Premium
 * Encontro de Idiomas
 *
 * Recebe do .htaccess: ?slug=X&lang=pt (ou lang=en)
 * Consulta a tabela `slugs` e inclui a página correta mantendo a URL limpa.
 */
require_once __DIR__ . '/config.php';

$slug = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['slug'] ?? ''));
$lang = (isset($_GET['lang']) && in_array($_GET['lang'], ['pt','en'])) ? $_GET['lang'] : 'pt';

// Interceptação premium para sejahost/beahost para evitar redirecionamento e manter a URL curta na barra do navegador
if ($slug === 'sejahost' || $slug === 'beahost') {
    $_GET['slug'] = $slug;
    $_GET['scroll_to'] = 'seja-host';
    include __DIR__ . '/equipe.php';
    exit;
}

if (empty($slug)) {
    http_response_code(404);
    include __DIR__ . '/includes/404.php';
    exit;
}

$route = null;
try {
    $conn = connectDB();
    // Busca: slug exato + lang específico OU slug universal ('*')
    // Prioridade: lang específico > universal
    $stmt = $conn->prepare("
        SELECT *
        FROM slugs
        WHERE slug = ?
          AND (lang = ? OR lang = '*')
        ORDER BY FIELD(lang, ?, '*')
        LIMIT 1
    ");
    $stmt->execute([$slug, $lang, $lang]);
    $route = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("router.php: DB error for slug=$slug — " . $e->getMessage());
}

// Slug não encontrado → 404
if (!$route) {
    http_response_code(404);
    include __DIR__ . '/includes/404.php';
    exit;
}

// ─── TIPO ANCHOR: redirecionar diretamente ────────────────────────────────────
if ($route['type'] === 'anchor' && !empty($route['redirect_to'])) {
    header('Location: ' . $route['redirect_to'], true, 302);
    exit;
}

// ─── OUTROS TIPOS: injetar parâmetros e incluir página destino ────────────────

// Preservar slug para que lang/index.php (initLang) detecte idioma corretamente
$_GET['slug'] = $slug;

// Injetar o parâmetro de destino (ex: 'idioma' => '3', 'dia' => '6', 'cidade' => 'São Paulo')
if (!empty($route['target_param_key']) && !empty($route['target_param_value'])) {
    $_GET[$route['target_param_key']] = $route['target_param_value'];
}

// Incluir a página destino (sem redirect — URL premium fica na barra do navegador!)
$targetFile = __DIR__ . '/' . $route['target_page'];

if (!file_exists($targetFile)) {
    error_log("router.php: target_page não encontrado: {$route['target_page']}");
    http_response_code(500);
    echo 'Erro interno de roteamento.';
    exit;
}

include $targetFile;
exit;
