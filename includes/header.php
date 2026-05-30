<?php
/**
 * Header Global — Encontro de Idiomas
 *
 * Variáveis esperadas da página que inclui este arquivo:
 *   $title        string  — Título da página (sem o sufixo "- Encontro de Idiomas")
 *   $current_page string  — Nome do arquivo atual (ex: 'online.php') para link ativo
 *   $page_styles  string  — CSS específico da página (opcional)
 *   $extra_head   string  — Tags extras para o <head> (opcional, ex: Swiper CSS)
 *   $swiper_enabled bool  — Se true, carrega Swiper CSS (opcional)
 *   $og_description string — Descrição Open Graph personalizada (opcional)
 *   $canonical    string  — URL canônica da página (opcional)
 */

$current_page   = $current_page   ?? basename($_SERVER['PHP_SELF']);
$title          = $title          ?? getSetting('site_title', 'Encontro de Idiomas');
$og_title       = $og_title       ?? ($title . ' — ' . t('meta.og_title_suffix'));
$og_description = $og_description ?? t('home.meta_description'); // Puxa da tradução da Home como fallback global
$canonical      = $canonical      ?? 'https://encontrodeidiomas.com.br' . langUrl($current_page);

// SEO Internacional & Slugs — Canonical e Hreflang
$hreflang_pt = SITE_URL . langSpecificUrl($current_page, 'pt');
$hreflang_en = SITE_URL . langSpecificUrl($current_page, 'en');

// ONLINE + IDIOMA
if ($current_page === 'online.php' && !empty($_GET['idioma'])) {
    $languages = $languages ?? getLanguages();
    foreach ($languages as $lang) {
        if ($lang['id'] == $_GET['idioma']) {
            if (!empty($lang['slug_pt'])) $hreflang_pt = SITE_URL . '/' . $lang['slug_pt'];
            if (!empty($lang['slug_en'])) $hreflang_en = SITE_URL . '/en/' . $lang['slug_en'];
            break;
        }
    }
}

// ONLINE + DIA DA SEMANA
if ($current_page === 'online.php' && !empty($_GET['dia']) && empty($_GET['idioma'])) {
    $dSlugPt = getDaySlug((int)$_GET['dia'], 'pt');
    $dSlugEn = getDaySlug((int)$_GET['dia'], 'en');
    if ($dSlugPt) $hreflang_pt = SITE_URL . '/' . $dSlugPt;
    if ($dSlugEn) $hreflang_en = SITE_URL . '/en/' . $dSlugEn;
}

// PRESENCIAL + CIDADE
if ($current_page === 'presencial.php' && !empty($_GET['cidade'])) {
    $cSlug = getCitySlug($_GET['cidade']);
    if ($cSlug) {
        $hreflang_pt = SITE_URL . '/' . $cSlug;
        $hreflang_en = SITE_URL . '/en/' . $cSlug;
    }
}

// PRESENCIAL + ESTADO
if ($current_page === 'presencial.php' && !empty($_GET['estado'])) {
    $sSlug = strtolower(trim($_GET['estado']));
    $hreflang_pt = SITE_URL . '/' . $sSlug;
    $hreflang_en = SITE_URL . '/en/' . $sSlug;
}

// Canonical: usa hreflang do idioma atual
$canonical = $canonical ?? ($current_lang === 'pt' ? $hreflang_pt : $hreflang_en);
?>
<!DOCTYPE html>
<html lang="<?= t('meta.lang_code') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= sanitize($og_description) ?>">
    <?php if (!empty($meta_keywords)): ?>
    <meta name="keywords" content="<?= sanitize($meta_keywords) ?>">
    <?php endif; ?>
    <meta name="author" content="Encontro de Idiomas">
    <?php if ($_SERVER['HTTP_HOST'] !== 'encontrodeidiomas.com.br'): ?>
    <meta name="robots" content="noindex, nofollow">
    <?php else: ?>
    <meta name="robots" content="index, follow">
    <?php endif; ?>
    <meta name="theme-color" content="#1a1a1a">

    <!-- SEO Internacional -->
    <link rel="alternate" hreflang="pt" href="<?= sanitize($hreflang_pt) ?>">
    <link rel="alternate" hreflang="en" href="<?= sanitize($hreflang_en) ?>">
    <link rel="alternate" hreflang="x-default" href="<?= sanitize($hreflang_pt) ?>">

    <!-- Structured Data (JSON-LD) -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "EducationalOrganization",
          "@id": "<?= SITE_URL ?>/#organization",
          "name": "<?= SITE_NAME ?>",
          "url": "<?= SITE_URL ?>",
          "logo": {
            "@type": "ImageObject",
            "url": "<?= SITE_URL ?>/assets/images/logo.png"
          },
          "description": "<?= t('meta.seo_org_desc') ?>",
          "sameAs": [
            "https://www.instagram.com/encontrodeidiomas",
            "https://www.tiktok.com/@encontrodeidiomas",
            "https://discord.com/invite/STHkrEhMpP"
          ]
        },
        {
          "@type": "WebSite",
          "@id": "<?= SITE_URL ?>/#website",
          "url": "<?= SITE_URL ?>",
          "name": "<?= SITE_NAME ?>",
          "description": "<?= t('meta.seo_web_desc') ?>",
          "publisher": { "@id": "<?= SITE_URL ?>/#organization" }
        },
        {
          "@type": "BreadcrumbList",
          "@id": "<?= SITE_URL ?>/#breadcrumb",
          "itemListElement": [
            {
              "@type": "ListItem",
              "position": 1,
              "item": {
                "@id": "<?= SITE_URL ?>",
                "name": "<?= SITE_NAME ?>"
              }
            }
            <?php if (isset($title) && $current_page !== 'index.php'): ?>
            ,{
              "@type": "ListItem",
              "position": 2,
              "item": {
                "@id": "<?= SITE_URL . $_SERVER['REQUEST_URI'] ?>",
                "name": "<?= sanitize($title) ?>"
              }
            }
            <?php endif; ?>
          ]
        },
        {
          "@type": "Person",
          "name": "Carlos de Alcântara",
          "jobTitle": "Fundador e Professor",
          "url": "<?= SITE_URL . langUrl('equipe.php') ?>",
          "worksFor": { "@id": "<?= SITE_URL ?>/#organization" }
        }
      ]
    }
    </script>

    <!-- Open Graph / Facebook / WhatsApp -->
    <meta property="og:type"        content="website">
    <meta property="og:title"       content="<?= sanitize($og_title) ?>">
    <meta property="og:description" content="<?= sanitize($og_description) ?>">
    <meta property="og:image"       content="<?= SITE_URL ?>/assets/images/og_preview_elegant.jpg?v=8.0">
    <meta property="og:url"         content="<?= sanitize($canonical) ?>">
    <meta property="og:site_name"   content="<?= SITE_NAME ?>">
    <meta property="og:locale"      content="<?= t('meta.og_locale') ?>">

    <!-- Twitter -->
    <meta property="twitter:card"        content="summary">
    <meta property="twitter:url"         content="<?= sanitize($canonical) ?>">
    <meta property="twitter:title"       content="<?= sanitize($og_title) ?>">
    <meta property="twitter:description" content="<?= sanitize($og_description) ?>">
    <meta property="twitter:image"       content="<?= SITE_URL ?>/assets/images/og_preview_elegant.jpg?v=8.0">

    <link rel="canonical" href="<?= sanitize($canonical) ?>">
    <title><?= sanitize($title) ?> — <?= SITE_NAME ?></title>

    <!-- Favicon -->
    <link rel="icon"             type="image/png" href="/assets/images/favicon.png">
    <link rel="apple-touch-icon"                  href="/assets/images/favicon.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <?php if (!empty($swiper_enabled)): ?>
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <?php endif; ?>

    <!-- Google Analytics (GA4) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-C1BD3DH8TJ"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-C1BD3DH8TJ');
    </script>

    <style>
        /* ============================================================
           DESIGN SYSTEM — Tokens globais
           ============================================================ */
        :root {
            --primary-color:  #1a1a1a;
            --accent-red:     #e31d1c;
            --accent-blue:    #002654;
            --accent-yellow:  #ffd700;
            --text-color:     #333;
            --bg-light:       #f8f9fa;
            --white:          #ffffff;
            --card-bg:        #ffffff;
            --border-radius:  16px;
            --shadow:         0 10px 30px rgba(0,0,0,.1);
            --transition:     all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            --highlight-bg:   rgba(255, 215, 0, 0.1);
            --highlight-border: #ffd700;
            --now-badge-bg:   #e31d1c;
            --disabled-bg:    #e0e0e0;
            --disabled-color: #888;
        }

        /* ============================================================
           RESET & BASE
           ============================================================ */
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            max-width: 100%;
            overflow-x: hidden;
            position: relative;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text-color);
            background-color: #f7f7f7;
            line-height: 1.6;
            width: 100%;
            /* O padding-top do body é gerenciado dinamicamente via JS (fim do arquivo) */
            padding-top: 85px; /* Valor base fallback */
        }
        @media (min-width: 769px) {
            body { padding-top: 80px; }
        }

        /* ============================================================
           HEADER — fixo, com menu hamburguer para mobile
           ============================================================ */
        .header {
            background: var(--primary-color);
            color: var(--white);
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            z-index: 1000;
            top: 0;
            left: 0;
            /* Estabilidade de renderização */
            transform: translateZ(0);
            will-change: transform;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            /* Removemos o wrap para evitar que o menu caia para baixo abruptamente em desktops */
            flex-wrap: nowrap;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--white);
        }

        .site-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--white);
            margin: 0;
        }

        .site-description {
            font-size: 0.9rem;
            opacity: 0.9;
            color: var(--white);
        }

        /* Botão hamburguer — visível apenas em mobile */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
        }

        .nav-links {
            display: flex;
            gap: 12px;
        }

        .nav-links a {
            position: relative;
            color: var(--white);
            text-decoration: none;
            padding: 8px 12px;
            transition: all 0.3s ease;
            white-space: nowrap;
            opacity: 0.8;
        }

        .nav-links a:hover,
        .nav-links a.active {
            opacity: 1;
        }

        .nav-links a.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 16px;
            right: 16px;
            height: 3px;
            background-color: var(--accent-red);
            border-radius: 3px;
        }

        /* ============================================================
           CONTAINER global
           ============================================================ */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            width: 100%;
        }

        /* ============================================================
           RESPONSIVE — Header mobile
           ============================================================ */
        @media (max-width: 768px) {
            .header-content {
                justify-content: space-between;
            }

            .menu-toggle {
                display: block;
            }

            .header-content {
                flex-wrap: wrap;
            }
            
            .nav-links {
                display: none;
                width: 100%;
                flex-direction: column;
                gap: 10px;
                margin-top: 0;
                padding: 20px 0;
                position: absolute;
                top: 100%;
                left: 0;
                background: var(--primary-color);
                box-shadow: 0 10px 20px rgba(0,0,0,0.2);
                z-index: 1000;
                max-height: calc(100vh - 60px);
                overflow-y: auto;
            }

            .nav-links.show {
                display: flex;
            }

            .nav-links a {
                display: block;
                text-align: center;
            }

            .logo {
                width: 50px;
                height: 50px;
            }

            .site-title {
                font-size: 1.2rem;
            }

            .site-description {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .header-content {
                justify-content: center;
                padding: 10px;
            }

            .logo-container {
                margin-bottom: 10px;
                justify-content: center;
                width: 100%;
            }

            .menu-toggle {
                position: absolute;
                right: 15px;
                top: 15px;
            }
        }

        .global-notice {
            background: var(--accent-yellow);
            color: var(--primary-color);
            text-align: center;
            padding: 10px 20px;
            font-weight: 600;
            font-size: 0.9rem;
            position: relative;
            z-index: 1001;
            margin-top: 0;
        }
        /* O padding do notice também é gerido pelo script dinâmico */
        body.has-notice { padding-top: 0; }

        /* Language Switcher */
        .lang-switch {
            display: flex;
            gap: 6px;
            margin-left: 15px;
        }
        .lang-btn {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--white);
            text-decoration: none;
            font-size: 0.78rem;
            opacity: 0.6;
            transition: var(--transition);
            padding: 4px 8px;
            border-radius: 12px;
            border: 1px solid transparent;
        }
        .lang-btn:hover {
            opacity: 1;
            background: rgba(255,255,255,0.1);
        }
        .lang-btn.active {
            opacity: 1;
            border-color: rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.1);
        }
        .lang-btn img {
            width: 20px;
            height: 15px;
            object-fit: cover;
            border-radius: 2px;
        }

        #smart-suggestion-banner {
            background: var(--accent-blue);
            color: white;
            text-align: center;
            padding: 12px 20px;
            font-size: 0.95rem;
            position: relative;
            z-index: 1100;
            display: none;
            flex-direction: column;
            gap: 8px;
            align-items: center;
            justify-content: center;
        }
        .smart-banner-row {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        #smart-suggestion-banner a, #smart-suggestion-banner button.action-link {
            color: var(--accent-yellow);
            font-weight: 700;
            text-decoration: underline;
            background: none;
            border: none;
            font-size: 0.95rem;
            cursor: pointer;
            padding: 0;
            font-family: inherit;
        }
        #smart-suggestion-banner .close-btn {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0.7;
        }
        #smart-suggestion-banner .close-btn:hover { opacity: 1; }

        .global-prefs-group {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-left: 15px;
        }
        .tz-dropdown-container {
            position: relative;
        }
        .tz-btn {
            display: flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.1);
            color: var(--white);
            border: 1px solid rgba(255,255,255,0.3);
            font-size: 0.78rem;
            padding: 4px 10px;
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
        }
        .tz-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        .tz-dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: var(--white);
            color: var(--text-color);
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 280px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
        }
        .tz-dropdown-menu.show {
            display: block;
        }
        .tz-group-title {
            font-size: 0.7rem;
            font-weight: 700;
            color: #888;
            padding: 10px 15px 5px;
            text-transform: uppercase;
        }
        .tz-option {
            display: block;
            width: 100%;
            text-align: left;
            background: none;
            border: none;
            padding: 8px 15px;
            font-size: 0.85rem;
            color: var(--text-color);
            cursor: pointer;
            transition: background 0.2s;
        }
        .tz-option:hover {
            background: var(--bg-light);
        }
        .tz-option.active {
            font-weight: 700;
            color: var(--accent-red);
            background: rgba(227, 29, 28, 0.05);
        }
        .tz-detect-btn {
            display: block;
            width: 100%;
            text-align: center;
            background: var(--bg-light);
            border: none;
            border-bottom: 1px solid #eee;
            padding: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--accent-blue);
            cursor: pointer;
        }
        .tz-detect-btn:hover {
            background: #e9ecef;
        }

        @keyframes blink {
            50% { opacity: 0; }
        }
        .blink-colon {
            animation: blink 1s step-start infinite;
        }

        /* Blindagem Global para Âncora: Qualquer elemento com ID respeita o menu fixo */
        [id] {
            scroll-margin-top: var(--header-offset, 100px);
        }

        @media (max-width: 768px) {
            .global-prefs-group {
                flex-direction: column;
                margin: 15px 0;
                width: 100%;
                gap: 15px;
                order: 2;
            }
            .lang-switch {
                margin: 0;
                justify-content: center;
                width: 100%;
            }
            .tz-dropdown-menu {
                right: 50%;
                transform: translateX(50%);
                width: 280px;
                max-width: 90vw;
                max-height: 45vh;
            }
        }

        <?php if (!empty($page_styles)) echo $page_styles; ?>
    </style>

    <?php if (!empty($extra_head)) echo $extra_head; ?>
</head>
<body>
    <header class="header">
        <?php if (getSetting('global_notice_active') === '1'): ?>
            <div class="global-notice">
                <i class="fas fa-exclamation-circle"></i> <?= getSetting('global_notice_text') ?>
            </div>
            <script>document.body.classList.add('has-notice');</script>
        <?php endif; ?>

        <?php 
        // Banner Inteligente Unificado (Idioma + Fuso Horário)
        $alt_lang = (CURRENT_LANG === 'pt') ? 'en' : 'pt';
        $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $showLangSuggest = !isset($_COOKIE['lang_suggest_closed']) && (strpos(strtolower($accept), $alt_lang) !== false);
        ?>
        <div id="smart-suggestion-banner" style="<?= $showLangSuggest ? 'display:flex;' : 'display:none;' ?>">
            <?php if ($showLangSuggest): ?>
            <div class="smart-banner-row lang-banner-row">
                🌎 <?= (CURRENT_LANG === 'pt') ? 'This site is also available in English.' : 'Este site também está disponível em Português.' ?> 
                <a href="<?= altLangUrl() ?>"><?= (CURRENT_LANG === 'pt') ? 'Switch to English' : 'Mudar para Português' ?></a>
            </div>
            <?php endif; ?>
            
            <!-- Linha de timezone injetada via JS se necessário -->
            <div class="smart-banner-row tz-banner-row" style="display:none;"></div>
            
            <button onclick="closeSmartSuggest()" class="close-btn" aria-label="Fechar aviso">&times;</button>
        </div>
        <script>
            function closeSmartSuggest() {
                document.getElementById('smart-suggestion-banner').style.display = 'none';
                document.cookie = "lang_suggest_closed=1; path=/; max-age=" + (86400 * 30);
                document.cookie = "tz_suggest_closed=1; path=/; max-age=" + (86400 * 30);
                if (typeof syncHeaderHeight === 'function') syncHeaderHeight();
            }
        </script>
        <div class="header-content">
            <div class="logo-container">
                <img src="/assets/images/logo.png" alt="Logo Encontro de Idiomas" class="logo" fetchpriority="high">
                <div>
                    <div class="site-title"><?= SITE_NAME ?></div>
                    <div class="site-description"><?= t('meta.tagline') ?></div>
                </div>
            </div>

            <button class="menu-toggle" aria-label="Abrir menu" id="menu-toggle-btn">
                <i class="fas fa-bars"></i>
            </button>

            <nav class="nav-links" id="main-nav" aria-label="Navegação principal">
                <a href="<?= langUrl('index.php') ?>"       <?= $current_page === 'index.php'       ? 'class="active" aria-current="page"' : '' ?>><?= t('nav.home') ?></a>
                <a href="<?= langUrl('online.php') ?>"      <?= $current_page === 'online.php'      ? 'class="active" aria-current="page"' : '' ?>><?= t('nav.online') ?></a>
                <a href="<?= langUrl('presencial.php') ?>"  <?= $current_page === 'presencial.php'  ? 'class="active" aria-current="page"' : '' ?>><?= t('nav.presencial') ?></a>
                <a href="<?= langUrl('equipe.php') ?>"      <?= $current_page === 'equipe.php'      ? 'class="active" aria-current="page"' : '' ?>><?= t('nav.team') ?></a>
                <a href="<?= langUrl('links.php') ?>"       <?= $current_page === 'links.php'       ? 'class="active" aria-current="page"' : '' ?>><?= t('nav.links') ?></a>
                <a href="<?= langUrl('contato.php') ?>"     <?= $current_page === 'contato.php'     ? 'class="active" aria-current="page"' : '' ?>><?= t('nav.contact') ?></a>
                
                <div class="global-prefs-group">
                    <div class="lang-switch">
                        <a href="<?= langSpecificUrl($current_page, 'pt') ?>" class="lang-btn <?= CURRENT_LANG === 'pt' ? 'active' : '' ?>" title="Português">
                            <img src="https://flagcdn.com/w20/br.png" alt="PT"> PT
                        </a>
                        <a href="<?= langSpecificUrl($current_page, 'en') ?>" class="lang-btn <?= CURRENT_LANG === 'en' ? 'active' : '' ?>" title="English">
                            <img src="https://flagcdn.com/w20/us.png" alt="EN"> EN
                        </a>
                    </div>
                    <div class="tz-dropdown-container">
                        <button class="tz-btn" id="tz-toggle-btn" aria-label="<?= t('online.tz_selector_label') ?>">
                            <i class="fas fa-clock"></i> <span id="tz-current-label">UTC-3</span>
                        </button>
                        <div class="tz-dropdown-menu" id="tz-dropdown-menu">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <script>
        // Ajuste dinâmico do padding-top do body baseado na altura do header
        function syncHeaderHeight() {
            const header = document.querySelector('.header');
            if (header) {
                const height = header.offsetHeight;
                document.body.style.paddingTop = height + 'px';
                // Define variável CSS global para ser usada no scroll-margin-top
                document.documentElement.style.setProperty('--header-offset', height + 'px');
            }
        }

        // Executar ao carregar, ao redimensionar e ao interagir
        window.addEventListener('load', syncHeaderHeight);
        window.addEventListener('resize', syncHeaderHeight);
        
        // Se houver banners que podem sumir, reajustar
        const observer = new MutationObserver(syncHeaderHeight);
        observer.observe(document.body, { attributes: true, childList: true, subtree: true });

        // Menu mobile toggle
        document.getElementById('menu-toggle-btn').addEventListener('click', function () {
            const nav = document.getElementById('main-nav');
            const isOpen = nav.classList.toggle('show');
            this.setAttribute('aria-expanded', isOpen);
            this.querySelector('i').classList.toggle('fa-bars', !isOpen);
            this.querySelector('i').classList.toggle('fa-times', isOpen);
            
            setTimeout(syncHeaderHeight, 10);
        });

        // Preservação de Scroll e Estado ao trocar de idioma
        document.querySelectorAll('.lang-btn:not(.active), #lang-suggestion-banner a').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const currentPath = window.location.pathname;
                
                // ── Mapa de tradução dinâmica das abas de equipe ────────────────────────────
                // Quando o JS faz pushState (ex: /equipe/bastidores), o href do botão ainda
                // aponta para o tab do load inicial. Recalculamos dinamicamente aqui.
                const teamTabMapToEn = { 'online': 'online', 'presencial': 'in-person', 'bastidores': 'backstage', 'iniciativas': 'initiatives' };
                const teamTabMapToPt = { 'online': 'online', 'in-person': 'presencial', 'backstage': 'bastidores', 'initiatives': 'iniciativas' };
                
                const isTeamPt = currentPath.startsWith('/equipe/');
                const isTeamEn = currentPath.startsWith('/en/team/');
                
                if (isTeamPt || isTeamEn) {
                    e.preventDefault();
                    sessionStorage.setItem('restoreScrollY', window.scrollY);
                    
                    // Extrair o tabslug atual do pathname
                    const currentTabSlug = isTeamPt
                        ? currentPath.replace('/equipe/', '')
                        : currentPath.replace('/en/team/', '');
                    
                    // Determinar o idioma de destino pelo href do botão
                    const targetIsEn = this.href.includes('/en/');
                    
                    // Traduzir o tabslug para o idioma alvo
                    const translatedSlug = targetIsEn
                        ? (teamTabMapToEn[currentTabSlug] || currentTabSlug)
                        : (teamTabMapToPt[currentTabSlug] || currentTabSlug);
                    
                    const newPath = targetIsEn ? '/en/team/' + translatedSlug : '/equipe/' + translatedSlug;
                    
                    // Preservar query params ativos (ex: ?projeto=X para iniciativas)
                    const currentSearch = window.location.search;
                    setTimeout(() => {
                        window.location.href = window.location.origin + newPath + currentSearch;
                    }, 10);
                    return;
                }
                // ────────────────────────────────────────────────────────────────────────────

                // Se estivermos no online ou em um slug premium limpo de idioma (/italiano, /japanese, etc.),
                // NÃO interceptar com JS! Deixe o navegador fazer a navegação limpa e direta para a URL premium gerada pelo PHP!
                const isOnlineOrPremiumSlug = currentPath.includes('/online') || 
                    (!currentPath.includes('/presencial') && !currentPath.includes('/in-person') &&
                     !currentPath.includes('/equipe') && !currentPath.includes('/team') &&
                     !currentPath.includes('/links') &&
                     !currentPath.includes('/contato') && !currentPath.includes('/contact') &&
                     currentPath !== '/' && currentPath !== '/en/');
                
                if (isOnlineOrPremiumSlug) {
                    return; // Deixa o clique fluir nativamente no HTML sem interferência de JS!
                }

                // Prevenir navegação imediata para processar o estado atual nas páginas complexas
                e.preventDefault();

                // 1. Salvar posição de Scroll
                sessionStorage.setItem('restoreScrollY', window.scrollY);

                // 2. Salvar estado de Acordeões (específico para página presencial)
                const accordionStates = {};
                document.querySelectorAll('[data-accordion-id]').forEach(acc => {
                    const id = acc.dataset.accordionId;
                    if (id) {
                        accordionStates[id] = acc.classList.contains('open');
                    }
                });
                
                if (Object.keys(accordionStates).length > 0) {
                    sessionStorage.setItem('openAccordions', JSON.stringify(accordionStates));
                }

                // 3. Mesclar parâmetros da URL atual (capturando mudanças via pushState)
                // com a URL de destino (que já tem o prefixo /en ou remove ele)
                try {
                    const currentParams = new URLSearchParams(window.location.search);
                    // Adicionando window.location.origin para suportar caminhos relativos
                    const dest = new URL(this.href, window.location.origin);
                    
                    // Se o destino for um slug premium limpo (não contém /online, /presencial, etc.),
                    // não poluir com parâmetros antigos de view ou idioma!
                    const isPremiumSlug = !dest.pathname.includes('/online') && !dest.pathname.includes('/presencial') && dest.pathname !== '/' && dest.pathname !== '/en/';
                    
                    // Transferir parâmetros atuais para o destino (filtrando resíduos)
                    currentParams.forEach((value, key) => {
                        if (key !== 'lang' && key !== 'slug') {
                            if (isPremiumSlug && (key === 'view' || key === 'idioma' || key === 'dia')) return;
                            dest.searchParams.set(key, value);
                        }
                    });
                    
                    // Pequeno delay de 10ms para garantir que o sessionStorage persista antes da navegação
                    setTimeout(() => {
                        window.location.href = dest.toString();
                    }, 10);
                } catch (err) {
                    // Fallback de segurança caso algo falhe no processamento da URL
                    setTimeout(() => {
                        window.location.href = this.href;
                    }, 10);
                }
            });
        });

        // Restaurar Scroll e Estado se houver dados salvos
        window.addEventListener('load', function() {
            const savedY = sessionStorage.getItem('restoreScrollY');
            if (savedY !== null) {
                sessionStorage.removeItem('restoreScrollY');
                setTimeout(() => {
                    window.scrollTo({ top: parseInt(savedY), behavior: 'instant' });
                }, 100);
            }
        });
    </script>
    <script src="/assets/js/timezone.js?v=<?= ASSET_VERSION ?>"></script>
