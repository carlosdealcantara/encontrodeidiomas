<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();
$msg = null;
$error = null;

$active_tab = $_POST['tab'] ?? $_GET['tab'] ?? 'fila';

// --- LOGIC: CONFIG ---
try { $conn->exec("ALTER TABLE languages ADD COLUMN odysee_auto_enabled TINYINT(1) DEFAULT 0"); } catch (PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_save'])) {
    try {
        $conn->beginTransaction();
        foreach ($_POST['langs'] as $id => $data) {
            $auto_enabled = isset($data['auto']) ? 1 : 0;
            $stmt = $conn->prepare("UPDATE languages SET odysee_auth_token = ?, odysee_channel_name = ?, odysee_auto_enabled = ? WHERE id = ?");
            $stmt->execute([
                trim($data['token']),
                trim($data['channel']),
                $auto_enabled,
                $id
            ]);
        }
        $conn->commit();
        $msg = "Configurações do Odysee salvas com sucesso!";
        $active_tab = 'config';
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $error = "Erro ao salvar: " . $e->getMessage();
        $active_tab = 'config';
    }
}
$languages = $conn->query("SELECT id, name, odysee_auth_token, odysee_channel_name, odysee_auto_enabled FROM languages ORDER BY name ASC")->fetchAll();

// --- LOGIC: WHATSAPP TEMPLATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    try {
        $template_texto = trim($_POST['template_texto']);
        
        $stmt = $conn->prepare("SELECT id FROM settings WHERE setting_key = 'odysee_whatsapp_template'");
        $stmt->execute();
        if ($stmt->fetch()) {
            $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'odysee_whatsapp_template'");
            $stmt->execute([$template_texto]);
        } else {
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, label, category, type) VALUES ('odysee_whatsapp_template', ?, 'Template do WhatsApp', 'odysee', 'text')");
            $stmt->execute([$template_texto]);
        }
        $msg = "Template salvo com sucesso!";
        $active_tab = 'template';
    } catch (Exception $e) {
        $error = "Erro ao salvar template: " . $e->getMessage();
        $active_tab = 'template';
    }
}

$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'odysee_whatsapp_template'");
$stmt->execute();
$row = $stmt->fetch();
$current_template = $row ? $row['setting_value'] : "🎬 *Replay:* {bandeira} {titulo}\\n\\n🔗 {link}";

// --- LOGIC: FILA ---
if (isset($_GET['retry']) && is_numeric($_GET['retry'])) {
    $id = (int)$_GET['retry'];
    $stmt = $conn->prepare("UPDATE odysee_publish_queue SET status = 'pending', retry_count = 0 WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: odysee.php?tab=fila&msg=' . urlencode('Retrying'));
    exit;
}

if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $id = (int)$_GET['cancel'];
    $stmt = $conn->prepare("UPDATE odysee_publish_queue SET status = 'error', error_message = 'Cancelled by Admin' WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: odysee.php?tab=fila&msg=' . urlencode('Cancelled'));
    exit;
}

$stmt = $conn->query("
    SELECT q.*, l.name as language_name, l.odysee_auto_enabled, l.odysee_auth_token
    FROM odysee_publish_queue q
    LEFT JOIN languages l ON q.language_id = l.id
    ORDER BY q.id DESC LIMIT 100
");
$queue = $stmt->fetchAll();

// --- LOGIC: DIAGNOSTICS ---
// Prioridade 1: tarefa ativa (status=processing). Prioridade 2: a mais recente com screenshot
$screenshots = [];
$active = $conn->query("
    SELECT q.id, q.titulo_final, q.status, q.last_screenshot, q.last_screenshot_time, l.name as language_name
    FROM odysee_publish_queue q
    LEFT JOIN languages l ON q.language_id = l.id
    WHERE q.status = 'processing' AND q.last_screenshot IS NOT NULL
    ORDER BY q.last_screenshot_time DESC LIMIT 1
")->fetchAll();

if (!empty($active)) {
    $screenshots = $active;
} else {
    $screenshots = $conn->query("
        SELECT q.id, q.titulo_final, q.status, q.last_screenshot, q.last_screenshot_time, l.name as language_name
        FROM odysee_publish_queue q
        LEFT JOIN languages l ON q.language_id = l.id
        WHERE q.last_screenshot IS NOT NULL
        ORDER BY q.last_screenshot_time DESC LIMIT 1
    ")->fetchAll();
}

if (isset($_GET['msg']) && !$msg) {
    $msg = $_GET['msg'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hub Odysee | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-bg: #0f172a;
            --sidebar-bg: #1e293b;
            --accent-red: #e31d1c;
            --accent-blue: #38bdf8;
            --text-main: #f1f5f9;
            --text-dim: #94a3b8;
            --white: #ffffff;
            --card-bg: #1e293b;
            --input-bg: #0f172a;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        
        .alert { padding: 15px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--success); border-radius: 12px; margin-bottom: 20px; }
        .alert.error { background: rgba(227, 29, 28, 0.1); border-color: rgba(227, 29, 28, 0.2); color: var(--accent-red); }
        
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; }
        
        /* Main Tabs */
        .main-tabs-nav { display: flex; gap: 15px; margin-bottom: 30px; border-bottom: 2px solid rgba(255,255,255,0.05); flex-wrap: wrap; }
        .main-tab-btn { padding: 15px 30px; border: none; background: transparent; color: var(--text-dim); font-size: 1.1rem; font-weight: 600; cursor: pointer; position: relative; transition: 0.3s; white-space: nowrap; }
        .main-tab-btn:hover { color: var(--white); }
        .main-tab-btn.active { color: var(--accent-red); }
        .main-tab-btn.active::after { content: ''; position: absolute; bottom: -2px; left: 0; width: 100%; height: 2px; background: var(--accent-red); }
        
        .main-tab-content { display: none; }
        .main-tab-content.active { display: block; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        /* Queue Table Styles */
        .data-table { width: 100%; border-collapse: collapse; background: var(--card-bg); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .data-table th, .data-table td { padding: 15px 20px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .data-table th { background: rgba(0,0,0,0.2); font-weight: 600; color: var(--text-dim); text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; }
        .data-table tr:hover { background: rgba(255,255,255,0.02); }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; }
        .badge-pending { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
        .badge-processing { background: rgba(56, 189, 248, 0.2); color: #38bdf8; }
        .badge-done { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .badge-error { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .badge-waiting_host { background: rgba(148, 163, 184, 0.2); color: #94a3b8; }
        
        .btn-sm { padding: 6px 12px; background: rgba(255,255,255,0.1); border-radius: 6px; color: white; text-decoration: none; font-size: 0.85rem; transition: 0.2s; border: none; cursor: pointer; }
        .btn-sm:hover { background: var(--accent-blue); }
        .btn-danger { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .btn-danger:hover { background: #ef4444; color: white; }

        .status-DONE { background: #28a745; color: white; }
        .status-PENDING { background: #ffc107; color: black; }
        .status-WAITING_TITLE { background: #6c757d; color: white; }
        .status-DISABLED { background: #475569; color: #cbd5e1; border: 1px solid #334155; }
        .status-NO_TOKEN { background: #b91c1c; color: white; border: 1px dashed #ef4444; }
        .actions-col { display: flex; gap: 5px; flex-wrap: nowrap; align-items: center; }

        /* Config Table Styles */
        .bulk-card { background: var(--card-bg); border-radius: 24px; padding: 30px; border: 1px solid rgba(255,255,255,0.05); }
        .config-table { width: 100%; border-collapse: collapse; }
        .config-table th { text-align: left; padding: 15px; color: var(--text-dim); font-size: 0.8rem; text-transform: uppercase; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .config-table td { padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); }
        
        .config-table input[type="text"], .config-table input[type="password"] { background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 10px 15px; color: white; width: 100%; transition: 0.3s; font-size: 0.95rem; }
        .config-table input:focus { border-color: var(--accent-red); outline: none; }
        
        .btn-save { background: var(--accent-red); color: white; border: none; padding: 15px 40px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: 0.3s; font-size: 1rem; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(227, 29, 28, 0.3); }

        .switch { display: inline-flex; align-items: center; cursor: pointer; user-select: none; }
        .switch input { display: none; }
        .slider { width: 44px; height: 24px; background: #334155; border-radius: 24px; position: relative; transition: .3s; flex-shrink: 0; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background: white; transition: .3s; border-radius: 50%; }
        .switch input:checked + .slider { background: var(--success); }
        .switch input:checked + .slider:before { transform: translateX(20px); }
        
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .status-ok { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .status-warn { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }

        /* Diag Styles */
        .screenshots-grid { display: grid; grid-template-columns: 1fr; gap: 30px; max-width: 900px; margin: 0 auto;}
        .screenshot-card { background: var(--card-bg); border-radius: 12px; overflow: hidden; border: 1px solid rgba(255,255,255,0.07); }
        .screenshot-card-header { padding: 15px 20px; background: rgba(0,0,0,0.3); display: flex; justify-content: space-between; align-items: center; }
        .screenshot-name { font-weight: 600; font-size: 1.1rem; color: var(--accent-blue); }
        .screenshot-time { font-size: 0.9rem; color: var(--text-dim); }
        .screenshot-card img { width: 100%; display: block; border-top: 1px solid rgba(255,255,255,0.05); }
        .empty-state { text-align: center; padding: 60px; color: var(--text-dim); }
        .info-box { background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.3); border-radius: 10px; padding: 16px 20px; margin-bottom: 24px; font-size: 0.9rem; color: #bae6fd; }
        .auto-refresh { display: inline-flex; align-items: center; gap: 10px; font-size: 0.9rem; color: var(--text-dim); }
        .countdown { font-weight: 700; color: var(--accent-blue); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div>
                <h1 style="font-size: 2rem; font-weight: 800;">Odysee Hub</h1>
                <p style="color: var(--text-dim);">Fila de uploads e configurações da automação.</p>
            </div>
        </header>

        <?php if ($msg): ?> <div class="alert"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div> <?php endif; ?>
        <?php if ($error): ?> <div class="alert error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div> <?php endif; ?>

        <nav class="main-tabs-nav">
            <button type="button" class="main-tab-btn <?= $active_tab === 'fila' ? 'active' : '' ?>" onclick="switchMainTab('fila')">Fila de Publicação</button>
            <button type="button" class="main-tab-btn <?= $active_tab === 'config' ? 'active' : '' ?>" onclick="switchMainTab('config')">Contas Conectadas</button>
            <button type="button" class="main-tab-btn <?= $active_tab === 'template' ? 'active' : '' ?>" onclick="switchMainTab('template')">WhatsApp Template</button>
            <button type="button" class="main-tab-btn <?= $active_tab === 'diag' ? 'active' : '' ?>" onclick="switchMainTab('diag')"><i class="fas fa-camera"></i> Diagnóstico (Em Tempo Real)</button>
        </nav>

        <!-- ABA 1: FILA DE UPLOADS -->
        <div id="tab-fila" class="main-tab-content <?= $active_tab === 'fila' ? 'active' : '' ?>">
            <div style="margin-bottom: 20px; text-align: right;">
                <button class="btn-sm" onclick="location.href='odysee.php?tab=fila'"><i class="fas fa-sync-alt"></i> Atualizar Fila</button>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Idioma</th>
                        <th>Título Final</th>
                        <th>Status</th>
                        <th>Tentativas</th>
                        <th>Erro/Detalhe</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($queue as $row): 
                        $display_status = $row['status'];
                        $badge_class = strtoupper($row['status']);
                        
                        if ($row['status'] === 'waiting_host') {
                            if (!$row['odysee_auto_enabled']) {
                                $display_status = 'DISABLED';
                                $badge_class = 'DISABLED';
                            } elseif (empty(trim($row['odysee_auth_token']))) {
                                $display_status = 'NO_TOKEN';
                                $badge_class = 'NO_TOKEN';
                            } else {
                                $display_status = 'WAITING_TITLE';
                                $badge_class = 'WAITING_TITLE';
                            }
                        } elseif ($row['status'] === 'pending') {
                            if (!$row['odysee_auto_enabled']) {
                                $display_status = 'DISABLED';
                                $badge_class = 'DISABLED';
                            } elseif (empty(trim($row['odysee_auth_token']))) {
                                $display_status = 'NO_TOKEN';
                                $badge_class = 'NO_TOKEN';
                            }
                        }
                    ?>
                    <tr>
                        <td>#<?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['language_name']) ?></td>
                        <td>
                            <?= htmlspecialchars($row['titulo_final'] ?? 'Aguardando Host...') ?>
                            <?php if ($row['odysee_url']): ?>
                                <br><a href="<?= $row['odysee_url'] ?>" target="_blank" style="color:var(--accent-blue); font-size:0.85rem;"><?= $row['odysee_url'] ?></a>
                            <?php endif; ?>
                        </td>
                        <td><span class="status-badge status-<?= $badge_class ?>"><?= $display_status ?></span></td>
                        <td><?= $row['retry_count'] ?>/3</td>
                        <td style="font-size: 0.85rem; color: var(--text-dim); max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($row['error_message'] ?? '') ?>">
                            <?= htmlspecialchars($row['error_message'] ?? '') ?>
                        </td>
                        <td class="actions-col">
                            <?php if ($row['status'] === 'error' || $display_status === 'DISABLED' || $display_status === 'NO_TOKEN' || $row['status'] === 'processing' || $row['status'] === 'pending'): ?>
                                <button class="btn-sm" onclick="location.href='odysee.php?retry=<?= $row['id'] ?>'"><i class="fas fa-redo"></i> Tentar Novamente</button>
                            <?php endif; ?>
                            <?php if ($row['status'] === 'processing' || $row['status'] === 'pending' || $row['status'] === 'waiting_host'): ?>
                                <button class="btn-sm btn-danger" onclick="if(confirm('Tem certeza? Isso marcará a tarefa como erro.')) location.href='odysee.php?cancel=<?= $row['id'] ?>'"><i class="fas fa-times"></i> Cancelar</button>
                            <?php endif; ?>
                            <?php if ($row['status'] === 'done'): ?>
                                <button class="btn-sm" style="background-color: #25D366; border-color: #25D366; color: white;" onclick="location.href='odysee_manual_dispatch.php?id=<?= $row['id'] ?>'"><i class="fab fa-whatsapp"></i> Disparar Zap</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($queue)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;">A fila está vazia.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ABA 2: CONFIGURAÇÕES -->
        <div id="tab-config" class="main-tab-content <?= $active_tab === 'config' ? 'active' : '' ?>">
            <form method="POST" action="odysee.php">
                <input type="hidden" name="tab" value="config">
                <div class="bulk-card">
                    <table class="config-table">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Idioma</th>
                                <th style="width: 35%;">Token Odysee (auth_token)</th>
                                <th style="width: 25%;">Nome do Canal (ex: @Canal)</th>
                                <th style="width: 10%;">Status</th>
                                <th style="width: 15%; text-align: center;">Robô Ativo?</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($languages as $l): ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($l['name']) ?></td>
                                <td>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <input type="password" id="token_<?= $l['id'] ?>" name="langs[<?= $l['id'] ?>][token]" value="<?= htmlspecialchars($l['odysee_auth_token'] ?? '') ?>" placeholder="Colar token longo aqui...">
                                        <div id="token_status_<?= $l['id'] ?>" style="flex-shrink: 0; width: 30px; text-align: center; font-size: 1.1rem; cursor: default;">
                                            <?php if (!empty($l['odysee_auth_token'])): ?><span style="color: var(--text-dim);"><i class="fas fa-circle-notch fa-spin"></i></span><?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><input type="text" name="langs[<?= $l['id'] ?>][channel]" value="<?= htmlspecialchars($l['odysee_channel_name'] ?? '') ?>" placeholder="@Exemplo"></td>
                                <td>
                                    <?php if (empty($l['odysee_auth_token'])): ?>
                                        <span class="status-badge status-warn">Sem Token</span>
                                    <?php else: ?>
                                        <span class="status-badge status-ok">Configurado</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <label class="switch">
                                        <input type="checkbox" name="langs[<?= $l['id'] ?>][auto]" <?= $l['odysee_auto_enabled'] ? 'checked' : '' ?>>
                                        <span class="slider"></span>
                                    </label>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top: 30px; text-align: right;">
                        <button type="submit" name="bulk_save" class="btn-save"><i class="fas fa-save"></i> Salvar Alterações</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- ABA: WHATSAPP TEMPLATE -->
        <div id="tab-template" class="main-tab-content <?= $active_tab === 'template' ? 'active' : '' ?>">
            <div class="bulk-card" style="max-width: 800px; margin: 0 auto;">
                <h2 style="margin-bottom: 20px; color: var(--accent-red);"><i class="fab fa-whatsapp"></i> Template de Mensagem</h2>
                <p style="color: var(--text-dim); margin-bottom: 20px;">Esta é a mensagem que o robô dispara automaticamente nos grupos de WhatsApp quando o vídeo termina de fazer upload no Odysee.</p>
                <form method="POST" action="odysee.php">
                    <input type="hidden" name="tab" value="template">
                    <textarea name="template_texto" style="width: 100%; height: 150px; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 15px; color: white; margin-bottom: 15px; font-family: monospace; font-size: 14px;" required><?= htmlspecialchars($current_template) ?></textarea>
                    
                    <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; margin-bottom: 25px; font-size: 0.9rem;">
                        <strong>Variáveis Dinâmicas Disponíveis:</strong><br><br>
                        <code>{bandeira}</code> = Emoji da bandeira (ex: 🇺🇸)<br>
                        <code>{idioma}</code> = Nome do idioma (ex: Inglês)<br>
                        <code>{titulo}</code> = Título do encontro gravado (ex: O que você faria se ganhasse na loteria?)<br>
                        <code>{link}</code> = Link encurtado oficial da postagem no Odysee<br>
                    </div>
                    
                    <div style="text-align: right;">
                        <button type="submit" name="save_template" class="btn-save"><i class="fas fa-save"></i> Salvar Template</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ABA 3: DIAGNÓSTICO -->
        <div id="tab-diag" class="main-tab-content <?= $active_tab === 'diag' ? 'active' : '' ?>">
            <div style="display:flex; justify-content:flex-end; align-items:center; margin-bottom: 20px;">
                <button class="btn-sm" style="margin-right: 15px;" onclick="fetchDiagUpdate()"><i class="fas fa-sync-alt"></i> Atualizar Agora</button>
            </div>
            
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <strong>Diagnóstico em tempo real:</strong> Esta tela se atualiza automaticamente via AJAX a cada 15 segundos — <strong>sem precisar recarregar o navegador</strong>. Durante o upload, o robô captura screenshots a cada ciclo de ~30s.
            </div>

            <div id="diag-container">
                <?php if (empty($screenshots)): ?>
                <div class="empty-state">
                    <i class="fas fa-video-slash" style="font-size: 3rem; margin-bottom: 16px; display: block;"></i>
                    <p>Nenhuma transmissão ativa. O robô não tirou nenhuma foto recentemente.</p>
                </div>
                <?php else: ?>
                <div class="screenshots-grid">
                    <?php foreach ($screenshots as $s): ?>
                    <div class="screenshot-card">
                        <div class="screenshot-card-header">
                            <div>
                                <div class="screenshot-name">
                                    #<?= $s['id'] ?> - <?= htmlspecialchars($s['language_name']) ?>
                                    <span class="badge badge-<?= $s['status'] ?>" style="margin-left:10px;"><?= $s['status'] ?></span>
                                </div>
                                <div style="font-size: 0.85rem; color: #94a3b8; margin-top: 5px;"><?= htmlspecialchars($s['titulo_final']) ?></div>
                            </div>
                            <span class="screenshot-time"><i class="far fa-clock"></i> <?= date('d/m H:i:s', strtotime($s['last_screenshot_time'])) ?></span>
                        </div>
                        <img src="data:image/png;base64,<?= $s['last_screenshot'] ?>" alt="Screenshot">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function switchMainTab(tabId) {
            document.querySelectorAll('.main-tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.main-tab-content').forEach(content => content.classList.remove('active'));
            
            event.currentTarget.classList.add('active');
            document.getElementById('tab-' + tabId).classList.add('active');
            
            // Update URL without reloading
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.pushState({}, '', url);
            
            if (tabId === 'config') {
                testAllTokens();
            }
            // Ao entrar na aba de diagnóstico, busca imediatamente sem esperar o intervalo
            if (tabId === 'diag') {
                fetchDiagUpdate();
            }
        }

        async function testToken(id) {
            const token = document.getElementById('token_' + id).value;
            const statusDiv = document.getElementById('token_status_' + id);
            if (!statusDiv) return;

            if (!token.trim()) {
                statusDiv.innerHTML = '';
                return;
            }

            statusDiv.innerHTML = '<span style="color:var(--text-dim);"><i class="fas fa-circle-notch fa-spin"></i></span>';

            try {
                const fd = new FormData();
                fd.append('token', token);

                const response = await fetch('ajax_test_odysee_token.php', { method: 'POST', body: fd });
                const data = await response.json();

                if (data.success) {
                    const tip = 'Válido — ' + (data.email || '');
                    statusDiv.innerHTML = '<span style="color:var(--success);" title="' + tip + '"><i class="fas fa-check-circle"></i></span>';
                } else {
                    const tip = (data.error || 'Erro') + (data.details ? ': ' + data.details : '');
                    statusDiv.innerHTML = '<span style="color:var(--accent-red);" title="' + tip.replace(/"/g, "'") + '"><i class="fas fa-times-circle"></i></span>';
                }
            } catch (err) {
                statusDiv.innerHTML = '<span style="color:var(--accent-red);" title="Erro de rede"><i class="fas fa-times-circle"></i></span>';
            }
        }

        async function testAllTokens() {
            const inputs = document.querySelectorAll('input[id^="token_"]');
            for (const input of inputs) {
                if (input.value.trim() !== '') {
                    const id = input.id.replace('token_', '');
                    await testToken(id); // Espera um terminar para testar o próximo (evita bloqueio da API)
                }
            }
        }

        // Executar auto-teste se a aba ativa na inicialização for a de config
        window.addEventListener('DOMContentLoaded', () => {
            if (document.getElementById('tab-config').classList.contains('active')) {
                testAllTokens();
            }
        });

        // Auto-refresh logic for Diag Tab using AJAX
        let lastTimestamp = "";
        let diagPollTimer = null;
        
        async function fetchDiagUpdate() {
            // Sempre tentar, independente de qual aba está ativa
            try {
                const res = await fetch('ajax_diag_screenshot.php');
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                
                if (json.success && json.data) {
                    const data = json.data;
                    const container = document.getElementById('diag-container');
                    
                    // Atualiza sempre que tiver dados (independente do timestamp)
                    if (container && data.last_screenshot) {
                        container.innerHTML = `
                            <div class="screenshots-grid">
                                <div class="screenshot-card">
                                    <div class="screenshot-card-header">
                                        <div>
                                            <div class="screenshot-name">
                                                #${data.id} - ${data.language_name}
                                                <span class="badge badge-${data.status}" style="margin-left:10px;">${data.status}</span>
                                            </div>
                                            <div style="font-size: 0.85rem; color: #94a3b8; margin-top: 5px;">${data.titulo_final || ''}</div>
                                        </div>
                                        <span class="screenshot-time"><i class="far fa-clock"></i> ${data.last_screenshot_time_fmt} <span id="diag-age" style="color:#f59e0b;font-size:0.8em;"></span></span>
                                    </div>
                                    <img src="data:image/png;base64,${data.last_screenshot}" alt="Screenshot" style="width:100%;">
                                </div>
                            </div>
                        `;
                        lastTimestamp = data.last_screenshot_time;
                        
                        // Mostra há quantos segundos a foto foi tirada
                        atualizarIdade(data.last_screenshot_time);
                    }
                }
            } catch (e) {
                console.error("Erro ao fazer polling da screenshot:", e);
            }
        }
        
        function atualizarIdade(timestamp) {
            const el = document.getElementById('diag-age');
            if (!el || !timestamp) return;
            const taken = new Date(timestamp.replace(' ', 'T') + 'Z');
            const diffSec = Math.round((Date.now() - taken.getTime()) / 1000);
            el.textContent = `(${diffSec}s atrás)`;
        }
        
        // Atualiza a "idade" da foto a cada segundo, sem rebuscar
        setInterval(() => {
            if (lastTimestamp) atualizarIdade(lastTimestamp);
        }, 1000);
        
        // Busca nova foto a cada 15 segundos (independente de qual aba está visível)
        diagPollTimer = setInterval(fetchDiagUpdate, 15000);
        
        // Busca imediatamente ao carregar
        setTimeout(fetchDiagUpdate, 500);
    </script>
</body>
</html>
