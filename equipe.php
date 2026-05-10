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
    $og_description = "Conheça nossas iniciativas especiais. Projetos criados pela comunidade para você.";
} else {
    $title = t('team.title');
    $og_description = t('team.meta_description');
}
$canonical = SITE_URL . langUrl('equipe.php');

$hosts     = getHosts();
$languages = getLanguages();

include 'includes/header.php';
?>

<style>
    /* FORÇAR RESET DE LAYOUT */
    .host-grid {
        display: grid !important;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)) !important;
        gap: 20px !important;
        padding: 20px 0 !important;
    }
    .host-card {
        background: #fff !important;
        border-radius: 15px !important;
        overflow: hidden !important;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05) !important;
        border: 1px solid #eee !important;
    }
    .host-card img {
        width: 100% !important;
        height: 250px !important;
        object-fit: cover !important;
    }
    .host-info {
        padding: 15px !important;
    }
    
    /* FILTROS */
    .dropdown-content {
        display: none !important;
        position: absolute;
        background: white;
        z-index: 999;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .dropdown-content.show {
        display: block !important;
    }
    .category-tabs {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin: 30px 0;
    }
    .category-tab {
        padding: 10px 20px;
        border-radius: 20px;
        border: 1px solid #ddd;
        background: white;
        cursor: pointer;
    }
    .category-tab.active {
        background: var(--accent-red);
        color: white;
        border-color: var(--accent-red);
    }
    .filter-group { display: none; }
    .filter-group.active { display: block; text-align: center; }

    /* HERO */
    .page-hero {
        background: #f8f9fa;
        padding: 60px 0;
        text-align: center;
        border-bottom: 1px solid #eee;
    }
    .hero-image { width: 180px; margin-bottom: 20px; }
</style>

<main>
    <section class="page-hero">
        <div class="container">
            <img src="/assets/images/hero_team.png" alt="Equipe" class="hero-image">
            <h1><?= t('team.hero_title') ?></h1>
            <p><?= t('team.hero_subtitle') ?></p>
        </div>
    </section>

    <div class="container">
        <div class="category-tabs">
            <button class="category-tab <?= $initialTab === 'online' ? 'active' : '' ?>" data-tab="online"><?= t('team.tabs.online') ?></button>
            <button class="category-tab <?= $initialTab === 'presencial' ? 'active' : '' ?>" data-tab="presencial"><?= t('team.tabs.presencial') ?></button>
            <button class="category-tab <?= $initialTab === 'bastidores' ? 'active' : '' ?>" data-tab="bastidores"><?= t('team.tabs.bastidores') ?></button>
            <button class="category-tab <?= $initialTab === 'iniciativas' ? 'active' : '' ?>" data-tab="iniciativas"><?= t('team.tabs.iniciativas') ?></button>
        </div>

        <div class="filter-section" style="margin-bottom: 40px;">
            <div id="filter-online" class="filter-group <?= $initialTab === 'online' ? 'active' : '' ?>">
                <div style="position: relative; display: inline-block;">
                    <button class="category-tab dropdown-toggle">Selecionar Idioma <i class="fas fa-chevron-down"></i></button>
                    <div class="dropdown-content">
                        <div class="dropdown-item" data-value="all">Todos os Idiomas</div>
                        <?php foreach ($languages as $l): ?>
                            <div class="dropdown-item" data-value="<?= $l['name'] ?>"><?= $l['name'] ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <!-- Outros filtros omitidos por brevidade para estabilidade -->
        </div>

        <div class="host-grid" id="hosts-grid">
            <?php foreach ($hosts as $host): ?>
                <div class="host-card" 
                     data-categories="<?= $host['category'] ?>" 
                     data-languages="<?= $host['languages'] ?>"
                     data-region="<?= $host['region'] ?>"
                     data-roles="<?= $host['role'] ?>">
                    <img src="<?= getHostPhotoUrl($host['profile_picture']) ?>" alt="<?= $host['full_name'] ?>">
                    <div class="host-info">
                        <h3><?= $host['full_name'] ?></h3>
                        <p><?= $host['languages'] ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.category-tab');
    const hostCards = document.querySelectorAll('.host-card');
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    let currentTab = '<?= $initialTab ?>';

    tabs.forEach(tab => {
        if (!tab.classList.contains('dropdown-toggle')) {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                currentTab = tab.dataset.tab;
                document.querySelectorAll('.filter-group').forEach(g => g.classList.remove('active'));
                const target = document.getElementById('filter-' + currentTab);
                if (target) target.classList.add('active');
                applyFilters();
            });
        }
    });

    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', (e) => {
            const content = toggle.nextElementSibling;
            content.classList.toggle('show');
            e.stopPropagation();
        });
    });

    document.addEventListener('click', () => {
        document.querySelectorAll('.dropdown-content').forEach(c => c.classList.remove('show'));
    });

    function applyFilters() {
        hostCards.forEach(card => {
            const cats = card.dataset.categories.toLowerCase();
            card.style.display = cats.includes(currentTab.toLowerCase()) ? 'block' : 'none';
        });
    }
    applyFilters();
});
</script>

<?php include 'includes/footer.php'; ?>
