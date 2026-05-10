<?php
require_once 'config.php';
require_once 'includes/components.php';

$current_page   = 'equipe.php';
$initialTab     = $_GET['tab'] ?? 'online';
$projeto        = $_GET['projeto'] ?? '';

// SEO Dinâmico
if ($initialTab === 'iniciativas') {
    if (!empty($projeto)) {
        $title = htmlspecialchars($projeto) . ' | ' . SITE_NAME;
    } else {
        $title = t('team.tabs.iniciativas') . ' | ' . SITE_NAME;
    }
    $og_description = "Conheça nossas iniciativas especiais como o " . (!empty($projeto) ? htmlspecialchars($projeto) : "Clube do Livro e Consultoria de Carreira") . ". Projetos criados pela comunidade para você.";
} else {
    $title = t('team.title');
    $og_description = t('team.meta_description');
}
$canonical = SITE_URL . langUrl('equipe.php');

// Busca dados para os filtros
$hosts     = getHosts();
$languages = getLanguages();

// Parâmetros iniciais da URL
$initialLanguage = $_GET['idioma']  ?? 'all';
$initialRegion   = $_GET['regiao']  ?? 'all';
$initialRole     = $_GET['papel']   ?? 'all';

ob_start();
?>
<style>
    /* ---- EQUIPE PAGE STYLES ---- */
    .page-hero {
        width: 100%;
        height: 45vh !important;
        background: linear-gradient(135deg, rgba(0, 38, 84, 0.4) 0%, #ffffff 50%, rgba(227, 29, 28, 0.4) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        padding: 20px 0;
        overflow: hidden;
    }

    .hero-header {
        text-align: center;
        animation: fadeInDown 0.8s ease-out;
        max-width: 600px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .hero-image-wrapper {
        width: 100%;
        max-width: 220px;
        margin: 0 auto 10px;
    }

    .hero-image {
        width: 100%;
        height: auto;
        filter: drop-shadow(0 10px 20px rgba(0,38,84,0.1));
        transform: scale(1.05);
    }

    .hero-header h1 {
        font-size: 2.2rem;
        font-weight: 800;
        margin-bottom: 5px;
        color: var(--primary-color);
        letter-spacing: -1px;
    }

    .hero-header p {
        font-size: 1.1rem;
        color: #666;
        max-width: 500px;
        margin: 0 auto;
    }

    .category-tabs {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-bottom: 40px;
        flex-wrap: wrap;
    }

    .category-tab {
        padding: 12px 25px;
        border-radius: 30px;
        border: 1px solid #eee;
        background: #fff;
        color: #666;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .category-tab.active {
        background: var(--accent-red);
        color: #fff;
        border-color: var(--accent-red);
        box-shadow: 0 5px 15px rgba(227, 29, 28, 0.2);
    }

    .filter-section {
        margin-bottom: 50px;
        background: #fdfdfd;
        padding: 30px;
        border-radius: 20px;
        border: 1px solid #f0f0f0;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
    }

    .filter-group {
        display: none;
    }

    .filter-group.active {
        display: block;
    }

    .host-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 30px;
        margin-bottom: 80px;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
    }

    /* Dropdown Reset */
    .dropdown-content {
        display: none;
        position: absolute;
        background-color: #fff;
        min-width: 250px;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.1);
        z-index: 100;
        border-radius: 12px;
        border: 1px solid #eee;
        margin-top: 5px;
    }
    .dropdown-content.show {
        display: block;
    }
    
    .dropdown-button {
        background: #fff;
        border: 1px solid #ddd;
        padding: 12px 20px;
        border-radius: 12px;
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        font-weight: 500;
        color: #444;
    }

    .custom-dropdown-wrapper {
        position: relative;
        max-width: 400px;
        margin: 0 auto;
    }

    .dropdown-item {
        padding: 12px 20px;
        cursor: pointer;
        display: flex;
        align-items: center;
        transition: background 0.2s;
    }
    .dropdown-item:hover {
        background-color: #f8f8f8;
        color: var(--accent-red);
    }

    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
<?php
$page_styles = ob_get_clean();

include 'includes/header.php';
?>

<main>
    <section class="page-hero">
        <header class="hero-header">
            <div class="hero-image-wrapper">
                <img src="/assets/images/hero_team.png" alt="Equipe" class="hero-image" fetchpriority="high">
            </div>
            <h1><?= t('team.hero_title') ?></h1>
            <p><?= t('team.hero_subtitle') ?></p>
        </header>
    </section>

    <div class="container page-wrapper" style="padding: 60px 0;">

        <!-- Navegação por Categorias -->
        <div class="category-tabs">
            <button class="category-tab <?= $initialTab === 'online' ? 'active' : '' ?>" data-tab="online"><?= t('team.tabs.online') ?></button>
            <button class="category-tab <?= $initialTab === 'presencial' ? 'active' : '' ?>" data-tab="presencial"><?= t('team.tabs.presencial') ?></button>
            <button class="category-tab <?= $initialTab === 'bastidores' ? 'active' : '' ?>" data-tab="bastidores"><?= t('team.tabs.bastidores') ?></button>
            <button class="category-tab <?= $initialTab === 'iniciativas' ? 'active' : '' ?>" data-tab="iniciativas"><?= t('team.tabs.iniciativas') ?></button>
        </div>

        <div class="filter-section">
            <!-- Filtro (Online) -->
            <div id="filter-online" class="filter-group <?= $initialTab === 'online' ? 'active' : '' ?>">
                <div class="custom-dropdown-wrapper">
                    <button class="dropdown-button">
                        <span><i class="fas fa-language"></i> <span id="selected-lang-text"><?= t('team.filters.lang_placeholder') ?></span></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-content">
                        <div class="dropdown-item" data-value="all"><?= t('team.filters.all_languages') ?></div>
                        <?php foreach ($languages as $l): ?>
                        <div class="dropdown-item" data-value="<?= htmlspecialchars($l['name']) ?>">
                            <img src="https://flagcdn.com/20x15/<?= htmlspecialchars($l['flag_code']) ?>.png" alt="" style="margin-right:10px;">
                            <?= htmlspecialchars($l['name']) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Filtro (Presencial) -->
            <div id="filter-presencial" class="filter-group <?= $initialTab === 'presencial' ? 'active' : '' ?>">
                <div class="custom-dropdown-wrapper">
                    <button class="dropdown-button">
                        <span><i class="fas fa-map-marker-alt"></i> <span id="selected-city-text"><?= t('team.filters.region_placeholder') ?></span></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-content" id="presencial-dropdown">
                        <div class="dropdown-item" data-value="all"><?= t('team.filters.all_regions') ?></div>
                    </div>
                </div>
            </div>

            <!-- Filtro (Bastidores) -->
            <div id="filter-bastidores" class="filter-group <?= $initialTab === 'bastidores' ? 'active' : '' ?>">
                <div class="custom-dropdown-wrapper">
                    <button class="dropdown-button">
                        <span><i class="fas fa-user-tag"></i> <span id="selected-role-text"><?= t('team.filters.role_placeholder') ?></span></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-content">
                        <div class="dropdown-item" data-value="all"><?= t('team.filters.all_roles') ?></div>
                        <div class="dropdown-item" data-value="desenvolvimento">Desenvolvimento</div>
                        <div class="dropdown-item" data-value="design">Design</div>
                        <div class="dropdown-item" data-value="conteudo">Conteúdo</div>
                        <div class="dropdown-item" data-value="coordenacao">Coordenação</div>
                    </div>
                </div>
            </div>

            <div id="filter-iniciativas" class="filter-group <?= $initialTab === 'iniciativas' ? 'active' : '' ?>"></div>
        </div>

        <div class="host-grid" id="hosts-grid">
            <?php foreach ($hosts as $host):
                renderHostCard($host);
            endforeach; ?>
        </div>
    </div>
</main>

<?php if ($initialTab === 'iniciativas'): ?>
<!-- SEO Index: Iniciativas -->
<section class="seo-language-nav" style="padding: 40px 0; background: #fafafa; border-top: 1px solid #eee;">
    <div class="container" style="opacity: 0.7; transition: opacity 0.3s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'">
        <?php
        $currentLangCode = t('meta.lang_code');
        $initiatives = [];
        try {
            $conn = connectDB();
            $stmt = $conn->query("SELECT DISTINCT initiative_label, initiative_label_en FROM hosts WHERE status = 'ativo' AND initiative_label IS NOT NULL AND TRIM(initiative_label) != ''");
            $initiatives = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
        if (!empty($initiatives)):
        ?>
        <p style="margin-bottom: 15px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: #888;"><?= t('team.tabs.iniciativas') ?></p>
        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
            <?php foreach ($initiatives as $ini): 
                $iniName = ($currentLangCode === 'en' && !empty($ini['initiative_label_en'])) ? $ini['initiative_label_en'] : $ini['initiative_label'];
            ?>
            <a href="<?= langUrl('equipe.php') ?>?tab=iniciativas&projeto=<?= urlencode($iniName) ?>" style="color: #666; text-decoration: none; font-size: 0.75rem; border: 1px solid #d0d0d0; padding: 4px 12px; border-radius: 20px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <?= htmlspecialchars($iniName) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php
$page_scripts = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.category-tab');
    const filterGroups = document.querySelectorAll('.filter-group');
    const hostCards = document.querySelectorAll('.host-card');
    
    let currentTab = '{$initialTab}';
    let currentFilters = {
        online: 'all',
        presencial: 'all',
        bastidores: 'all',
        iniciativas: 'all'
    };

    // Populando regiões
    const regions = new Set();
    hostCards.forEach(card => {
        const reg = card.dataset.region;
        if (reg && reg !== 'all') regions.add(reg);
    });
    const presencialDropdown = document.getElementById('presencial-dropdown');
    if (presencialDropdown) {
        regions.forEach(reg => {
            const div = document.createElement('div');
            div.className = 'dropdown-item';
            div.dataset.value = reg;
            div.textContent = reg;
            presencialDropdown.appendChild(div);
        });
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            currentTab = tab.dataset.tab;
            filterGroups.forEach(g => g.classList.remove('active'));
            const targetGroup = document.getElementById('filter-' + currentTab);
            if (targetGroup) targetGroup.classList.add('active');
            applyFilters();
        });
    });

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.dropdown-button');
        if (btn) {
            const content = btn.nextElementSibling;
            content.classList.toggle('show');
            e.stopPropagation();
        } else if (e.target.classList.contains('dropdown-item')) {
            const item = e.target;
            const content = item.parentElement;
            const wrapper = item.closest('.filter-group');
            const type = wrapper.id.replace('filter-', '');
            currentFilters[type] = item.dataset.value;
            wrapper.querySelector('.dropdown-button span span').textContent = item.textContent.trim();
            content.classList.remove('show');
            applyFilters();
        } else {
            document.querySelectorAll('.dropdown-content').forEach(c => c.classList.remove('show'));
        }
    });

    function applyFilters() {
        hostCards.forEach(card => {
            const cardCats = (card.dataset.categories || '').split(',').map(s => s.trim().toLowerCase());
            const cardLangs = (card.dataset.languages || '').split(',').map(s => s.trim().toLowerCase());
            const cardRegion = (card.dataset.region || '').trim().toLowerCase();
            const cardRoles = (card.dataset.roles || '').split(',').map(s => s.trim().toLowerCase());

            let visible = cardCats.includes(currentTab);
            if (visible) {
                if (currentTab === 'online' && currentFilters.online !== 'all') visible = cardLangs.includes(currentFilters.online.toLowerCase());
                else if (currentTab === 'presencial' && currentFilters.presencial !== 'all') visible = cardRegion === currentFilters.presencial.toLowerCase();
                else if (currentTab === 'bastidores' && currentFilters.bastidores !== 'all') visible = cardRoles.some(r => r.includes(currentFilters.bastidores.toLowerCase()));
            }
            card.style.display = visible ? 'block' : 'none';
        });
    }

    applyFilters();
});
</script>
JS;

include 'includes/footer.php';
?>
