<?php
require_once 'config.php';

$title          = 'Links Úteis';
$current_page   = 'links.php';
$og_description = 'Links importantes do Encontro de Idiomas - Acesse nossos grupos de WhatsApp, Instagram e recursos para praticar idiomas gratuitamente.';
$canonical      = 'https://encontrodeidiomas.com.br/links.php';

$page_styles = <<<CSS
    :root {
        --link-bg:       rgba(0,0,0,.05);
        --link-hover-bg: rgba(0,0,0,.1);
    }

    .profile {
        text-align: center;
        margin-top: 60px;
        margin-bottom: 40px;
    }

    .profile-image {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        margin: 0 auto 20px;
        overflow: hidden;
        border: 3px solid var(--primary-color);
        box-shadow: 0 10px 25px rgba(0,0,0,.1);
    }

    .logo-text {
        font-weight: bold;
        font-size: 42px;
        background: linear-gradient(135deg, var(--accent-red) 0%, var(--accent-blue) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100%;
    }

    h1 {
        font-size: 2.2rem;
        color: var(--primary-color);
        margin-bottom: 5px;
        text-align: center;
    }

    h2 {
        font-size: 1.2rem;
        color: var(--text-color);
        font-weight: 500;
        margin-bottom: 30px;
        opacity: 0.9;
        text-align: center;
    }

    .links-wrapper {
        max-width: 700px;
        margin: 0 auto;
    }

    .links {
        display: flex;
        flex-direction: column;
        gap: 16px;
        margin-bottom: 40px;
    }

    .link {
        background: var(--link-bg);
        padding: 16px;
        border-radius: 12px;
        text-decoration: none;
        color: var(--text-color);
        display: flex;
        align-items: center;
        transition: all 0.3s ease;
        box-shadow: 0 4px 8px rgba(0,0,0,.1);
    }

    .link:hover {
        transform: translateY(-3px);
        background: var(--link-hover-bg);
        box-shadow: 0 6px 12px rgba(0,0,0,.15);
    }

    .link i {
        font-size: 1.5rem;
        margin-right: 15px;
        width: 24px;
        text-align: center;
        color: var(--primary-color);
    }

    .social-icons {
        display: flex;
        justify-content: center;
        gap: 30px;
        margin-top: 40px;
        margin-bottom: 60px;
    }

    .social-icons a {
        color: var(--primary-color);
        font-size: 1.8rem;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--link-bg);
        transition: all 0.3s ease;
    }

    .social-icons a:hover {
        transform: scale(1.1);
        background: var(--link-hover-bg);
    }

    @media (max-width: 768px) {
        .profile { margin-top: 30px; }
    }
CSS;

include 'includes/header.php';
?>

    <main>
        <div class="container">
            <div class="profile">
                <div class="profile-image">
                    <div class="logo-text">EI</div>
                </div>
                <h1>Links Úteis</h1>
                <h2>Aprenda se divertindo!</h2>
            </div>

            <div class="links-wrapper">
                <div class="links">
                    <?php 
                    $usefulLinks = getUsefulLinks();
                    foreach ($usefulLinks as $ul): ?>
                        <a href="<?= htmlspecialchars($ul['url']) ?>" class="link" target="_blank" rel="noopener noreferrer">
                            <i class="<?= $ul['icon'] ?: 'fas fa-link' ?>"></i>
                            <span><?= htmlspecialchars($ul['title']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="social-icons">
                    <a href="https://www.instagram.com/encontrodeidiomas/" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="https://discord.gg/STHkrEhMpP" target="_blank" rel="noopener noreferrer" aria-label="Discord">
                        <i class="fab fa-discord"></i>
                    </a>
                </div>
            </div>
        </div>
    </main>

<?php include 'includes/footer.php'; ?>
