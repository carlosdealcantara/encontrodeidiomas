<?php
/**
 * SCRIPT DE MIGRAÇÃO — Tabela `slugs` (Executar uma vez, remover depois)
 * Acesse: dev.encontrodeidiomas.com.br/admin/_migrate_slugs.php
 */
require_once __DIR__ . '/../config.php';

// Verificação de segurança: bloquear em produção
if ($_SERVER['HTTP_HOST'] === 'encontrodeidiomas.com.br') {
    die('Proibido em produção.');
}

$conn = connectDB();
$results = [];

function run(PDO $conn, string $label, string $sql, array $params = []): void {
    global $results;
    try {
        if (empty($params)) {
            $conn->exec($sql);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }
        $results[] = "✅ $label";
    } catch (PDOException $e) {
        $results[] = "⚠️  $label — " . $e->getMessage();
    }
}

// ─── 1. Criar tabela slugs ───────────────────────────────────────────────────
run($conn, "CREATE TABLE slugs", "
    CREATE TABLE IF NOT EXISTS slugs (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        slug                VARCHAR(120) NOT NULL,
        lang                ENUM('pt','en','*') NOT NULL DEFAULT '*',
        type                ENUM('language','city','state','day','anchor','page') NOT NULL,
        target_page         VARCHAR(60)  NOT NULL,
        target_param_key    VARCHAR(50)  DEFAULT NULL,
        target_param_value  VARCHAR(200) DEFAULT NULL,
        redirect_to         VARCHAR(300) DEFAULT NULL,
        UNIQUE KEY uq_slug_lang (slug, lang)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ─── 2. Popular slugs de IDIOMA a partir de languages.slug_pt / slug_en ───────
$langs = $conn->query("SELECT id, slug_pt, slug_en FROM languages WHERE active = 1")->fetchAll();
foreach ($langs as $l) {
    if (!empty($l['slug_pt'])) {
        run($conn, "Idioma slug_pt={$l['slug_pt']}", "
            INSERT INTO slugs (slug, lang, type, target_page, target_param_key, target_param_value)
            VALUES (?, 'pt', 'language', 'online.php', 'idioma', ?)
            ON DUPLICATE KEY UPDATE target_param_value = VALUES(target_param_value)
        ", [$l['slug_pt'], (string)$l['id']]);
    }
    if (!empty($l['slug_en'])) {
        run($conn, "Idioma slug_en={$l['slug_en']}", "
            INSERT INTO slugs (slug, lang, type, target_page, target_param_key, target_param_value)
            VALUES (?, 'en', 'language', 'online.php', 'idioma', ?)
            ON DUPLICATE KEY UPDATE target_param_value = VALUES(target_param_value)
        ", [$l['slug_en'], (string)$l['id']]);
    }
}

// ─── 3. Popular slugs de CIDADE ──────────────────────────────────────────────
$cities = $conn->query("SELECT DISTINCT city FROM in_person_events WHERE active = 1 AND city IS NOT NULL")->fetchAll();
foreach ($cities as $c) {
    $cityName = $c['city'];
    // Gerar slug: lowercase, sem acentos (transliterate), apenas a-z0-9 e hífen
    $slug = mb_strtolower($cityName, 'UTF-8');
    $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    if (empty($slug)) continue;
    run($conn, "Cidade slug=$slug (city=$cityName)", "
        INSERT INTO slugs (slug, lang, type, target_page, target_param_key, target_param_value)
        VALUES (?, '*', 'city', 'presencial.php', 'cidade', ?)
        ON DUPLICATE KEY UPDATE target_param_value = VALUES(target_param_value)
    ", [$slug, $cityName]);
}

// ─── 4. Popular slugs de ESTADO ──────────────────────────────────────────────
$states = $conn->query("SELECT DISTINCT state FROM in_person_events WHERE active = 1 AND state IS NOT NULL")->fetchAll();
foreach ($states as $s) {
    $stateSig = $s['state'];
    $slug = strtolower(trim($stateSig));
    if (empty($slug)) continue;
    run($conn, "Estado slug=$slug (state=$stateSig)", "
        INSERT INTO slugs (slug, lang, type, target_page, target_param_key, target_param_value)
        VALUES (?, '*', 'state', 'presencial.php', 'estado', ?)
        ON DUPLICATE KEY UPDATE target_param_value = VALUES(target_param_value)
    ", [$slug, $stateSig]);
}

// ─── 5. Popular slugs de DIAS DA SEMANA ──────────────────────────────────────
$dayData = [
    ['segunda','pt','1'], ['monday','en','1'],
    ['terca','pt','2'],   ['tuesday','en','2'],
    ['quarta','pt','3'],  ['wednesday','en','3'],
    ['quinta','pt','4'],  ['thursday','en','4'],
    ['sexta','pt','5'],   ['friday','en','5'],
    ['sabado','pt','6'],  ['saturday','en','6'],
    ['domingo','pt','7'], ['sunday','en','7'],
];
foreach ($dayData as [$slug, $lang, $dia]) {
    run($conn, "Dia slug=$slug lang=$lang dia=$dia", "
        INSERT INTO slugs (slug, lang, type, target_page, target_param_key, target_param_value)
        VALUES (?, ?, 'day', 'online.php', 'dia', ?)
        ON DUPLICATE KEY UPDATE target_param_value = VALUES(target_param_value)
    ", [$slug, $lang, $dia]);
}

// ─── 6. Popular ÂNCORAS ──────────────────────────────────────────────────────
run($conn, "Âncora /sejahost", "
    INSERT INTO slugs (slug, lang, type, target_page, redirect_to)
    VALUES ('sejahost', 'pt', 'anchor', 'equipe.php', '/equipe#seja-host')
    ON DUPLICATE KEY UPDATE redirect_to = VALUES(redirect_to)
");
run($conn, "Âncora /beahost", "
    INSERT INTO slugs (slug, lang, type, target_page, redirect_to)
    VALUES ('beahost', 'en', 'anchor', 'equipe.php', '/en/team#seja-host')
    ON DUPLICATE KEY UPDATE redirect_to = VALUES(redirect_to)
");

// ─── Resultado ────────────────────────────────────────────────────────────────
$total = $conn->query("SELECT COUNT(*) FROM slugs")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt">
<head><meta charset="UTF-8"><title>Migração Slugs</title>
<style>body{font-family:monospace;padding:40px;background:#0f172a;color:#e2e8f0;}
h1{color:#38bdf8;} .ok{color:#4ade80;} .warn{color:#facc15;}
.box{background:#1e293b;padding:20px;border-radius:10px;margin-top:20px;line-height:2;}
</style></head>
<body>
<h1>🛠️ Migração: Tabela <code>slugs</code></h1>
<div class="box">
<?php foreach ($results as $r): ?>
    <div class="<?= str_starts_with($r,'✅') ? 'ok' : 'warn' ?>"><?= htmlspecialchars($r) ?></div>
<?php endforeach; ?>
</div>
<p style="margin-top:30px;color:#94a3b8;">Total de slugs na tabela: <strong style="color:#38bdf8"><?= $total ?></strong></p>
<p style="color:#ef4444;font-weight:bold;margin-top:20px;">⚠️ Remova este arquivo após confirmar que a migração foi concluída com sucesso!</p>
</body>
</html>
<?php
