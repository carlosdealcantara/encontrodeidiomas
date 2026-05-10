<?php
// Script de limpeza para encontrar arquivos órfãos (que não estão no Git)

$trackedFiles = [
    ".env.example",
    ".gitignore",
    ".htaccess",
    "AGENTS.md",
    "MASTER_PLAN.md",
    "__pycache__/app.cpython-311.pyc",
    "admin/check_db.php",
    "admin/fix_icons.php",
    "admin/fix_prio.php",
    "admin/host_form.php",
    "admin/hosts.php",
    "admin/includes/sidebar.php",
    "admin/index.php",
    "admin/languages.php",
    "admin/login.php",
    "admin/logout.php",
    "admin/meeting_form.php",
    "admin/meetings.php",
    "admin/presencial.php",
    "admin/presencial_form.php",
    "admin/settings.php",
    "admin/update_link_order.php",
    "admin/useful_links.php",
    "ajax/get_events_by_language.php",
    "assets/css/home.css",
    "assets/css/online.css",
    "assets/images/Alyce.jpg",
    "assets/images/Ane.jpg",
    "assets/images/Caique.jpg",
    "assets/images/Carlos.jpg",
    "assets/images/CarlosDaniel.jpg",
    "assets/images/Daniel.jpg",
    "assets/images/Grupos.png",
    "assets/images/HostSemFoto.png",
    "assets/images/IMG_20250408_174649_714.jpg",
    "assets/images/IMG_20250408_175458_304.jpg",
    "assets/images/Isaac.jpg",
    "assets/images/Jackelynne.jpg",
    "assets/images/MaisIdiomasCidades.png",
    "assets/images/Michele.jpg",
    "assets/images/Paula.jpg",
    "assets/images/Rhadila.jpg",
    "assets/images/Rosana.jpg",
    "assets/images/Tarsis.jpg",
    "assets/images/Wellington.jpg",
    "assets/images/encontrodeidiomas-20250407-0001.jpg",
    "assets/images/encontrodeidiomas-20250407-0002.jpg",
    "assets/images/encontrodeidiomas-20250408-0002.jpg",
    "assets/images/encontrodeidiomas-20250408-0013.jpg",
    "assets/images/favicon.png",
    "assets/images/hero_contact.png",
    "assets/images/hero_links_v2.png",
    "assets/images/hero_team.png",
    "assets/images/instagram_social.png",
    "assets/images/logo.png",
    "assets/images/mentoria.jpg",
    "assets/images/og_image.png",
    "assets/images/og_preview_elegant.jpg",
    "assets/images/replay.png",
    "assets/js/online.js",
    "config.php",
    "contato.php",
    "equipe.php",
    "includes/components.php",
    "includes/db_online.php",
    "includes/footer.php",
    "includes/header.php",
    "includes/presencial_card.php",
    "index.php",
    "lang/audit.php",
    "lang/en.json",
    "lang/index.php",
    "lang/pt.json",
    "links.php",
    "online.php",
    "presencial.php",
    "requirements.txt",
    "robots.txt",
    "schema.sql",
    "scratch/inject_presencial_nav.php",
    "sitemap.xml",
    "static/logo.svg",
    "static/styles.css",
    "templates/index.html"
];

$allowedExtras = [
    ".env", 
    "scanner_limpeza.php", 
    "error_log"
];

$baseDir = __DIR__;
$orphans = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile()) {
        $path = $file->getRealPath();
        
        // Pega o caminho relativo ignorando o separador inicial
        $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $path);
        
        // Normaliza as barras para comparar com o array
        $relativePath = str_replace('\\', '/', $relativePath);

        // Ignora a pasta .git inteira
        if (strpos($relativePath, '.git/') === 0) continue;
        
        if (!in_array($relativePath, $trackedFiles) && !in_array($relativePath, $allowedExtras)) {
            $orphans[] = $relativePath;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanner de Limpeza de Arquivos</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8f9fa; color: #212529; margin: 0; padding: 2rem; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { color: #dc3545; border-bottom: 2px solid #dc3545; padding-bottom: 0.5rem; }
        .alert { padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem; background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        ul { list-style-type: none; padding: 0; }
        li { background: #f1f3f5; margin-bottom: 0.5rem; padding: 0.75rem; border-radius: 4px; border-left: 4px solid #dc3545; display: flex; justify-content: space-between; align-items: center; font-family: monospace; }
        .btn-action { display: inline-block; background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; margin-top: 20px; }
        .btn-action:hover { background: #0056b3; }
        .instruction { font-size: 0.9em; color: #6c757d; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Scanner de Arquivos Órfãos</h1>
        <p>Este script compara os arquivos que estão fisicamente na sua hospedagem com a lista de arquivos registrados no repositório Git (Branch Dev).</p>
        
        <div class="alert">
            <strong>⚠️ ATENÇÃO:</strong> Revise com cuidado os arquivos antes de deletá-los. Certifique-se de que nenhum upload essencial de usuários (caso exista fora do controle do git) ou configurações sigilosas sejam perdidas.
        </div>

        <?php if(empty($orphans)): ?>
            <div class="alert alert-success">
                <strong>Parabéns!</strong> Sua hospedagem está completamente sincronizada. Nenhum arquivo órfão encontrado.
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                Foram encontrados <strong><?= count($orphans) ?></strong> arquivos no servidor que não pertencem ao repositório Git atual.
            </div>

            <h2>Arquivos Candidatos à Exclusão:</h2>
            <p class="instruction">Você pode abrir o Gerenciador de Arquivos da Hostinger e procurar por esses arquivos para apagá-los com segurança.</p>
            <ul>
                <?php foreach($orphans as $orphan): ?>
                    <li>
                        <?= htmlspecialchars($orphan) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        
        <div style="margin-top: 30px; font-size: 0.8rem; color: #aaa; text-align: center;">
            Para segurança, após a limpeza, exclua este script "scanner_limpeza.php" ou ignore este aviso caso vá deixá-lo no repositório.
        </div>
    </div>
</body>
</html>
