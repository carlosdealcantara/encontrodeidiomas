<?php
/**
 * Script de instalação: INSERT do card "Comunidade Global" na tabela useful_links
 * INSTRUÇÕES: Coloque este arquivo no servidor, acesse via browser UMA VEZ, e depois apague.
 * SEGURANÇA: Arquivo protegido por token.
 */
define('INSTALL_TOKEN', 'eiglobal2026');

if (($_GET['token'] ?? '') !== INSTALL_TOKEN) {
    http_response_code(403);
    die('Acesso negado. Use ?token=eiglobal2026');
}

require_once __DIR__ . '/config.php';

try {
    $conn = connectDB();

    // Verifica se já existe
    $check = $conn->query("SELECT id FROM useful_links WHERE url = 'https://chat.whatsapp.com/CorMfQfDhZj6X4tIofl67T' LIMIT 1")->fetch();
    if ($check) {
        echo "<p style='color:orange'>⚠️ Link já existe no banco (ID: {$check['id']}). Nada foi inserido.</p>";
        echo "<p><a href='https://dev.viaEi.com/links.php'>Ver links.php no dev</a></p>";
        die();
    }

    // Pega o maior order_index atual
    $max = $conn->query('SELECT MAX(order_index) as m FROM useful_links')->fetch();
    $nextOrder = ((int)($max['m'] ?? 0)) + 10;

    $stmt = $conn->prepare("
        INSERT INTO useful_links 
            (title, title_en, subtitle, subtitle_en, url, icon, badge, badge_en, layout_type, order_index, active)
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");

    $result = $stmt->execute([
        'Comunidade Global 🌐',
        'Global Community 🌐',
        'Use o inglês como base e pratique seu idioma-alvo com o mundo',
        'Use English as a base — practice your target language worldwide',
        'https://chat.whatsapp.com/CorMfQfDhZj6X4tIofl67T',
        'fas fa-globe',
        'NOVO 🌐',
        'NEW 🌐',
        'standard',
        $nextOrder
    ]);

    if ($result) {
        $id = $conn->lastInsertId();
        echo "<h2 style='color:green'>✅ INSERT OK!</h2>";
        echo "<p>ID: <strong>{$id}</strong> | order_index: <strong>{$nextOrder}</strong></p>";
        echo "<p><a href='https://dev.viaEi.com/links.php'>Ver links.php no dev →</a></p>";
        echo "<p><strong>APAGUE este arquivo do servidor após confirmar.</strong></p>";
    } else {
        echo "<h2 style='color:red'>❌ Falha no INSERT</h2>";
    }

} catch (Exception $e) {
    echo "<h2 style='color:red'>❌ EXCEPTION</h2><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
