<?php
require_once 'config.php';

$title          = 'Links Úteis';
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
        margin-bottom: 40px;
        animation: fadeInDown 0.8s ease-out;
    }

    .hero-image-wrapper {
        width: 100%;
        max-width: 280px;
        margin: 0 auto 20px;
        position: relative;
    }

    .hero-image {
        width: 100%;
        height: auto;
        border-radius: 30px;
        filter: drop-shadow(0 15px 30px rgba(0,38,84,0.15));
    }

    .hero-header h1 {
        font-size: 2.2rem;
        font-weight: 800;
        margin-bottom: 8px;
        background: var(--accent-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        letter-spacing: -1px;
    }

    .hero-header p {
        color: #666;
        font-size: 1.1rem;
        max-width: 80%;
        margin: 0 auto;
    }

    /* Section Headers */
    .section-label {
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #999;
        margin: 30px 0 15px 5px;
        letter-spacing: 1px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

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
        padding: 18px 20px;
        margin-bottom: 15px;
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
        transform: translateY(-5px) scale(1.02);
        box-shadow: var(--shadow-md);
        background: var(--white);
    }

    .link-card .icon-box {
        width: 50px;
        height: 50px;
        background: #f0f2f5;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 18px;
        font-size: 1.4rem;
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
        font-size: 1.1rem;
        display: block;
        margin-bottom: 2px;
    }

    .link-card .subtitle {
        font-size: 0.85rem;
        color: #777;
        display: block;
    }

    /* Featured Link (Hero CTA) */
    .link-featured {
        background: var(--accent-gradient);
        color: var(--white);
        padding: 24px;
        border: none;
    }

    .link-featured .title { font-size: 1.25rem; }
    .link-featured .subtitle { color: rgba(255,255,255,0.8); }
    .link-featured .icon-box { background: rgba(255,255,255,0.2); color: var(--white); }
    
    .badge-top {
        position: absolute;
        top: 0;
        right: 0;
        background: var(--accent-yellow);
        color: var(--primary-color);
        padding: 4px 12px;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        border-bottom-left-radius: 12px;
    }

    /* Grid for Info Cards */
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 15px;
    }

    .info-grid .link-card {
        flex-direction: column;
        text-align: center;
        padding: 20px 10px;
        margin-bottom: 0;
    }

    .info-grid .icon-box {
        margin: 0 auto 12px;
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
        .hero-header h1 { font-size: 1.8rem; }
        .info-grid { grid-template-columns: 1fr; }
    }
CSS;

include 'includes/header.php';

// Logic to categorize links
$allLinks = getUsefulLinks();
$heroLink = null;
$infoLinks = [];
$communityLinks = [];
$footerLink = null;

foreach ($allLinks as $l) {
    $t = mb_strtolower($l['title']);
    // Link 1: Atalho Grupão (Featured)
    if (strpos($t, 'grupão') !== false || strpos($t, 'entrada') !== false) {
        if (!$heroLink) $heroLink = $l; else $footerLink = $l; // Link 6 logic (regions)
    } 
    // Link 2 & 5: Info (Cronograma, Vídeo)
    elseif (strpos($t, 'cronograma') !== false || strpos($t, 'vídeo') !== false || strpos($t, 'funcionam') !== false) {
        $infoLinks[] = $l;
    }
    // Link 3 & 4: Communities (Inglês, Outros)
    elseif (strpos($t, 'inglês') !== false || strpos($t, 'outros') !== false || strpos($t, 'demais') !== false) {
        $communityLinks[] = $l;
    }
    // Fallback/Link 6
    else {
        $footerLink = $l;
    }
}
?>

<main>
    <div class="links-container">
        
        <header class="hero-header">
            <div class="hero-image-wrapper">
                <img src="assets/images/community_hub.png" alt="Hub de Comunidades" class="hero-image">
            </div>
            <h1>Central de Links</h1>
            <p>Conecte-se com o mundo através dos nossos grupos e recursos.</p>
        </header>

        <!-- Seção 1: Destaque / Atalho Rápido -->
        <?php if ($heroLink): ?>
            <div class="section-label">Acesso Rápido</div>
            <a href="<?= htmlspecialchars($heroLink['url']) ?>" class="link-card link-featured stagger-1" target="_blank">
                <div class="badge-top">Recomendado</div>
                <div class="icon-box">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <div class="content">
                    <span class="title"><?= htmlspecialchars($heroLink['title']) ?></span>
                    <span class="subtitle">Atalho direto para começar agora!</span>
                </div>
                <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>

        <!-- Seção 2: Entenda o Projeto -->
        <?php if (!empty($infoLinks)): ?>
            <div class="section-label">Explore o Projeto</div>
            <div class="info-grid">
                <?php foreach ($infoLinks as $idx => $il): ?>
                    <a href="<?= htmlspecialchars($il['url']) ?>" class="link-card stagger-2" target="_blank">
                        <div class="icon-box">
                            <i class="<?= strpos(mb_strtolower($il['title']), 'vídeo') !== false ? 'fas fa-play' : 'fas fa-calendar-alt' ?>"></i>
                        </div>
                        <div class="content">
                            <span class="title"><?= htmlspecialchars($il['title']) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Seção 3: Comunidades por Idioma -->
        <?php if (!empty($communityLinks)): ?>
            <div class="section-label">Nossas Comunidades</div>
            <?php foreach ($communityLinks as $idx => $cl): ?>
                <a href="<?= htmlspecialchars($cl['url']) ?>" class="link-card stagger-3" target="_blank">
                    <div class="icon-box">
                        <i class="<?= strpos(mb_strtolower($cl['title']), 'inglês') !== false ? 'fas fa-flag-usa' : 'fas fa-globe' ?>"></i>
                    </div>
                    <div class="content">
                        <span class="title"><?= htmlspecialchars($cl['title']) ?></span>
                        <span class="subtitle"><?= strpos(mb_strtolower($cl['title']), 'inglês') !== false ? 'O maior grupo do projeto' : 'Mais de 50 idiomas disponíveis' ?></span>
                    </div>
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Seção 4: Estrutura Completa -->
        <?php if ($footerLink): ?>
            <div class="section-label">Estrutura Completa</div>
            <a href="<?= htmlspecialchars($footerLink['url']) ?>" class="link-card" target="_blank">
                <div class="icon-box">
                    <i class="fas fa-users-rectangle"></i>
                </div>
                <div class="content">
                    <span class="title"><?= htmlspecialchars($footerLink['title']) ?></span>
                    <span class="subtitle">Regiões e grupos de organização</span>
                </div>
                <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>

        <!-- Rodapé de Redes Sociais -->
        <div style="text-align: center; margin-top: 40px; display: flex; justify-content: center; gap: 20px;">
            <a href="https://www.instagram.com/encontrodeidiomas/" target="_blank" style="color: var(--accent-red); font-size: 1.5rem;"><i class="fab fa-instagram"></i></a>
            <a href="https://discord.gg/STHkrEhMpP" target="_blank" style="color: #5865F2; font-size: 1.5rem;"><i class="fab fa-discord"></i></a>
        </div>

    </div>
</main>

<?php include 'includes/footer.php'; ?>
