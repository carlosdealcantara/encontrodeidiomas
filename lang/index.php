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

    // Interceptação Inteligente de Slugs (Ex: /english ou /ingles sem prefixo /en/)
    if (!empty($_GET['slug'])) {
        try {
            $conn = connectDB();
            $slugClean = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['slug']));
            $stmt = $conn->prepare("SELECT slug_pt, slug_en FROM languages WHERE slug_pt = ? OR slug_en = ? LIMIT 1");
            $stmt->execute([$slugClean, $slugClean]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if ($row['slug_en'] === $slugClean) {
                    $lang = 'en'; // Força inglês se o slug for em inglês!
                    $_GET['lang'] = 'en'; // Ajusta $_GET para consistência global
                } elseif ($row['slug_pt'] === $slugClean) {
                    $lang = 'pt'; // Força português se o slug for em português!
                    $_GET['lang'] = 'pt';
                }
            }
        } catch (Exception $e) {}
    }

    // 2. Detectar via Cookie (somente se não veio lang na URL nem slug)
    if (empty($_GET['lang']) && empty($_GET['slug']) && isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], SUPPORTED_LANGS)) {
        $lang = $_COOKIE['lang'];
    }
    // 3. Detectar via Browser (Accept-Language)
    elseif (empty($_GET['lang']) && empty($_GET['slug']) && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
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
                $fallback = t_fallback_pt($key, $params);
                if ($fallback !== $key) {
                    return $fallback;
                }
            }
            // Fallback inteligente para nomes de idiomas: consulta o banco de dados
            // para retornar o nome no idioma correto (name_en se EN, name se PT)
            if (strpos($key, 'languages.') === 0) {
                $langName = str_replace('languages.', '', $key);
                return _langNameFromDB($langName);
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
 * Fallback: busca o nome traduzido de um idioma no banco de dados.
 * Usa cache estático para evitar queries repetidas na mesma requisição.
 * @param string $langNamePt Nome em português (ex: 'indonésio')
 * @return string Nome no idioma do site (ex: 'Indonesian' se EN, 'Indonésio' se PT)
 */
function _langNameFromDB(string $langNamePt): string {
    static $cache = null;

    // Carrega o cache uma única vez por requisição
    if ($cache === null) {
        $cache = [];
        try {
            $conn = connectDB();
            $stmt = $conn->query("SELECT name, name_en FROM languages WHERE active = 1");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $key = mb_strtolower(trim($row['name']), 'UTF-8');
                $cache[$key] = $row;
            }
        } catch (Exception $e) {
            error_log("_langNameFromDB: " . $e->getMessage());
        }
    }

    $lookup = mb_strtolower(trim($langNamePt), 'UTF-8');

    if (isset($cache[$lookup])) {
        $row = $cache[$lookup];
        if (CURRENT_LANG === 'en' && !empty($row['name_en'])) {
            return $row['name_en'];
        }
        return $row['name']; // Retorna nome em PT do banco (formatado corretamente)
    }

    // Último fallback: capitaliza o nome da chave
    return mb_convert_case($langNamePt, MB_CASE_TITLE, "UTF-8");
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
    global $current_page;
    $targetLang = (CURRENT_LANG === 'pt') ? 'en' : 'pt';

    // CASO 1: Online + idioma selecionado → slug premium do idioma
    if (($current_page ?? '') === 'online.php' && !empty($_GET['idioma'])) {
        return langSpecificUrl($current_page, $targetLang);
    }

    // CASO 2: Online + dia selecionado → slug do dia no idioma destino
    if (($current_page ?? '') === 'online.php' && !empty($_GET['dia']) && empty($_GET['idioma'])) {
        $daySlug = getDaySlug((int)$_GET['dia'], $targetLang);
        if ($daySlug) {
            return ($targetLang === 'pt') ? '/' . $daySlug : '/en/' . $daySlug;
        }
    }

    // CASO 3: Presencial + cidade → /sao-paulo ou /en/sao-paulo
    if (($current_page ?? '') === 'presencial.php' && !empty($_GET['cidade'])) {
        $citySlug = getCitySlug($_GET['cidade']);
        if ($citySlug) {
            return ($targetLang === 'pt') ? '/' . $citySlug : '/en/' . $citySlug;
        }
    }

    // CASO 4: Presencial + estado → /sp ou /en/sp
    if (($current_page ?? '') === 'presencial.php' && !empty($_GET['estado'])) {
        $stateSlug = strtolower(trim($_GET['estado']));
        return ($targetLang === 'pt') ? '/' . $stateSlug : '/en/' . $stateSlug;
    }

    // PADRÃO: URL da página no idioma destino
    $params = $_GET;
    unset($params['lang'], $params['slug'], $params['tabslug'], $params['tab']);
    $query = !empty($params) ? '?' . http_build_query($params) : '';
    return langSpecificUrl($current_page ?? 'index.php', $targetLang) . $query;
}

/**
 * Retorna a URL de uma página para um idioma específico
 */
function langSpecificUrl($page, $targetLang) {
    $page = ltrim($page, '/');
    global $current_page;

    // Roteamento Premium: seja-host/be-a-host para manter as URLs enxutas e transparentes ao mudar o idioma
    if ($page === 'equipe.php' && !empty($_GET['slug']) && ($_GET['slug'] === 'seja-host' || $_GET['slug'] === 'be-a-host')) {
        return ($targetLang === 'pt') ? '/seja-host' : '/en/be-a-host';
    }

    // EQUIPE + tab ativo → sub-path limpo por categoria
    if ($page === 'equipe.php' && !empty($_GET['tab'])) {
        $tabMapPt = ['online' => 'online', 'presencial' => 'presencial', 'bastidores' => 'bastidores', 'iniciativas' => 'iniciativas'];
        $tabMapEn = ['online' => 'online', 'presencial' => 'in-person', 'bastidores' => 'backstage', 'iniciativas' => 'initiatives'];
        $tabMap   = ($targetLang === 'en') ? $tabMapEn : $tabMapPt;
        $tabSlug  = $tabMap[$_GET['tab']] ?? $_GET['tab'];
        $prefix   = ($targetLang === 'pt') ? '/equipe' : '/en/team';
        $url      = $prefix . '/' . $tabSlug;
        // Preservar apenas sub-filtros (projeto, etc.), nunca artefatos de roteamento
        $params = $_GET;
        unset($params['lang'], $params['slug'], $params['tabslug'], $params['tab']);
        if (!empty($params)) $url .= '?' . http_build_query($params);
        return $url;
    }

    // ONLINE + idioma → slug premium do idioma
    if ($page === 'online.php' && !empty($_GET['idioma'])) {
        try {
            $conn = connectDB();
            $stmt = $conn->prepare("SELECT slug_pt, slug_en FROM languages WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$_GET['idioma']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $targetSlug = ($targetLang === 'en') ? ($row['slug_en'] ?? '') : ($row['slug_pt'] ?? '');
                if (!empty($targetSlug)) {
                    $prefix = ($targetLang === 'en') ? '/en' : '';
                    return $prefix . '/' . $targetSlug;
                }
            }
        } catch (Exception $e) {}
    }

    // ONLINE + dia → slug do dia
    if ($page === 'online.php' && !empty($_GET['dia']) && empty($_GET['idioma'])) {
        $daySlug = getDaySlug((int)$_GET['dia'], $targetLang);
        if ($daySlug) {
            return ($targetLang === 'pt') ? '/' . $daySlug : '/en/' . $daySlug;
        }
    }

    // PRESENCIAL + cidade → slug da cidade
    if ($page === 'presencial.php' && !empty($_GET['cidade'])) {
        $citySlug = getCitySlug($_GET['cidade']);
        if ($citySlug) {
            return ($targetLang === 'pt') ? '/' . $citySlug : '/en/' . $citySlug;
        }
    }

    // PRESENCIAL + estado → sigla do estado
    if ($page === 'presencial.php' && !empty($_GET['estado'])) {
        $stateSlug = strtolower(trim($_GET['estado']));
        return ($targetLang === 'pt') ? '/' . $stateSlug : '/en/' . $stateSlug;
    }

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
    
    // Preservar parâmetros se estivermos na página atual
    if ($page === ($current_page ?? '')) {
        $params = $_GET;
        unset($params['lang'], $params['slug'], $params['tabslug'], $params['tab']);
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
    }
    
    return $url === '' ? '/' : $url;
}

/**
 * Retorna a URL curta (slug) para um idioma específico no idioma atual do site
 * @param array $lang Array do idioma (da tabela languages)
 * @return string URL como '/ingles' ou '/english'
 */
function langSlugUrl(array $lang): string {
    $slugField = (CURRENT_LANG === 'en') ? 'slug_en' : 'slug_pt';
    $slug = $lang[$slugField] ?? null;
    
    // Fallback: se não tiver slug cadastrado, usa a URL antiga
    if (empty($slug)) {
        return langUrl('online.php') . '?view=language&idioma=' . $lang['id'];
    }
    
    $prefix = (CURRENT_LANG === 'en') ? '/en' : '';
    return $prefix . '/' . $slug;
}

