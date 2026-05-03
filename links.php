<?php
require_once 'config.php';

$title          = 'Links';
$current_page   = 'links.php';
$og_description = 'Hub de Comunidades do Encontro de Idiomas - Acesse nossos grupos de WhatsApp, cronogramas e recursos exclusivos.';
$canonical      = SITE_URL . '/links.php';

$page_styles = <<<CSS
    :root {
        --glass-bg: rgba(255, 255, 255, 0.85);
        --glass-border: rgba(255, 255, 255, 0.3);
        --accent-gradient: linear-gradient(135deg, var(--accent-blue) 0%, var(--accent-red) 100%);
        --shadow-sm: 0 4px 6px rgba(0,0,0,0.05);
        --shadow-md: 0 10px 20px rgba(0,0,0,0.1);
    }

    body {
        background: linear-gradient(180deg, #f0f2f5 0%, #ffffff 100%);
        min-height: 100vh;
    }

    .links-container {
        max-width: 600px;
        margin: 0 auto;
        padding: 20px 15px 80px;
    }

    /* Header Visual */
    .hero-header {
        text-align: center;
        margin-bottom: 30px;
        animation: fadeInDown 0.8s ease-out;
    }

    .hero-image-wrapper {
        width: 100%;
        max-width: 260px;
        margin: 0 auto 15px;
        position: relative;
    }

    .hero-image {
        width: 100%;
        height: auto;
        /* Removido bordas e sombras pesadas para um look mais limpo e integrado */
        filter: drop-shadow(0 10px 20px rgba(0,38,84,0.1));
        transform: scale(1.05); /* Leve destaque */
    }

    .hero-header h1 {
        font-size: 2.2rem;
        font-weight: 800;
        margin-bottom: 5px;
        /* Gradiente ajustado para o Vermelho e Azul da marca */
        background: linear-gradient(135deg, var(--accent-red) 0%, var(--accent-blue) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        letter-spacing: -1.5px;
    }

    .hero-header p {
        color: #666;
        font-size: 1rem;
        max-width: 85%;
        margin: 0 auto;
    }

    /* Section Headers */
    .section-label {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #999;
        margin: 25px 0 15px;
        letter-spacing: 1px;
        display: flex;
        align-items: center;
        gap: 15px;
        justify-content: center; /* Centralizado */
    }

    .section-label::before,
    .section-label::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #eee;
    }

    /* Link Cards Base */
    .link-card {
        background: var(--glass-bg);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 16px 20px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        text-decoration: none;
        color: var(--text-color);
        transition: var(--transition);
        box-shadow: var(--shadow-sm);
        position: relative;
        overflow: hidden;
    }

    .link-card:hover {
        transform: translateY(-4px) scale(1.01);
        box-shadow: var(--shadow-md);
        background: var(--white);
    }

    .link-card .icon-box {
        width: 46px;
        height: 46px;
        background: #f0f2f5;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        font-size: 1.3rem;
        color: var(--accent-blue);
        transition: var(--transition);
    }

    .link-card:hover .icon-box {
        background: var(--accent-gradient);
        color: var(--white);
    }

    .link-card .content {
        flex: 1;
    }

    .link-card .title {
        font-weight: 700;
        font-size: 1.05rem;
        display: block;
        margin-bottom: 1px;
    }

    .link-card .subtitle {
        font-size: 0.8rem;
        color: #777;
        display: block;
    }

    /* Twins Layout (Strictly Squares) */
    .twin-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 12px;
    }

    .twin-grid .link-card {
        flex-direction: column;
        text-align: center;
        padding: 25px 15px;
        margin-bottom: 0;
        min-height: 180px;
        justify-content: center;
    }

    .twin-grid .icon-box {
        margin: 0 auto 15px;
        width: 55px;
        height: 55px;
        font-size: 1.6rem;
    }

    .twin-grid .title {
        font-size: 1.1rem;
        line-height: 1.3;
    }
    
    .badge-top {
        position: absolute;
        top: 0;
        right: 0;
        background: var(--accent-yellow);
        color: var(--primary-color);
        padding: 4px 10px;
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        border-bottom-left-radius: 12px;
        box-shadow: -2px 2px 5px rgba(0,0,0,0.05);
    }

    /* Animations */
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .stagger-1 { animation: fadeInUp 0.5s ease both 0.1s; }
    .stagger-2 { animation: fadeInUp 0.5s ease both 0.2s; }
    .stagger-3 { animation: fadeInUp 0.5s ease both 0.3s; }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 480px) {
        .hero-header h1 { font-size: 1.7rem; }
        .twin-grid { grid-template-columns: 1fr; }
        .twin-grid .link-card { min-height: auto; padding: 20px; }
    }
CSS;

include 'includes/header.php';

$allLinks = getUsefulLinks();
?>

<main>
    <div class="links-container">
        
        <header class="hero-header">
            <div class="hero-image-wrapper">
                <img src="assets/images/hero_links_v2.png" alt="Hub de Comunidades" class="hero-image">
            </div>
            <h1>Links</h1>
            <p>Conecte-se com o mundo pelos nossos grupos e recursos.</p>
        </header>

        <div class="links-wrapper">
            <div class="section-label">Links</div>
            <?php 
            $i = 0;
            $twins_buffer = [];
            
            while ($i < count($allLinks)) {
                $l = $allLinks[$i];
                
                if ($l['layout_type'] === 'twin') {
                    // Start a twin grid for consecutive twins
                    echo '<div class="twin-grid">';
                    renderLinkCard($l, 'twin', 'stagger-1');
                    $i++;
                    
                    // Check if next one is also a twin to fill the pair
                    if ($i < count($allLinks) && $allLinks[$i]['layout_type'] === 'twin') {
                        renderLinkCard($allLinks[$i], 'twin', 'stagger-1');
                        $i++;
                    }
                    echo '</div>';
                } else {
                    // Render standard card
                    renderLinkCard($l, 'standard', 'stagger-2');
                    $i++;
                }
            }
            
            function renderLinkCard($link, $type, $animation) {
                $url = htmlspecialchars($link['url']);
                $title = htmlspecialchars($link['title']);
                $subtitle = htmlspecialchars($link['subtitle'] ?? '');
                $badge = htmlspecialchars($link['badge'] ?? '');
                $icon = htmlspecialchars($link['icon'] ?? 'fas fa-link');
                
                if ($type === 'twin'): ?>
                    <a href="<?= $url ?>" class="link-card <?= $animation ?>" target="_blank">
                        <?php if ($badge): ?><div class="badge-top"><?= $badge ?></div><?php endif; ?>
                        <div class="icon-box">
                            <i class="<?= $icon ?>"></i>
                        </div>
                        <div class="content">
                            <span class="title"><?= $title ?></span>
                            <?php if ($subtitle): ?><span class="subtitle" style="font-size: 0.7rem;"><?= $subtitle ?></span><?php endif; ?>
                        </div>
                    </a>
                <?php else: ?>
                    <a href="<?= $url ?>" class="link-card <?= $animation ?>" target="_blank">
                        <?php if ($badge): ?><div class="badge-top"><?= $badge ?></div><?php endif; ?>
                        <div class="icon-box">
                            <i class="<?= $icon ?>"></i>
                        </div>
                        <div class="content">
                            <span class="title"><?= $title ?></span>
                            <?php if ($subtitle): ?><span class="subtitle"><?= $subtitle ?></span><?php endif; ?>
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif;
            }
            ?>
        </div>

        <!-- Rodapé Social -->
        <div style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; display: flex; justify-content: center; gap: 25px;">
            <a href="https://www.instagram.com/encontrodeidiomas/" target="_blank" style="color: var(--accent-red); font-size: 1.6rem; transition: 0.3s;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'"><i class="fab fa-instagram"></i></a>
            <a href="https://www.tiktok.com/@encontrodeidiomas" target="_blank" style="color: #000; font-size: 1.6rem; transition: 0.3s;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'"><i class="fab fa-tiktok"></i></a>
            <a href="https://discord.gg/STHkrEhMpP" target="_blank" style="color: #5865F2; font-size: 1.6rem; transition: 0.3s;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'"><i class="fab fa-discord"></i></a>
        </div>

    </div>
</main>

<?php include 'includes/footer.php'; ?>
