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
$og_description = $og_description ?? getSetting('site_description', 'Comunidade gratuita para praticar idiomas via videoconferência.');
$canonical      = $canonical      ?? SITE_URL . '/' . $current_page;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= sanitize($og_description) ?>">
    <meta name="author" content="Encontro de Idiomas">
    <meta name="robots" content="index, follow">

    <!-- Open Graph / Facebook / WhatsApp -->
    <meta property="og:type"        content="website">
    <meta property="og:title"       content="<?= sanitize($title) ?> - Comunidade gratuita para praticar idiomas">
    <meta property="og:description" content="<?= sanitize($og_description) ?>">
    <meta property="og:image"       content="<?= SITE_URL ?>/assets/images/og_image.png">
    <meta property="og:url"         content="<?= sanitize($canonical) ?>">
    <meta property="og:site_name"   content="<?= SITE_NAME ?>">
    <meta property="og:locale"      content="pt_BR">

    <!-- Twitter -->
    <meta property="twitter:card"        content="summary_large_image">
    <meta property="twitter:url"         content="<?= sanitize($canonical) ?>">
    <meta property="twitter:title"       content="<?= sanitize($title) ?> - Comunidade gratuita para praticar idiomas">
    <meta property="twitter:description" content="<?= sanitize($og_description) ?>">
    <meta property="twitter:image"       content="<?= SITE_URL ?>/assets/images/og_image.png">

    <link rel="canonical" href="<?= sanitize($canonical) ?>">
    <title><?= sanitize($title) ?> - Aprenda se divertindo!</title>

    <!-- Favicon -->
    <link rel="icon"             type="image/png" href="assets/images/favicon.png">
    <link rel="apple-touch-icon"                  href="assets/images/favicon.png">

    <!-- Fonts -->
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
            /* Espaço para o header fixo — ESSENCIAL para não "pular" o conteúdo */
            padding-top: 80px;
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
            gap: 20px;
        }

        .nav-links a {
            color: var(--white);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 20px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: var(--accent-red);
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
                margin-top: 15px;
                padding-bottom: 10px;
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
        body.has-notice { padding-top: 120px; }

        <?php if (!empty($page_styles)) echo $page_styles; ?>
    </style>

    <?php if (!empty($extra_head)) echo $extra_head; ?>
</head>
<body>
    <?php if (getSetting('global_notice_active') === '1'): ?>
        <div class="global-notice">
            <i class="fas fa-exclamation-circle"></i> <?= getSetting('global_notice_text') ?>
        </div>
        <script>document.body.classList.add('has-notice');</script>
    <?php endif; ?>

    <header class="header">
        <div class="header-content">
            <div class="logo-container">
                <img src="assets/images/logo.png" alt="Logo Encontro de Idiomas" class="logo">
                <div>
                    <div class="site-title"><?= getSetting('site_title', 'Encontro de Idiomas') ?></div>
                    <div class="site-description"><?= getSetting('site_description', 'Aprenda se divertindo!') ?></div>
                </div>
            </div>

            <button class="menu-toggle" aria-label="Abrir menu" id="menu-toggle-btn">
                <i class="fas fa-bars"></i>
            </button>

            <nav class="nav-links" id="main-nav" aria-label="Navegação principal">
                <a href="index.php"   <?= $current_page === 'index.php'   ? 'class="active" aria-current="page"' : '' ?>>Início</a>
                <a href="online.php"  <?= $current_page === 'online.php'  ? 'class="active" aria-current="page"' : '' ?>>Online</a>
                <a href="equipe.php"  <?= $current_page === 'equipe.php'  ? 'class="active" aria-current="page"' : '' ?>>Equipe</a>
                <a href="links.php"   <?= $current_page === 'links.php'   ? 'class="active" aria-current="page"' : '' ?>>Links</a>
                <a href="contato.php" <?= $current_page === 'contato.php' ? 'class="active" aria-current="page"' : '' ?>>Contato</a>
            </nav>
        </div>
    </header>

    <script>
        // Menu mobile toggle
        document.getElementById('menu-toggle-btn').addEventListener('click', function () {
            const nav = document.getElementById('main-nav');
            const isOpen = nav.classList.toggle('show');
            this.setAttribute('aria-expanded', isOpen);
            this.querySelector('i').classList.toggle('fa-bars', !isOpen);
            this.querySelector('i').classList.toggle('fa-times', isOpen);
        });
    </script>
