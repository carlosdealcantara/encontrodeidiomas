<?php
require_once __DIR__ . '/../config.php';
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Encontro de Idiomas - Comunidade gratuita para praticar idiomas em grupo! Participe de encontros online e presenciais de inglês, espanhol, francês, alemão, japonês, mandarim, italiano, russo e mais.">
    <meta name="keywords" content="encontro de idiomas, clube de idiomas, clube poliglota, grupo de conversação, praticar idiomas, encontros online, chamada de vídeo, videoconferência, gratuito, inglês, espanhol, francês, alemão, japonês, mandarim, italiano, russo, coreano, poliglota, intercâmbio cultural, conversação, aprender idiomas, fluência, comunidade de idiomas">
    <meta name="author" content="Encontro de Idiomas">
    <meta name="robots" content="index, follow">
    
    <!-- Essential Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= $title ?? SITE_NAME ?> - Comunidade gratuita para praticar idiomas">
    <meta property="og:description" content="Participe gratuitamente de encontros para praticar inglês, espanhol, francês, alemão, japonês e outros idiomas online e presenciais.">
    <meta property="og:image" content="<?= SITE_URL ?>/assets/images/og_image.png">
    <meta property="og:url" content="<?= SITE_URL . $_SERVER['REQUEST_URI'] ?>">
    
    <!-- WhatsApp specific -->
    <meta property="og:site_name" content="<?= SITE_NAME ?>">
    <meta property="og:locale" content="pt_BR">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= SITE_URL . $_SERVER['REQUEST_URI'] ?>">
    <meta property="twitter:title" content="<?= $title ?? SITE_NAME ?> - Comunidade gratuita para praticar idiomas">
    <meta property="twitter:description" content="Participe gratuitamente de encontros para praticar inglês, espanhol, francês, alemão, japonês e outros idiomas online e presenciais. Comunidade para poliglotas e estudantes.">
    <meta property="twitter:image" content="<?= SITE_URL ?>/assets/images/logo.png">
    
    <link rel="canonical" href="<?= SITE_URL . $_SERVER['REQUEST_URI'] ?>">
    <title><?= $title ?? SITE_NAME ?> - Aprenda se divertindo!</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <link rel="apple-touch-icon" href="assets/images/favicon.png">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php if(isset($swiper_enabled) && $swiper_enabled): ?>
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
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
        :root {
            --primary-color: #1a1a1a;
            --accent-red: #e31d1c;
            --accent-blue: #002654;
            --accent-yellow: #ffd700;
            --text-color: #333;
            --bg-light: #f8f9fa;
            --white: #ffffff;
            --card-bg: #ffffff;
            --border-radius: 16px;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            --highlight-bg: rgba(255, 215, 0, 0.1);
            --highlight-border: #ffd700;
            --now-badge-bg: #e31d1c;
            --disabled-bg: #e0e0e0;
            --disabled-color: #888;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        /* Impede qualquer overflow horizontal em qualquer elemento */
        html, body {
            max-width: 100%;
            overflow-x: hidden;
            position: relative;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            color: #333;
            background-color: #f7f7f7;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            width: 100%;
        }
        
        .header {
            background: var(--primary-color);
            color: var(--white);
            padding: 1rem 0;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            max-width: 1200px;
            margin: 0 auto;
            flex-wrap: wrap; /* Permite que o menu quebre para a linha de baixo em telas pequenas */
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--white);
        }

        .site-title {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .site-description {
            font-size: 1rem;
            opacity: 0.9;
        }

        .nav-links {
            display: flex;
            gap: 20px;
        }

        .nav-links a {
            color: #fff;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 20px;
            transition: background-color 0.3s ease;
            white-space: nowrap; /* Impede que o texto quebre dentro do link */
        }

        .nav-links a:hover, .nav-links a.active {
            background: var(--accent-red);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            width: 100%;
        }
        
        /* Menu mobile toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .header-content {
                justify-content: space-between;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .nav-links {
                display: none;
                width: 100%;
                flex-direction: column;
                gap: 10px;
                margin-top: 20px;
            }
            
            .nav-links.show {
                display: flex;
            }
            
            .nav-links a {
                display: block;
                text-align: center;
            }
        }
        
        <?php if(isset($page_styles)): ?>
        <?= $page_styles ?>
        <?php endif; ?>
    </style>
    
    <?php if(isset($extra_head)): ?>
    <?= $extra_head ?>
    <?php endif; ?>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo-container">
                <img src="assets/images/logo.png" alt="Encontro de Idiomas" class="logo">
                <div class="site-info">
                    <div class="site-title">Encontro de Idiomas</div>
                    <div class="site-description">Aprenda se divertindo!</div>
                </div>
            </div>
            
            <button class="menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <nav class="nav-links">
                <a href="index.php" <?= $current_page == 'index.php' ? 'class="active"' : '' ?>>Início</a>
                <a href="online.php" <?= $current_page == 'online.php' ? 'class="active"' : '' ?>>Encontros Online</a>
                <a href="equipe.php" <?= $current_page == 'equipe.php' ? 'class="active"' : '' ?>>Nossa Equipe</a>
                <a href="links.php" <?= $current_page == 'links.php' ? 'class="active"' : '' ?>>Links Úteis</a>
                <a href="contato.php" <?= $current_page == 'contato.php' ? 'class="active"' : '' ?>>Contato</a>
            </nav>
        </div>
    </header>
    
    <main> 