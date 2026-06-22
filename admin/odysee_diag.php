<?php
/**
 * Odysee Diagnostics — Visualiza screenshots do Playwright em tempo real via DB
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();
$stmt = $conn->query("
    SELECT q.id, q.titulo_final, q.status, q.last_screenshot, q.last_screenshot_time, l.name as language_name
    FROM odysee_publish_queue q
    LEFT JOIN languages l ON q.language_id = l.id
    WHERE q.last_screenshot IS NOT NULL
    ORDER BY q.id DESC LIMIT 10
");
$screenshots = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Odysee Diagnósticos - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { font-size: 1.8rem; font-weight: 700; color: var(--white); }
        .btn-sm { padding: 8px 14px; background: rgba(255,255,255,0.1); border-radius: 8px; color: white; text-decoration: none; font-size: 0.9rem; transition: 0.2s; border: none; cursor: pointer; }
        .btn-sm:hover { background: rgba(255,255,255,0.2); }
        .screenshots-grid { display: grid; grid-template-columns: 1fr; gap: 30px; max-width: 900px; margin: 0 auto;}
        .screenshot-card { background: var(--card-bg); border-radius: 12px; overflow: hidden; border: 1px solid rgba(255,255,255,0.07); }
        .screenshot-card-header { padding: 15px 20px; background: rgba(0,0,0,0.3); display: flex; justify-content: space-between; align-items: center; }
        .screenshot-name { font-weight: 600; font-size: 1.1rem; color: var(--accent-blue); }
        .screenshot-time { font-size: 0.9rem; color: var(--text-dim); }
        .screenshot-card img { width: 100%; display: block; border-top: 1px solid rgba(255,255,255,0.05); }
        .empty-state { text-align: center; padding: 60px; color: var(--text-dim); }
        .info-box { background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.3); border-radius: 10px; padding: 16px 20px; margin-bottom: 24px; font-size: 0.9rem; color: #bae6fd; }
        .auto-refresh { display: flex; align-items: center; gap: 10px; font-size: 0.9rem; color: var(--text-dim); }
        .countdown { font-weight: 700; color: var(--accent-blue); }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; background: rgba(255,255,255,0.1); }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-camera" style="color: var(--accent-blue)"></i> Câmera de Segurança (Odysee)</h1>
            <div style="display:flex; gap: 10px; align-items:center;">
                <div class="auto-refresh">Auto-refresh: <span class="countdown" id="countdown">15s</span></div>
                <button class="btn-sm" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Atualizar</button>
            </div>
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
                            <span class="status-badge" style="margin-left:10px;"><?= $s['status'] ?></span>
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
    </main>

    <script>
        let secs = 15;
        const el = document.getElementById('countdown');
        setInterval(() => {
            secs--;
            el.textContent = secs + 's';
            if (secs <= 0) location.reload();
        }, 1000);
    </script>
</body>
</html>
