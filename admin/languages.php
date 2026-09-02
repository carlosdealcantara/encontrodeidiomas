<?php
session_start();
require_once '../config.php';

// Proteção da página
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();// Auto-Migração: Tenta criar colunas (silencioso se já existirem)
try { $conn->exec("ALTER TABLE languages ADD COLUMN whatsapp_link VARCHAR(255) AFTER active"); } catch (PDOException $e) {}
try { $conn->exec("ALTER TABLE languages ADD COLUMN instagram_link VARCHAR(255) AFTER whatsapp_link"); } catch (PDOException $e) {}
try { $conn->exec("ALTER TABLE languages ADD COLUMN slug_pt VARCHAR(50) DEFAULT NULL AFTER name_en"); } catch (PDOException $e) {}
try { $conn->exec("ALTER TABLE languages ADD COLUMN slug_en VARCHAR(50) DEFAULT NULL AFTER slug_pt"); } catch (PDOException $e) {}
try { $conn->exec("ALTER TABLE languages ADD UNIQUE INDEX idx_slug_pt (slug_pt)"); } catch (PDOException $e) {}
try { $conn->exec("ALTER TABLE languages ADD UNIQUE INDEX idx_slug_en (slug_en)"); } catch (PDOException $e) {}



// Limpeza de barras finais
try { $conn->exec("UPDATE languages SET instagram_link = TRIM(TRAILING '/' FROM instagram_link), whatsapp_link = TRIM(TRAILING '/' FROM whatsapp_link)"); } catch (PDOException $e) {}

function slugify(string $text): ?string {
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = preg_replace('/[^a-z0-9-]/', '', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : null;
}

$RESERVED_SLUGS = [
    'admin', 'ajax', 'assets', 'includes', 'lang', 'static', 'templates',
    'online', 'presencial', 'equipe', 'links', 'contato', 'index',
    'en', 'team', 'in-person', 'contact',
    'scratch', 'check', 'config', 'robots', 'sitemap', 'login', 'logout', 'settings', 'hosts', 'meetings'
];

// Lógica de Salvar em Lote (Bulk Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_save'])) {
    try {
        $conn->beginTransaction();
        foreach ($_POST['langs'] as $id => $data) {
            $slug_pt = !empty($data['slug_pt']) ? slugify($data['slug_pt']) : null;
            $slug_en = !empty($data['slug_en']) ? slugify($data['slug_en']) : null;

            if ($slug_pt && in_array($slug_pt, $RESERVED_SLUGS)) {
                throw new Exception("O link curto '$slug_pt' é uma palavra reservada do sistema.");
            }
            if ($slug_en && in_array($slug_en, $RESERVED_SLUGS)) {
                throw new Exception("O link curto '$slug_en' é uma palavra reservada do sistema.");
            }

            $stmt = $conn->prepare("UPDATE languages SET name = ?, name_en = ?, slug_pt = ?, slug_en = ?, flag_code = ?, flag_emoji = ?, whatsapp_link = ?, instagram_link = ?, greeting = ?, welcome_native = ?, active = ? WHERE id = ?");
            $stmt->execute([
                $data['name'], 
                $data['name_en'] ?? '',
                $slug_pt,
                $slug_en,
                $data['flag_code'], 
                $data['flag_emoji'], 
                trim($data['whatsapp_link'], '/ '), 
                trim($data['instagram_link'], '/ '), 
                $data['greeting'] ?? 'Welcome!',
                $data['welcome_native'] ?? '',
                isset($data['active']) ? 1 : 0,
                $id
            ]);
        }
        $conn->commit();
        $msg = "Todos os idiomas foram atualizados com sucesso!";
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $error = "Erro ao salvar: " . $e->getMessage();
    }
}

// Lógica de Adicionar Novo Idioma
if (isset($_POST['add_new'])) {
    try {
        $stmt = $conn->prepare("INSERT INTO languages (name, active) VALUES ('Novo Idioma', 0)");
        $stmt->execute();
        $msg = "Novo idioma criado! Edite o nome e os detalhes na lista abaixo.";
    } catch (Exception $e) {
        $error = "Erro ao adicionar: " . $e->getMessage();
    }
}

// Busca idiomas
$languages = $conn->query("SELECT * FROM languages ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edição em Lote | Idiomas</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-bg: #0f172a;
            --sidebar-bg: #1e293b;
            --accent-red: #e31d1c;
            --text-main: #f1f5f9;
            --text-dim: #94a3b8;
            --card-bg: #1e293b;
            --input-bg: #0f172a;
            --success: #10b981;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }

        .sidebar { width: 280px; background: var(--sidebar-bg); padding: 30px; border-right: 1px solid rgba(255,255,255,0.05); }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 14px 18px; color: var(--text-dim); text-decoration: none; border-radius: 12px; margin-bottom: 8px; transition: 0.3s; }
        .nav-item.active { background: var(--accent-red); color: white; }

        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end; }
        
        .bulk-card { background: var(--card-bg); border-radius: 24px; padding: 30px; border: 1px solid rgba(255,255,255,0.05); }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; color: var(--text-dim); font-size: 0.8rem; text-transform: uppercase; border-bottom: 1px solid rgba(255,255,255,0.05); }
        td { padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); }
        
        input { background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 8px 12px; color: white; width: 100%; transition: 0.3s; }
        input:focus { border-color: var(--accent-red); outline: none; }
        
        .btn-save { background: var(--accent-red); color: white; border: none; padding: 15px 40px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: 0.3s; font-size: 1rem; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(227, 29, 28, 0.3); }

        .alert { padding: 15px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--success); border-radius: 12px; margin-bottom: 20px; }

        /* Estilo do Toggle */
        .switch { display: inline-flex; align-items: center; cursor: pointer; user-select: none; }
        .switch input { display: none; }
        .slider { 
            width: 40px; height: 20px; background: #334155; border-radius: 20px; position: relative; transition: .3s; flex-shrink: 0;
        }
        .slider:before { 
            position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background: white; transition: .3s; border-radius: 50%; 
        }
        input:checked + .slider { background: var(--success); }
        input:checked + .slider:before { transform: translateX(20px); }
    </style>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'%3E%3Crect width='512' height='512' rx='128' fill='%23e31d1c'/%3E%3Ctext x='256' y='256' dy='.35em' font-family='system-ui, -apple-system, sans-serif' font-weight='900' font-size='300' fill='white' text-anchor='middle'%3EEi%3C/text%3E%3C/svg%3E">
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <form method="POST">
            <header class="header">
                <div>
                    <h2 style="font-size: 2rem; font-weight: 800;">Gestão de Idiomas</h2>
                    <p style="color: var(--text-dim);">Edite ou adicione novos idiomas à plataforma.</p>
                </div>
                <div style="display: flex; gap: 15px;">
                    <button type="submit" name="add_new" class="btn-save" style="background: var(--sidebar-bg); border: 1px solid var(--accent-red); color: var(--accent-red);">
                        <i class="fas fa-plus"></i> Novo Idioma
                    </button>
                    <button type="submit" name="bulk_save" class="btn-save">Salvar Tudo</button>
                </div>
            </header>

            <?php if (isset($msg)): ?> <div class="alert"><i class="fas fa-check-circle"></i> <?= $msg ?></div> <?php endif; ?>
            <?php if (isset($error)): ?> <div class="alert" style="background: rgba(227, 29, 28, 0.1); border: 1px solid rgba(227, 29, 28, 0.2); color: var(--accent-red);"><i class="fas fa-exclamation-triangle"></i> <?= $error ?></div> <?php endif; ?>

            <div class="bulk-card">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 13%;">Idioma (PT)</th>
                            <th style="width: 13%;">Idioma (EN)</th>
                            <th style="width: 13%;">Link Curto (PT/EN)</th>
                            <th style="width: 9%;">Bandeira / Emoji</th>
                            <th>Link WhatsApp</th>
                            <th>Link Instagram</th>
                            <th>Saudação (EN)</th>
                            <th>Boas-vindas Nativas</th>
                            <th style="width: 50px;">Ativo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($languages as $l): ?>
                        <tr>
                            <td><input type="text" name="langs[<?= $l['id'] ?>][name]" value="<?= htmlspecialchars($l['name']) ?>" placeholder="Português"></td>
                            <td><input type="text" name="langs[<?= $l['id'] ?>][name_en]" value="<?= htmlspecialchars($l['name_en'] ?? '') ?>" placeholder="English"></td>
                            <td>
                                <div style="display:flex; gap:5px;">
                                    <input type="text" name="langs[<?= $l['id'] ?>][slug_pt]" value="<?= htmlspecialchars($l['slug_pt'] ?? '') ?>" placeholder="ex: ingles">
                                    <input type="text" name="langs[<?= $l['id'] ?>][slug_en]" value="<?= htmlspecialchars($l['slug_en'] ?? '') ?>" placeholder="ex: english">
                                </div>
                            </td>
                            <td>
                                <div style="display:flex; gap:5px;">
                                    <input type="text" name="langs[<?= $l['id'] ?>][flag_code]" value="<?= htmlspecialchars($l['flag_code'] ?? '') ?>" placeholder="us">
                                    <input type="text" name="langs[<?= $l['id'] ?>][flag_emoji]" value="<?= htmlspecialchars($l['flag_emoji'] ?? '') ?>" placeholder="🇺🇸">
                                </div>
                            </td>
                            <td><input type="url" name="langs[<?= $l['id'] ?>][whatsapp_link]" value="<?= htmlspecialchars($l['whatsapp_link'] ?? '') ?>" placeholder="https://chat.whatsapp.com/..."></td>
                            <td><input type="url" name="langs[<?= $l['id'] ?>][instagram_link]" value="<?= htmlspecialchars($l['instagram_link'] ?? '') ?>" placeholder="https://instagram.com/..."></td>
                            <td><input type="text" name="langs[<?= $l['id'] ?>][greeting]" value="<?= htmlspecialchars($l['greeting'] ?? 'Welcome!') ?>" placeholder="ex: ¡Bienvenidos!"></td>
                            <td><input type="text" name="langs[<?= $l['id'] ?>][welcome_native]" value="<?= htmlspecialchars($l['welcome_native'] ?? '') ?>" placeholder="ex: 欢迎!" title="Boas-vindas no idioma-alvo para mensagens globais ({BOAS_VINDAS_NATIVAS})"></td>
                            <td style="text-align:center;">
                                <label class="switch">
                                    <input type="checkbox" name="langs[<?= $l['id'] ?>][active]" <?= $l['active'] ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 30px; text-align: right;">
                <button type="submit" name="bulk_save" class="btn-save">Salvar Alterações</button>
            </div>
        </form>
    </main>
</body>
</html>
