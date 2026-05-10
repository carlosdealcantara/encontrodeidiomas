<?php
require_once 'config.php';
require_once 'includes/components.php';

$current_page   = 'equipe.php';
$initialTab     = $_GET['tab'] ?? 'online';
$projeto        = $_GET['projeto'] ?? '';

// SEO Dinâmico
if ($initialTab === 'iniciativas') {
    $title = (!empty($projeto) ? htmlspecialchars($projeto) . ' | ' : '') . t('team.tabs.iniciativas') . ' | ' . SITE_NAME;
    $og_description = "Conheça nossas iniciativas especiais. Projetos criados pela comunidade para você.";
} else {
    $title = t('team.title');
    $og_description = t('team.meta_description');
}
$canonical = SITE_URL . langUrl('equipe.php');

$hosts     = getHosts();
$languages = getLanguages();

$initialLanguage = $_GET['idioma']  ?? 'all';
$initialRegion   = $_GET['regiao']  ?? 'all';
$initialRole     = $_GET['papel']   ?? 'all';

ob_start();
?>
    /* Estilos Simplificados para Estabilidade */
    .page-hero {
        width: 100%;
        height: 45vh !important;
        background: linear-gradient(135deg, rgba(0, 38, 84, 0.4) 0%, #ffffff 50%, rgba(227, 29, 28, 0.4) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px 0;
    }
    .hero-header { text-align: center; max-width: 600px; margin: 0 auto; }
    .hero-image { width: 220px; height: auto; }
<?php
$page_styles = ob_get_clean();

include 'includes/header.php';
?>

<main>
    <section class="page-hero">
        <header class="hero-header">
            <img src="/assets/images/hero_team.png" alt="Equipe" class="hero-image">
            <h1><?= t('team.hero_title') ?></h1>
            <p><?= t('team.hero_subtitle') ?></p>
        </header>
    </section>

    <div class="container" style="padding: 60px 0; text-align: center;">
        <p>A página de equipe está sendo restaurada para garantir estabilidade.</p>
        <a href="/" style="color: var(--accent-red);">Voltar para Home</a>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
