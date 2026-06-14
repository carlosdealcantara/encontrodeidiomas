<?php
session_start();
require_once '../config.php';
require_once '../includes/whatsapp_helper.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Lógica de Renderizar QR Code via Proxy (JSON API para AJAX)
if (isset($_GET['action']) && $_GET['action'] === 'qr_status') {
    header('Content-Type: application/json');
    $url = getBestBaileysUrl() . '/qr?json=true';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch) || $httpCode >= 400) {
        echo json_encode(['connected' => false, 'qr' => null, 'error' => 'server_unavailable']);
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
        if ($res['httpCode'] >= 500) {
            $error = "O servidor está reiniciando. Aguarde alguns segundos.";
        } else {
            $error = "Erro ao desconectar: " . $res['error'];
        }
    } else {
        header("Location: conectar_whatsapp.php?msg=Sessão finalizada! Aguarde o novo QR Code aparecer.");
        exit;
    }
}

// Lógica de Reset Completo
if (isset($_GET['action']) && $_GET['action'] === 'reset') {
    $res = sendBaileysRequest('/reset', null, 'POST');
    if (!$res['success']) {
        if ($res['httpCode'] >= 500) {
            $error = "O servidor está reiniciando. Aguarde alguns segundos e tente novamente.";
        } else {
            $error = "Erro ao resetar: " . $res['error'];
        }
    } else {
        header("Location: conectar_whatsapp.php?msg=Sessão recriada com sucesso! Aguarde e escaneie o novo QR Code.");
        exit;
    }
}

$status = statusWhatsApp();
$state = $status['connected'] ? 'connected' : 'disconnected';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conectar WhatsApp | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        
        .card { background: var(--card-bg); padding: 40px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); text-align: center; max-width: 500px; width: 100%; margin: 40px auto 0 auto; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        .card h2 { margin-bottom: 10px; font-size: 1.8rem; }
        .card p { color: var(--text-dim); margin-bottom: 25px; font-size: 0.95rem; line-height: 1.5; }
        
        .qr-container { background: white; padding: 20px; border-radius: 15px; display: inline-block; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        
        .status-badge { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 50px; font-weight: 600; font-size: 0.9rem; margin-bottom: 25px; }
        .status-badge.connected { background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2); }
        .status-badge.disconnected { background: rgba(227, 29, 28, 0.1); color: var(--accent-red); border: 1px solid rgba(227, 29, 28, 0.2); }
        
        .btn { padding: 12px 24px; border-radius: 10px; font-weight: bold; cursor: pointer; text-decoration: none; border: none; color: white; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 0.95rem; }
        .btn-primary { background: var(--accent-red); }
        .btn-primary:hover { opacity: 0.9; }
        .btn-secondary { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); }
        .btn-secondary:hover { background: rgba(255,255,255,0.2); }
        
        .alert { padding: 15px; background: rgba(16, 185, 129, 0.1); color: var(--success); border-radius: 12px; margin-bottom: 20px; border: 1px solid rgba(16, 185, 129, 0.2); text-align: left; width: 100%; max-width: 500px; }
        .alert.error { background: rgba(227, 29, 28, 0.1); color: var(--accent-red); border-color: rgba(227, 29, 28, 0.2); }
        
        /* Loader */
        .loader { border: 4px solid rgba(255,255,255,0.1); border-top: 4px solid #4f46e5; border-radius: 50%; width: 44px; height: 44px; animation: spin 1s linear infinite; margin: 25px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        /* QR Code container animations */
        #qr-area { transition: opacity 0.3s ease; }
        #qr-area.fade { opacity: 0.3; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- WhatsApp Sub-Nav -->
        <div style="display: flex; gap: 15px; margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px;">
            <a href="meetup_groups.php" class="btn <?= basename($_SERVER['PHP_SELF']) == 'meetup_groups.php' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fab fa-whatsapp"></i> Configurar Grupos</a>
            <a href="meetup_templates.php" class="btn <?= basename($_SERVER['PHP_SELF']) == 'meetup_templates.php' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-comment-dots"></i> Templates de Mensagem</a>
            <a href="wpp_broadcast.php" class="btn <?= basename($_SERVER['PHP_SELF']) == 'wpp_broadcast.php' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-bullhorn"></i> Disparar Mensagem</a>
            <a href="wpp_resumo_semanal.php" class="btn <?= basename($_SERVER['PHP_SELF']) == 'wpp_resumo_semanal.php' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-list-alt"></i> Resumo Semanal</a>
            <a href="conectar_whatsapp.php" class="btn <?= basename($_SERVER['PHP_SELF']) == 'conectar_whatsapp.php' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-qrcode"></i> Conexão e Status</a>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert"><?= htmlspecialchars($_GET['msg']) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <div id="status-badge-area">
                <div class="status-badge <?= $state ?>" id="statusBadge">
                    <i class="fas fa-<?= $state === 'connected' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                    <span id="statusText"><?= $state === 'connected' ? 'Conectado' : 'Desconectado' ?></span>
                </div>
            </div>

            <div id="main-area">
                <?php if ($state === 'connected'): ?>
                    <h2>WhatsApp Ativo!</h2>
                    <p>O sistema de cobranças e lembretes de Meetups e Mentorias está operando normalmente com o WhatsApp conectado.</p>
                    <div style="display: flex; gap: 15px; justify-content: center;">
                        <a href="conectar_whatsapp.php" class="btn btn-secondary"><i class="fas fa-sync"></i> Atualizar Status</a>
                        <a href="?action=logout" class="btn btn-primary" onclick="return confirm('Deseja realmente desconectar este WhatsApp?')"><i class="fas fa-sign-out-alt"></i> Desconectar Sessão</a>
                    </div>
                <?php else: ?>
                    <h2>Conectar WhatsApp</h2>
                    <p>Abra o WhatsApp no seu celular, vá em <strong>Aparelhos conectados > Conectar um aparelho</strong> e aponte a câmera para o QR Code abaixo.</p>
                    
                    <div id="qr-area">
                        <div class="loader" id="qrLoader"></div>
                        <p id="qrMessage" style="color: var(--text-dim); font-size: 0.9rem;">Aguardando QR Code do servidor...</p>
                    </div>

                    <div style="margin-top: 15px; display: flex; gap: 15px; justify-content: center;">
                        <a href="conectar_whatsapp.php" class="btn btn-secondary"><i class="fas fa-sync"></i> Atualizar QR Code</a>
                        <a href="?action=reset" class="btn btn-secondary" style="border-color: rgba(227, 29, 28, 0.5); color: #ff8a8a;" onclick="return confirm('Isso vai apagar a sessão atual na API e gerar uma nova do zero. Tem certeza?')"><i class="fas fa-trash-alt"></i> Forçar Nova Sessão</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
    (function() {
        let currentQR = null;
        let qrCodeObj = null;
        let polling = true;

        async function checkQR() {
            if (!polling) return;
            try {
                const res = await fetch('?action=qr_status');
                const data = await res.json();
                
                if (data.connected) {
                    // Atualizar UI para conectado
                    const badge = document.getElementById('statusBadge');
                    badge.className = 'status-badge connected';
                    badge.innerHTML = '<i class="fas fa-check-circle"></i> <span>Conectado</span>';
                    
                    const mainArea = document.getElementById('main-area');
                    mainArea.innerHTML = '<h2>WhatsApp Ativo!</h2><p>O sistema de cobranças e lembretes de Meetups e Mentorias está operando normalmente com o WhatsApp conectado.</p><div style="display: flex; gap: 15px; justify-content: center;"><a href="conectar_whatsapp.php" class="btn btn-secondary"><i class="fas fa-sync"></i> Atualizar Status</a><a href="?action=logout" class="btn btn-primary" onclick="return confirm(\'Deseja realmente desconectar este WhatsApp?\')"><i class="fas fa-sign-out-alt"></i> Desconectar Sessão</a></div>';
                    
                    polling = false;
                    return;
                }
                
                const qrArea = document.getElementById('qr-area');
                if (!qrArea) return; // Já está em modo conectado
                
                if (data.qr) {
                    if (currentQR !== data.qr) {
                        currentQR = data.qr;
                        qrArea.innerHTML = '<div class="qr-container" id="qrCanvas"></div><p style="color: var(--text-dim); font-size: 0.85rem;">Atualizando em tempo real...</p>';
                        qrCodeObj = new QRCode(document.getElementById("qrCanvas"), {
                            text: data.qr,
                            width: 256,
                            height: 256,
                            colorDark : "#000000",
                            colorLight : "#ffffff",
                            correctLevel : QRCode.CorrectLevel.H
                        });
                    }
                } else {
                    if (currentQR !== null) {
                        // QR sumiu mas não conectou — servidor reiniciando
                        currentQR = null;
                        qrArea.innerHTML = '<div class="loader"></div><p style="color: var(--text-dim); font-size: 0.9rem;">Servidor reiniciando, aguarde...</p>';
                    }
                }
            } catch (e) {
                console.error('Erro ao checar QR:', e);
            }
            setTimeout(checkQR, 3000);
        }
        
        // Só iniciar polling se desconectado
        if (document.getElementById('qr-area')) {
            checkQR();
        }
    })();
    </script>
</body>
</html>
