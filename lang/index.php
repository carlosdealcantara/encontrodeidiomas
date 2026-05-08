<?php
/**
 * i18n Engine - Encontro de Idiomas
 */

// Configurações
define('DEFAULT_LANG', 'pt');
define('SUPPORTED_LANGS', ['pt', 'en']);

function initLang() {
    $lang = DEFAULT_LANG;

    // 1. Detectar via URL (passado pelo .htaccess via ?lang=)
    if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS)) {
        $lang = $_GET['lang'];
    } 
    // 2. Detectar via Cookie
    elseif (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], SUPPORTED_LANGS)) {
        $lang = $_COOKIE['lang'];
    }
    // 3. Detectar via Browser (Accept-Language)
    elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
        if (in_array($browserLang, SUPPORTED_LANGS)) {
            $lang = $browserLang;
        }
    }

    // Salvar preferência em cookie por 30 dias
    if (!isset($_COOKIE['lang']) || $_COOKIE['lang'] !== $lang) {
        setcookie('lang', $lang, time() + (86400 * 30), "/");
    }

    return $lang;
}

$current_lang = initLang();
define('CURRENT_LANG', $current_lang);
define('LANG_PREFIX', ($current_lang === 'pt' ? '' : '/' . $current_lang));
define('IS_ENGLISH', ($current_lang === 'en'));

// Carregar dicionário
$dict_path = __DIR__ . '/' . CURRENT_LANG . '.json';
$translations = [];
if (file_exists($dict_path)) {
    $translations = json_decode(file_get_contents($dict_path), true);
}

// Função de tradução global
function t($key, $params = []) {
    global $translations;
    
    $keys = explode('.', $key);
    $value = $translations;

    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            // Fallback para PT se estivermos em EN e a chave faltar
            if (CURRENT_LANG === 'en') {
                return t_fallback_pt($key, $params);
            }
            return $key; // Retorna a própria chave se não encontrar
        }
    }

    // Interpolação de parâmetros
    if (!empty($params)) {
        foreach ($params as $pKey => $pVal) {
            $value = str_replace('{' . $pKey . '}', $pVal, $value);
        }
    }

    return $value;
}

function t_fallback_pt($key, $params = []) {
    static $pt_translations = null;
    if ($pt_translations === null) {
        $pt_path = __DIR__ . '/pt.json';
        $pt_translations = file_exists($pt_path) ? json_decode(file_get_contents($pt_path), true) : [];
    }

    $keys = explode('.', $key);
    $value = $pt_translations;

    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $key;
        }
    }

    if (!empty($params)) {
        foreach ($params as $pKey => $pVal) {
            $value = str_replace('{' . $pKey . '}', $pVal, $value);
        }
    }

    return $value;
}

/**
 * Retorna a URL correta para uma página no idioma atual
 */
function langUrl($page = '') {
    $page = ltrim($page, '/');
    
    // Mapeamento de slugs traduzidos
    $slugs = [
        'en' => [
            'presencial.php' => 'in-person',
            'equipe.php'     => 'team',
            'contato.php'    => 'contact',
            'index.php'      => '',
            'online.php'     => 'online',
            'links.php'      => 'links'
        ],
        'pt' => [
            'presencial.php' => 'presencial',
            'equipe.php'     => 'equipe',
            'contato.php'    => 'contato',
            'index.php'      => '',
            'online.php'     => 'online',
            'links.php'      => 'links'
        ]
    ];

    $slug = $slugs[CURRENT_LANG][$page] ?? str_replace('.php', '', $page);
    
    $prefix = (CURRENT_LANG === 'pt') ? '' : '/en';
    $url = $prefix . ($slug ? '/' . $slug : '/');
    
    return $url === '' ? '/' : $url;
}

/**
 * Retorna a URL da versão alternativa da página atual
 */
function altLangUrl() {
    $alt_lang = (CURRENT_LANG === 'pt') ? 'en' : 'pt';
    return langSpecificUrl(basename($_SERVER['SCRIPT_NAME']), $alt_lang);
}

/**
 * Retorna a URL de uma página para um idioma específico
 */
function langSpecificUrl($page, $targetLang) {
    $page = ltrim($page, '/');
    $slugs = [
        'en' => [
            'presencial.php' => 'in-person',
            'equipe.php'     => 'team',
            'contato.php'    => 'contact',
            'index.php'      => '',
            'online.php'     => 'online',
            'links.php'      => 'links'
        ],
        'pt' => [
            'presencial.php' => 'presencial',
            'equipe.php'     => 'equipe',
            'contato.php'    => 'contato',
            'index.php'      => '',
            'online.php'     => 'online',
            'links.php'      => 'links'
        ]
    ];

    $slug = $slugs[$targetLang][$page] ?? str_replace('.php', '', $page);
    $prefix = ($targetLang === 'pt') ? '' : '/en';
    
    $url = $prefix . ($slug ? '/' . $slug : '/');
    return $url === '' ? '/' : $url;
}

/**
 * Retorna a URL da página atual no idioma oposto
 */
function altLangUrl() {
    global $current_page;
    $targetLang = (CURRENT_LANG === 'pt') ? 'en' : 'pt';
    return langSpecificUrl($current_page ?? 'index.php', $targetLang);
}
