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
    SELECT q.*, l.name as language_name 
    FROM odysee_publish_queue q
    LEFT JOIN languages l ON q.language_id = l.id
    ORDER BY q.id DESC LIMIT 100
");
$queue = $stmt->fetchAll();

// --- LOGIC: DIAGNOSTICS ---
$stmt = $conn->query("
    SELECT q.id, q.titulo_final, q.status, q.last_screenshot, q.last_screenshot_time, l.name as language_name
    FROM odysee_publish_queue q
    LEFT JOIN languages l ON q.language_id = l.id
    WHERE q.last_screenshot IS NOT NULL
    ORDER BY q.id DESC LIMIT 10
");
$screenshots = $stmt->fetchAll();

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
            <button class="main-tab-btn <?= $active_tab === 'fila' ? 'active' : '' ?>" onclick="switchMainTab('fila')">
                <i class="fas fa-list"></i> Fila de Uploads
            </button>
            <button class="main-tab-btn <?= $active_tab === 'config' ? 'active' : '' ?>" onclick="switchMainTab('config')">
                <i class="fas fa-cogs"></i> Configurações
            </button>
            <button class="main-tab-btn <?= $active_tab === 'diag' ? 'active' : '' ?>" onclick="switchMainTab('diag')">
                <i class="fas fa-camera"></i> Diagnóstico (Em Tempo Real)
            </button>
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
                    <?php foreach ($queue as $row): ?>
                    <tr>
                        <td>#<?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['language_name']) ?></td>
                        <td>
                            <?= htmlspecialchars($row['titulo_final'] ?? 'Aguardando Host...') ?>
                            <?php if ($row['odysee_url']): ?>
                                <br><a href="<?= $row['odysee_url'] ?>" target="_blank" style="color:var(--accent-blue); font-size:0.85rem;"><?= $row['odysee_url'] ?></a>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?= $row['status'] ?>"><?= $row['status'] ?></span></td>
                        <td><?= $row['retry_count'] ?>/3</td>
                        <td style="font-size: 0.85rem; color: var(--text-dim); max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($row['error_message'] ?? '') ?>">
                            <?= htmlspecialchars($row['error_message'] ?? '') ?>
                        </td>
                        <td>
                            <?php if ($row['status'] === 'error' || $row['status'] === 'processing'): ?>
                                <a href="?retry=<?= $row['id'] ?>" class="btn-sm"><i class="fas fa-redo"></i> Tentar Novamente</a>
                            <?php endif; ?>
                            <?php if ($row['status'] === 'pending' || $row['status'] === 'processing'): ?>
                                <a href="?cancel=<?= $row['id'] ?>" class="btn-sm btn-danger"><i class="fas fa-times"></i> Cancelar</a>
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
                                        <button type="button" class="btn-sm" style="background: rgba(56, 189, 248, 0.2); color: #38bdf8;" onclick="testToken(<?= $l['id'] ?>)" title="Testar Token"><i class="fas fa-stethoscope"></i></button>
                                    </div>
                                    <div id="test_result_<?= $l['id'] ?>" style="font-size: 0.8rem; margin-top: 5px; min-height: 15px;"></div>
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

        <!-- ABA 3: DIAGNÓSTICO -->
        <div id="tab-diag" class="main-tab-content <?= $active_tab === 'diag' ? 'active' : '' ?>">
            <div style="display:flex; justify-content:flex-end; align-items:center; margin-bottom: 20px;">
                <button class="btn-sm" style="margin-right: 15px;" onclick="location.href='odysee.php?tab=diag'"><i class="fas fa-sync-alt"></i> Atualizar Agora</button>
                <div class="auto-refresh">Auto-refresh: <span class="countdown" id="countdown">15s</span></div>
            </div>
            
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <strong>Visão em Tempo Real:</strong> Aqui você acompanha a tela do navegador do robô durante o upload. A imagem é atualizada automaticamente a cada etapa do processo.
            </div>

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
        }

        async function testToken(id) {
            const token = document.getElementById('token_' + id).value;
            const resDiv = document.getElementById('test_result_' + id);
            
            if (!token.trim()) {
                resDiv.innerHTML = '<span style="color:var(--warning);"><i class="fas fa-exclamation-circle"></i> Token vazio.</span>';
                return;
            }
            
            resDiv.innerHTML = '<span style="color:var(--text-dim);"><i class="fas fa-spinner fa-spin"></i> Testando...</span>';
            
            try {
                const fd = new FormData();
                fd.append('token', token);
                
                const response = await fetch('ajax_test_odysee_token.php', { method: 'POST', body: fd });
                const data = await response.json();
                
                if (data.success) {
                    resDiv.innerHTML = '<span style="color:var(--success);"><i class="fas fa-check-circle"></i> ' + data.message + ' (' + data.email + ')</span>';
                } else {
                    resDiv.innerHTML = '<span style="color:var(--accent-red);"><i class="fas fa-times-circle"></i> ' + data.error + (data.details ? ' - ' + data.details : '') + '</span>';
                }
            } catch (err) {
                resDiv.innerHTML = '<span style="color:var(--accent-red);"><i class="fas fa-times-circle"></i> Erro de rede ao testar.</span>';
            }
        }

        // Auto-refresh logic for Diag Tab
        let secs = 15;
        const el = document.getElementById('countdown');
        if (el) {
            setInterval(() => {
                if (document.getElementById('tab-diag').classList.contains('active')) {
                    secs--;
                    el.textContent = secs + 's';
                    if (secs <= 0) {
                        window.location.href = 'odysee.php?tab=diag';
                    }
                } else {
                    secs = 15; // Reset se sair da aba
                    el.textContent = '15s';
                }
            }, 1000);
        }
    </script>
</body>
</html>
