<?php
session_start();
require_once '../config.php';
require_once '../includes/whatsapp_helper.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Ação: QR Code Proxy
if (isset($_GET['action']) && $_GET['action'] === 'qr') {
    $url = getBestBaileysUrl() . '/qr';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        header('Content-Type: text/html; charset=UTF-8');
        echo "<h3>Erro ao conectar ao servidor Node.js (Baileys).</h3>";
        echo "<p>Detalhe do Erro: " . htmlspecialchars(curl_error($ch)) . "</p>";
    } else {
        echo $response;
    }
    curl_close($ch);
    exit;
}

$error = null;

// Lógica de Desconectar (Logout)
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $res = sendBaileysRequest('/logout', null, 'DELETE');
    if (!$res['success']) {
        $error = "Erro ao desconectar: " . $res['error'];
    } else {
        header("Location: conectar_whatsapp.php?msg=Sessão finalizada!");
        exit;
    }
}

// Lógica de Reset Completo
if (isset($_GET['action']) && $_GET['action'] === 'reset') {
    $res = sendBaileysRequest('/reset', null, 'POST');
    if (!$res['success']) {
        $error = "Erro ao resetar: " . $res['error'];
    } else {
        header("Location: conectar_whatsapp.php?msg=Sessão recriada com sucesso! Aguarde e escaneie o novo QR Code.");
        exit;
    }
}

$status = statusWhatsApp();
$state = $status['connected'] ? 'connected' : 'disconnected';
$qrCodeBase64 = null; // Não usado no Baileys, o QR é servido via HTML
$pairingCode = null;  // Não suportado atualmente no Baileys configurado
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conectar WhatsApp | Admin</title>
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
            --warning: #f59e0b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        
        .card { background: var(--card-bg); padding: 40px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); text-align: center; max-width: 500px; width: 100%; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        .card h2 { margin-bottom: 10px; font-size: 1.8rem; }
        .card p { color: var(--text-dim); margin-bottom: 25px; font-size: 0.95rem; line-height: 1.5; }
        
        .qr-container { background: white; padding: 20px; border-radius: 15px; display: inline-block; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); position: relative; }
        .qr-container img { display: block; max-width: 250px; width: 100%; height: auto; }
        
        .status-badge { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 50px; font-weight: 600; font-size: 0.9rem; margin-bottom: 25px; }
        .status-badge.connected { background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2); }
        .status-badge.disconnected { background: rgba(227, 29, 28, 0.1); color: var(--accent-red); border: 1px solid rgba(227, 29, 28, 0.2); }
        
        .btn { padding: 12px 24px; border-radius: 10px; font-weight: bold; cursor: pointer; text-decoration: none; border: none; color: white; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 0.95rem; }
        .btn-primary { background: var(--accent-red); }
        .btn-primary:hover { opacity: 0.9; }
        .btn-secondary { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); }
        .btn-secondary:hover { background: rgba(255,255,255,0.2); }
        
        .alert { padding: 15px; background: rgba(16, 185, 129, 0.1); color: var(--success); border-radius: 12px; margin-bottom: 20px; border: 1px solid rgba(16, 185, 129, 0.2); text-align: left; width: 100%; }
        .alert.error { background: rgba(227, 29, 28, 0.1); color: var(--accent-red); border-color: rgba(227, 29, 28, 0.2); }
        
        .pairing-code { font-family: monospace; font-size: 1.4rem; letter-spacing: 2px; background: rgba(255,255,255,0.05); padding: 10px 20px; border-radius: 8px; display: inline-block; margin-top: 10px; color: #38bdf8; border: 1px dashed rgba(56, 189, 248, 0.3); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert" style="max-width: 500px;"><?= htmlspecialchars($_GET['msg']) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error" style="max-width: 500px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <?php if ($state === 'open' || $state === 'connected'): ?>
                <div class="status-badge connected">
                    <i class="fas fa-check-circle"></i> Conectado
                </div>
                <h2>WhatsApp Ativo!</h2>
                <p>O sistema de cobranças e lembretes de Meetups e Mentorias está operando normalmente com o WhatsApp conectado.</p>
                <div style="margin-top: 20px; display: flex; gap: 15px; justify-content: center;">
                    <a href="conectar_whatsapp.php" class="btn btn-secondary"><i class="fas fa-sync"></i> Atualizar Status</a>
                    <a href="?action=logout" class="btn btn-primary" onclick="return confirm('Deseja realmente desconectar este WhatsApp?')"><i class="fas fa-sign-out-alt"></i> Desconectar Sessão</a>
                </div>
            <?php else: ?>
                <div class="status-badge disconnected">
                    <i class="fas fa-exclamation-triangle"></i> Desconectado
                </div>
                <h2>Conectar WhatsApp</h2>
                <p>Abra o WhatsApp no seu celular, vá em <strong>Aparelhos conectados > Conectar um aparelho</strong> e aponte a câmera para o QR Code abaixo.</p>
                
                <?php if ($state !== 'connected'): ?>
                    <div class="qr-container" style="padding: 0; overflow: hidden; height: 350px;">
                        <iframe src="?action=qr" width="100%" height="100%" style="border: none;"></iframe>
                    </div>
                <?php endif; ?>

                <?php if ($pairingCode): ?>
                    <div style="margin-bottom: 25px;">
                        <span style="font-size: 0.85rem; color: var(--text-dim);">Código de Pareamento Alternativo:</span><br>
                        <div class="pairing-code"><?= htmlspecialchars($pairingCode) ?></div>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 10px; display: flex; gap: 15px; justify-content: center;">
                    <a href="conectar_whatsapp.php" class="btn btn-secondary"><i class="fas fa-sync"></i> Atualizar QR Code</a>
                    <a href="?action=reset" class="btn btn-secondary" style="border-color: rgba(227, 29, 28, 0.5); color: #ff8a8a;" onclick="return confirm('Isso vai apagar a sessão atual na API e gerar uma nova do zero. Tem certeza?')"><i class="fas fa-trash-alt"></i> Forçar Nova Sessão</a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
