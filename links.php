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
        border-radius: 30px;
        filter: drop-shadow(0 15px 30px rgba(0,38,84,0.15));
    }

    .hero-header h1 {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 5px;
        background: var(--accent-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        letter-spacing: -1px;
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
        margin: 25px 0 12px 5px;
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

// Safe Fetch and Categorization
$allLinks = getUsefulLinks();
$onlineLink = null;
$presencialLink = null;
$cronogramaLink = null;
$communityLinks = [];
$structureLink = null;

foreach ($allLinks as $l) {
    $t = mb_strtolower($l['title']);
    
    // Online (Link 1)
    if (strpos($t, 'grupão') !== false || strpos($t, 'entrada') !== false) {
        if (!$onlineLink) $onlineLink = $l; else $structureLink = $l;
    } 
    // Cronograma (Link 2)
    elseif (strpos($t, 'cronograma') !== false) {
        $cronogramaLink = $l;
    }
    // Presencial (Link 5)
    elseif (strpos($t, 'vídeo') !== false || strpos($t, 'funcionam') !== false) {
        $presencialLink = $l;
    }
    // Communities (3 & 4)
    elseif (strpos($t, 'inglês') !== false || strpos($t, 'outros') !== false || strpos($t, 'demais') !== false || strpos($t, 'idiomas') !== false) {
        $communityLinks[] = $l;
    }
    // Catch-all
    else {
        if (!$structureLink) $structureLink = $l; else $communityLinks[] = $l;
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

        <!-- Seção 1: Entenda o Projeto (Os Gêmeos) -->
        <div class="section-label">Entenda o Projeto</div>
        <div class="twin-grid">
            <!-- Gêmeo A: Online -->
            <?php if ($onlineLink): ?>
                <a href="<?= htmlspecialchars($onlineLink['url']) ?>" class="link-card stagger-1" target="_blank">
                    <div class="badge-top">Comece por aqui</div>
                    <div class="icon-box">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div class="content">
                        <span class="title">Online – Encontros Virtuais</span>
                        <span class="subtitle" style="font-size: 0.7rem;">Clique para entrar</span>
                    </div>
                </a>
            <?php endif; ?>

            <!-- Gêmeo B: Presencial -->
            <?php if ($presencialLink): ?>
                <a href="<?= htmlspecialchars($presencialLink['url']) ?>" class="link-card stagger-1" target="_blank">
                    <div class="icon-box">
                        <i class="fas fa-play"></i>
                    </div>
                    <div class="content">
                        <span class="title">Presencial – Vídeo de Apresentação</span>
                        <span class="subtitle" style="font-size: 0.7rem;">Assista agora</span>
                    </div>
                </a>
            <?php endif; ?>
        </div>

        <!-- Seção 2: Programação Atual -->
        <?php if ($cronogramaLink): ?>
            <div class="section-label">Programação</div>
            <a href="<?= htmlspecialchars($cronogramaLink['url']) ?>" class="link-card stagger-2" target="_blank">
                <div class="icon-box">
                    <i class="fas fa-calendar-days"></i>
                </div>
                <div class="content">
                    <span class="title"><?= htmlspecialchars($cronogramaLink['title']) ?></span>
                    <span class="subtitle">Confira os dias e horários atuais</span>
                </div>
                <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>

        <!-- Seção 3: Comunidades por Idioma -->
        <?php if (!empty($communityLinks)): ?>
            <div class="section-label">Pratique um Idioma</div>
            <?php foreach ($communityLinks as $cl): ?>
                <a href="<?= htmlspecialchars($cl['url']) ?>" class="link-card stagger-3" target="_blank">
                    <div class="icon-box">
                        <i class="<?= strpos(mb_strtolower($cl['title']), 'inglês') !== false ? 'fas fa-flag-usa' : 'fas fa-globe' ?>"></i>
                    </div>
                    <div class="content">
                        <span class="title"><?= htmlspecialchars($cl['title']) ?></span>
                        <span class="subtitle"><?= strpos(mb_strtolower($cl['title']), 'inglês') !== false ? 'O maior grupo do projeto' : 'Comunidade com diversos idiomas' ?></span>
                    </div>
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Seção 4: Estrutura Completa -->
        <?php if ($structureLink): ?>
            <div class="section-label">Nossa Estrutura</div>
            <a href="<?= htmlspecialchars($structureLink['url']) ?>" class="link-card" target="_blank">
                <div class="icon-box">
                    <i class="fas fa-sitemap"></i>
                </div>
                <div class="content">
                    <span class="title"><?= htmlspecialchars($structureLink['title']) ?></span>
                    <span class="subtitle">Comunidade principal e grupos regionais</span>
                </div>
                <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>

        <!-- Rodapé Social -->
        <div style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; display: flex; justify-content: center; gap: 25px;">
            <a href="https://www.instagram.com/encontrodeidiomas/" target="_blank" style="color: var(--accent-red); font-size: 1.6rem; transition: 0.3s;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'"><i class="fab fa-instagram"></i></a>
            <a href="https://discord.gg/STHkrEhMpP" target="_blank" style="color: #5865F2; font-size: 1.6rem; transition: 0.3s;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'"><i class="fab fa-discord"></i></a>
        </div>

    </div>
</main>

<?php include 'includes/footer.php'; ?>
