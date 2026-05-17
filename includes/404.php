<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/components.php';

http_response_code(404);

$title = t('404.title') ?? (CURRENT_LANG === 'en' ? 'Page Not Found' : 'Página Não Encontrada');
$og_description = t('404.description') ?? (CURRENT_LANG === 'en' ? 'The page you are looking for does not exist or has been moved.' : 'A página que você procura não existe ou foi movida.');

$extra_head = '
<style>
.not-found-section {
    padding: 120px 20px 80px;
    text-align: center;
    min-height: 70vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: white;
}
.not-found-code {
    font-size: 8rem;
    font-weight: 900;
    line-height: 1;
    background: linear-gradient(135deg, #e31d1c 0%, #ff5b5a 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 20px;
}
.not-found-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 20px;
}
.not-found-text {
    font-size: 1.1rem;
    color: #94a3b8;
    max-width: 600px;
    margin-bottom: 40px;
}
.not-found-buttons {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
}
.btn-primary {
    background: #e31d1c;
    color: white;
    padding: 14px 32px;
    border-radius: 16px;
    text-decoration: none;
    font-weight: 700;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(227, 29, 28, 0.3);
}
.btn-secondary {
    background: rgba(255,255,255,0.1);
    color: white;
    padding: 14px 32px;
    border-radius: 16px;
    text-decoration: none;
    font-weight: 700;
    transition: all 0.3s;
    border: 1px solid rgba(255,255,255,0.2);
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
.btn-secondary:hover {
    background: rgba(255,255,255,0.2);
    transform: translateY(-2px);
}
</style>
';

include __DIR__ . '/../includes/header.php';
?>

<main>
    <section class="not-found-section">
        <div class="not-found-code">404</div>
        <h1 class="not-found-title"><?= CURRENT_LANG === 'en' ? 'Oops! Page Not Found' : 'Ops! Página Não Encontrada' ?></h1>
        <p class="not-found-text">
            <?= CURRENT_LANG === 'en' 
                ? 'We looked everywhere, but we couldn\'t find the page or language you were looking for. Let\'s get you back on track!' 
                : 'Procuramos por toda parte, mas não encontramos a página ou idioma que você tentou acessar. Vamos te ajudar a voltar para o caminho certo!' ?>
        </p>
        <div class="not-found-buttons">
            <a href="<?= langUrl('index.php') ?>" class="btn-primary"><i class="fas fa-home"></i> <?= CURRENT_LANG === 'en' ? 'Back to Home' : 'Voltar para o Início' ?></a>
            <a href="<?= langUrl('online.php') ?>" class="btn-secondary"><i class="fas fa-globe"></i> <?= CURRENT_LANG === 'en' ? 'Explore Online Meetings' : 'Ver Encontros Online' ?></a>
        </div>
    </section>
</main>

<?php
include __DIR__ . '/../includes/footer.php';
?>
