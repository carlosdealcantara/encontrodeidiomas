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

    /* Featured Link Style */
    .link-featured {
        background: var(--accent-gradient);
        color: var(--white);
        padding: 22px;
        border: none;
    }
    .link-featured .title { font-size: 1.2rem; }
    .link-featured .subtitle { color: rgba(255,255,255,0.85); font-weight: 500; }
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
        box-shadow: -2px 2px 5px rgba(0,0,0,0.1);
    }

    /* Grid for Twins (Cronograma & Video) */
    .twin-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 12px;
    }

    .twin-grid .link-card {
        flex-direction: column;
        text-align: center;
        padding: 18px 10px;
        margin-bottom: 0;
        justify-content: center;
    }

    .twin-grid .icon-box {
        margin: 0 auto 10px;
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
    }
CSS;

include 'includes/header.php';

// Safe Fetch - ensuring we categorize ALL links correctly
$allLinks = getUsefulLinks();

// Temporary arrays to hold categorized links
$heroLink = null;
$twinLinks = []; // Cronograma (2) and Video (5)
$communityLinks = []; // English (3) and Others (4)
$footerLink = null; // Main structure (6)

foreach ($allLinks as $l) {
    $t = mb_strtolower($l['title']);
    
    // Link 1: Atalho Grupão (Featured)
    if (strpos($t, 'grupão') !== false || strpos($t, 'entrada') !== false) {
        // We might have Link 1 and Link 6 both matching "entrada"
        // Link 1 is usually the "shortcut", Link 6 is the "community"
        if (strpos($t, 'atalho') !== false || strpos($t, 'início') !== false || !$heroLink) {
             if (!$heroLink) $heroLink = $l; else $footerLink = $l;
        } else {
             $footerLink = $l;
        }
    } 
    // Link 2 & 5: Twins (Cronograma & Video)
    elseif (strpos($t, 'cronograma') !== false || strpos($t, 'vídeo') !== false || strpos($t, 'funcionam') !== false) {
        $twinLinks[] = $l;
    }
    // Link 3 & 4: Communities (Inglês & Outros)
    elseif (strpos($t, 'inglês') !== false || strpos($t, 'outros') !== false || strpos($t, 'demais') !== false || strpos($t, 'idiomas') !== false) {
        $communityLinks[] = $l;
    }
    // Catch-all/Link 6
    else {
        if (!$footerLink) $footerLink = $l; else $communityLinks[] = $l;
    }
}

// Special case: If user says Link 1 is "Online comece por aqui", let's ensure it has that label
if ($heroLink) {
    $heroLink['subtitle'] = "Atalho direto para o Grupão de Entrada";
    $heroLink['badge'] = "Comece por aqui";
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

        <!-- Seção 1: Destaque Principal -->
        <?php if ($heroLink): ?>
            <div class="section-label">Acesso Imediato</div>
            <a href="<?= htmlspecialchars($heroLink['url']) ?>" class="link-card link-featured stagger-1" target="_blank">
                <div class="badge-top"><?= $heroLink['badge'] ?? 'Destaque' ?></div>
                <div class="icon-box">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <div class="content">
                    <span class="title"><?= htmlspecialchars($heroLink['title']) ?></span>
                    <span class="subtitle"><?= htmlspecialchars($heroLink['subtitle']) ?></span>
                </div>
                <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>

        <!-- Seção 2: Gêmeos (Cronograma & Vídeo) -->
        <?php if (!empty($twinLinks)): ?>
            <div class="section-label">Entenda o Projeto</div>
            <div class="twin-grid">
                <?php foreach ($twinLinks as $idx => $tl): ?>
                    <a href="<?= htmlspecialchars($tl['url']) ?>" class="link-card stagger-2" target="_blank">
                        <div class="icon-box">
                            <i class="<?= (strpos(mb_strtolower($tl['title']), 'vídeo') !== false || strpos(mb_strtolower($tl['title']), 'funcionam') !== false) ? 'fas fa-play' : 'fas fa-calendar-days' ?>"></i>
                        </div>
                        <div class="content">
                            <span class="title"><?= htmlspecialchars($tl['title']) ?></span>
                            <span class="subtitle" style="font-size: 0.7rem;">Clique para ver</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Seção 3: Comunidades por Idioma -->
        <?php if (!empty($communityLinks)): ?>
            <div class="section-label">Pratique um Idioma</div>
            <?php foreach ($communityLinks as $idx => $cl): ?>
                <a href="<?= htmlspecialchars($cl['url']) ?>" class="link-card stagger-3" target="_blank">
                    <div class="icon-box">
                        <i class="<?= strpos(mb_strtolower($cl['title']), 'inglês') !== false ? 'fas fa-flag-usa' : 'fas fa-globe' ?>"></i>
                    </div>
                    <div class="content">
                        <span class="title"><?= htmlspecialchars($cl['title']) ?></span>
                        <span class="subtitle"><?= strpos(mb_strtolower($cl['title']), 'inglês') !== false ? 'O maior grupo do projeto' : 'Italiano, Francês, Alemão e +50' ?></span>
                    </div>
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Seção 4: Estrutura Completa -->
        <?php if ($footerLink): ?>
            <div class="section-label">Nossa Estrutura</div>
            <a href="<?= htmlspecialchars($footerLink['url']) ?>" class="link-card" target="_blank">
                <div class="icon-box">
                    <i class="fas fa-sitemap"></i>
                </div>
                <div class="content">
                    <span class="title"><?= htmlspecialchars($footerLink['title']) ?></span>
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
