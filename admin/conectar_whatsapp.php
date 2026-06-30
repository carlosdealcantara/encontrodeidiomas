<?php
session_start();
require_once '../config.php';
require_once '../includes/whatsapp_helper.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Proxy: Status QR + Pairing Code (para AJAX)
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

// Proxy: Solicitar Pairing Code
if (isset($_GET['action']) && $_GET['action'] === 'request_pairing_code') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $phone = isset($input['phone']) ? preg_replace('/\D/', '', $input['phone']) : '';
    if (empty($phone) || strlen($phone) < 10 || strlen($phone) > 15) {
        echo json_encode(['success' => false, 'error' => 'Número inválido. Use DDI+DDD+número (ex: 5521999999999).']);
        exit;
    }
    $url = getBestBaileysUrl() . '/request-pairing-code';
    $payload = json_encode(['phone' => $phone]);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch) || $httpCode >= 500) {
        echo json_encode(['success' => false, 'error' => 'Servidor indisponível. Tente em alguns segundos.']);
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
        $error = $res['httpCode'] >= 500 ? "O servidor está reiniciando. Aguarde alguns segundos." : "Erro ao desconectar: " . $res['error'];
    } else {
        header("Location: conectar_whatsapp.php?msg=Sessão finalizada! Aguarde o novo QR Code aparecer.");
        exit;
    }
}

// Lógica de Reset Completo
if (isset($_GET['action']) && $_GET['action'] === 'reset') {
    $res = sendBaileysRequest('/reset', null, 'POST');
    if (!$res['success']) {
        $error = $res['httpCode'] >= 500 ? "O servidor está reiniciando. Aguarde alguns segundos e tente novamente." : "Erro ao resetar: " . $res['error'];
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
            --card-bg: #1e293b;
            --input-bg: #0f172a;
            --accent-red: #e31d1c;
            --text-main: #f1f5f9;
            --text-dim: #94a3b8;
            --success: #10b981;
            --indigo: #4f46e5;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }

        /* Card */
        .card { background: var(--card-bg); padding: 36px 32px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); text-align: center; max-width: 520px; width: 100%; margin: 40px auto 0; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        .card h2 { margin-bottom: 10px; font-size: 1.8rem; }

        /* Status badge */
        .status-badge { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 50px; font-weight: 600; font-size: 0.9rem; margin-bottom: 25px; }
        .status-badge.connected { background: rgba(16,185,129,0.1); color: var(--success); border: 1px solid rgba(16,185,129,0.2); }
        .status-badge.disconnected { background: rgba(227,29,28,0.1); color: var(--accent-red); border: 1px solid rgba(227,29,28,0.2); }

        /* Buttons */
        .btn { padding: 12px 24px; border-radius: 10px; font-weight: bold; cursor: pointer; text-decoration: none; border: none; color: white; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 0.95rem; font-family: 'Outfit', sans-serif; }
        .btn-primary { background: var(--accent-red); }
        .btn-primary:hover { opacity: 0.9; }
        .btn-secondary { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); }
        .btn-secondary:hover { background: rgba(255,255,255,0.2); }
        .btn-indigo { background: var(--indigo); }
        .btn-indigo:hover { background: #4338ca; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        /* Alerts */
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; text-align: left; width: 100%; max-width: 520px; }
        .alert.success { background: rgba(16,185,129,0.1); color: var(--success); border: 1px solid rgba(16,185,129,0.2); }
        .alert.error { background: rgba(227,29,28,0.1); color: var(--accent-red); border: 1px solid rgba(227,29,28,0.2); }

        /* Loader */
        .loader { border: 4px solid rgba(255,255,255,0.1); border-top: 4px solid var(--indigo); border-radius: 50%; width: 44px; height: 44px; animation: spin 1s linear infinite; margin: 25px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* Auth Tabs */
        .auth-tabs { display: flex; gap: 6px; background: rgba(0,0,0,0.3); border-radius: 12px; padding: 5px; margin-bottom: 24px; }
        .auth-tab { flex: 1; padding: 10px 8px; border: none; border-radius: 8px; font-family: 'Outfit', sans-serif; font-size: 0.88rem; font-weight: 600; cursor: pointer; transition: all 0.2s; color: var(--text-dim); background: transparent; }
        .auth-tab.active { background: var(--indigo); color: white; }
        .auth-tab:hover:not(.active) { color: var(--text-main); background: rgba(255,255,255,0.07); }

        /* Pairing Code */
        .pairing-code-display { background: rgba(79,70,229,0.1); border: 2px solid var(--indigo); border-radius: 14px; padding: 22px 16px; margin: 16px 0; letter-spacing: 8px; font-size: 2.4rem; font-weight: 700; color: #a5b4fc; font-family: monospace; }
        .phone-row { display: flex; gap: 8px; margin: 16px 0; }
        .phone-input { flex: 1; padding: 11px 14px; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; color: var(--text-main); font-family: 'Outfit', sans-serif; font-size: 0.95rem; outline: none; transition: border-color 0.2s; }
        .phone-input:focus { border-color: var(--indigo); }
        .phone-input::placeholder { color: #4b5563; }
        .step-box { background: rgba(0,0,0,0.25); border-radius: 10px; padding: 11px 14px; margin: 7px 0; text-align: left; }
        .step-label { color: var(--indigo); font-weight: 700; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 1px; }
        .step-desc { color: var(--text-dim); font-size: 0.85rem; margin-top: 3px; line-height: 1.4; }
        .step-desc strong { color: var(--text-main); }
        .err-msg { color: #f87171; font-size: 0.85rem; margin-top: 6px; min-height: 18px; }
        .divider { border: none; border-top: 1px solid rgba(255,255,255,0.07); margin: 18px 0; }
        .hint-warn { color: #f59e0b; font-size: 0.82rem; margin-top: 10px; }

        /* QR */
        .qr-container { background: white; padding: 20px; border-radius: 15px; display: inline-block; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }

        /* Sub-nav */
        .subnav { display: flex; gap: 15px; margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; flex-wrap: wrap; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="subnav">
            <a href="meetup_groups.php" class="btn <?= basename($_SERVER['PHP_SELF']) == 'meetup_groups.php' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fab fa-whatsapp"></i> Configurar Grupos</a>
            <a href="meetup_templates.php" class="btn <?= basename($_SERVER['PHP_SELF']) == 'meetup_templates.php' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-comment-dots"></i> Templates de Mensagem</a>
            <a href="wpp_broadcast.php" class="btn <?= basename($_SERVER['PHP_SELF']) == 'wpp_broadcast.php' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-bullhorn"></i> Disparar Mensagem</a>
            <a href="wpp_resumo_semanal.php" class="btn <?= basename($_SERVER['PHP_SELF']) == 'wpp_resumo_semanal.php' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-list-alt"></i> Resumo Semanal</a>
            <a href="conectar_whatsapp.php" class="btn <?= basename($_SERVER['PHP_SELF']) == 'conectar_whatsapp.php' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-qrcode"></i> Conexão e Status</a>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert success"><?= htmlspecialchars($_GET['msg']) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="status-badge <?= $state ?>" id="statusBadge">
                <i class="fas fa-<?= $state === 'connected' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                <span id="statusText"><?= $state === 'connected' ? 'Conectado' : 'Desconectado' ?></span>
            </div>

            <div id="main-area">
                <?php if ($state === 'connected'): ?>
                    <h2>WhatsApp Ativo!</h2>
                    <p style="color:var(--text-dim); margin-bottom:25px;">O sistema de cobranças e lembretes de Meetups e Mentorias está operando normalmente com o WhatsApp conectado.</p>
                    <div style="display: flex; gap: 15px; justify-content: center;">
                        <a href="conectar_whatsapp.php" class="btn btn-secondary"><i class="fas fa-sync"></i> Atualizar Status</a>
                        <a href="?action=logout" class="btn btn-primary" onclick="return confirm('Deseja realmente desconectar este WhatsApp?')"><i class="fas fa-sign-out-alt"></i> Desconectar Sessão</a>
                    </div>
                <?php else: ?>
                    <h2>Conectar WhatsApp</h2>

                    <!-- Tabs -->
                    <div class="auth-tabs">
                        <button class="auth-tab active" id="tabPairing" onclick="switchTab('pairing')">📱 Código por Número</button>
                        <button class="auth-tab" id="tabQR" onclick="switchTab('qr')">📷 QR Code</button>
                    </div>

                    <!-- Painel: Pairing Code -->
                    <div id="panel-pairing">
                        <p style="color:var(--text-dim); margin-bottom:16px;">Gere um código de 8 dígitos e digite no WhatsApp do celular. Resolve o novo sistema anti-automação do WhatsApp.</p>

                        <!-- Formulário -->
                        <div id="pairing-form">
                            <div class="phone-row">
                                <input class="phone-input" id="phoneInput" type="tel" placeholder="Ex: 5521999999999 (DDI+DDD+número)" />
                                <button class="btn btn-indigo" id="pairingBtn" onclick="requestPairingCode()"><i class="fas fa-key"></i> Gerar</button>
                            </div>
                            <p class="err-msg" id="pairingErr"></p>
                            <hr class="divider">
                            <div class="step-box"><div class="step-label">Passo 1</div><div class="step-desc">Digite seu número com DDI + DDD: <strong>5521999999999</strong> e clique em "Gerar"</div></div>
                            <div class="step-box"><div class="step-label">Passo 2</div><div class="step-desc">No celular: <strong>WhatsApp → ⋮ Menu → Aparelhos Conectados → Vincular com número de telefone</strong></div></div>
                            <div class="step-box"><div class="step-label">Passo 3</div><div class="step-desc">Digite o código de 8 dígitos que vai aparecer aqui na tela</div></div>
                        </div>

                        <!-- Código gerado -->
                        <div id="pairing-code-view" style="display:none;">
                            <p style="color:var(--text-dim); margin-bottom:4px;">Digite este código no WhatsApp do celular:</p>
                            <div class="pairing-code-display" id="pairingCodeDisplay">----</div>
                            <p style="color:var(--text-dim); font-size:0.85rem; margin-bottom:0;">📱 Número: <span id="pairingPhoneDisplay"></span></p>
                            <hr class="divider">
                            <div class="step-box"><div class="step-label">⏳ Aguardando conexão</div><div class="step-desc">No celular: <strong>WhatsApp → ⋮ Menu → Aparelhos Conectados → Vincular com número de telefone</strong> e digite o código acima.</div></div>
                            <p class="hint-warn"><i class="fas fa-clock"></i> O código expira em ~60 segundos. Se expirar, clique abaixo para gerar outro.</p>
                            <button class="btn btn-secondary" style="margin-top:14px; width:100%;" onclick="resetPairing()"><i class="fas fa-redo"></i> Gerar Novo Código</button>
                        </div>
                    </div>

                    <!-- Painel: QR Code -->
                    <div id="panel-qr" style="display:none;">
                        <p style="color:var(--text-dim); margin-bottom:20px;">Abra o WhatsApp no seu celular, vá em <strong>Aparelhos conectados → Conectar um aparelho</strong> e aponte a câmera para o QR Code abaixo.</p>
                        <div id="qr-area">
                            <div class="loader" id="qrLoader"></div>
                            <p id="qrMessage" style="color: var(--text-dim); font-size: 0.9rem;">Aguardando QR Code do servidor...</p>
                        </div>
                        <p class="hint-warn" style="margin-top:10px;"><i class="fas fa-exclamation-triangle"></i> Se aparecer "Continue no outro dispositivo", use a aba 📱 Código por Número.</p>
                    </div>

                    <div style="margin-top: 20px; display: flex; gap: 15px; justify-content: center;">
                        <a href="conectar_whatsapp.php" class="btn btn-secondary"><i class="fas fa-sync"></i> Atualizar QR Code</a>
                        <a href="?action=reset" class="btn btn-secondary" style="border-color: rgba(227,29,28,0.5); color: #ff8a8a;" onclick="return confirm('Isso vai apagar a sessão atual na API e gerar uma nova do zero. Tem certeza?')"><i class="fas fa-trash-alt"></i> Forçar Nova Sessão</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
    (function() {
        let currentQR = null;
        let polling = true;

        // --- Tab switching ---
        window.switchTab = function(tab) {
            document.getElementById('tabPairing').classList.toggle('active', tab === 'pairing');
            document.getElementById('tabQR').classList.toggle('active', tab === 'qr');
            document.getElementById('panel-pairing').style.display = tab === 'pairing' ? 'block' : 'none';
            document.getElementById('panel-qr').style.display = tab === 'qr' ? 'block' : 'none';
        };

        // --- Pairing Code ---
        window.requestPairingCode = async function() {
            const input = document.getElementById('phoneInput');
            const btn = document.getElementById('pairingBtn');
            const errEl = document.getElementById('pairingErr');
            const phone = input.value.replace(/\D/g, '');
            if (!phone || phone.length < 10) {
                errEl.textContent = 'Digite um número válido com DDI (ex: 5521999999999)';
                return;
            }
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando...';
            errEl.textContent = '';
            try {
                const resp = await fetch('?action=request_pairing_code', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ phone: phone })
                });
                const data = await resp.json();
                if (data.success && data.code) {
                    showPairingCode(data.code, phone);
                } else {
                    errEl.textContent = data.error || 'Erro ao gerar código. Tente novamente.';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-key"></i> Gerar';
                }
            } catch(e) {
                errEl.textContent = 'Erro de comunicação com o servidor.';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-key"></i> Gerar';
            }
        };

        function showPairingCode(code, phone) {
            var formatted = code.length === 8 ? code.substring(0,4) + '-' + code.substring(4) : code;
            document.getElementById('pairingCodeDisplay').textContent = formatted;
            document.getElementById('pairingPhoneDisplay').textContent = phone;
            document.getElementById('pairing-form').style.display = 'none';
            document.getElementById('pairing-code-view').style.display = 'block';
        }

        window.resetPairing = function() {
            document.getElementById('pairing-form').style.display = 'block';
            document.getElementById('pairing-code-view').style.display = 'none';
            var btn = document.getElementById('pairingBtn');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-key"></i> Gerar'; }
        };

        // --- QR + status polling ---
        async function checkQR() {
            if (!polling) return;
            try {
                var res = await fetch('?action=qr_status');
                var data = await res.json();

                if (data.connected) {
                    document.getElementById('statusBadge').className = 'status-badge connected';
                    document.getElementById('statusBadge').innerHTML = '<i class="fas fa-check-circle"></i> <span>Conectado</span>';
                    document.getElementById('main-area').innerHTML = '<h2>WhatsApp Ativo! \u2705</h2><p style="color:var(--text-dim);margin-bottom:25px;">O sistema est\u00e1 operando normalmente com o WhatsApp conectado.</p><div style="display:flex;gap:15px;justify-content:center;"><a href="conectar_whatsapp.php" class="btn btn-secondary"><i class="fas fa-sync"></i> Atualizar Status</a><a href="?action=logout" class="btn btn-primary" onclick="return confirm(\'Deseja realmente desconectar este WhatsApp?\')"><i class="fas fa-sign-out-alt"></i> Desconectar Sess\u00e3o</a></div>';
                    polling = false;
                    return;
                }

                // Atualizar pairing code se veio da API (outro dispositivo já gerou)
                if (data.pairingCode) {
                    var pv = document.getElementById('pairing-code-view');
                    if (pv && pv.style.display === 'none') {
                        showPairingCode(data.pairingCode, data.pairingPhone || '');
                    } else if (pv) {
                        var formatted = data.pairingCode.length === 8
                            ? data.pairingCode.substring(0,4) + '-' + data.pairingCode.substring(4)
                            : data.pairingCode;
                        document.getElementById('pairingCodeDisplay').textContent = formatted;
                    }
                }

                // Atualizar QR Code se aba ativa
                var qrArea = document.getElementById('qr-area');
                if (qrArea && data.qr && currentQR !== data.qr) {
                    currentQR = data.qr;
                    qrArea.innerHTML = '<div class="qr-container" id="qrCanvas"></div><p style="color:var(--text-dim);font-size:0.85rem;">Atualizando em tempo real...</p>';
                    new QRCode(document.getElementById('qrCanvas'), {
                        text: data.qr, width: 256, height: 256,
                        colorDark: '#000000', colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.H
                    });
                } else if (qrArea && !data.qr && currentQR !== null) {
                    currentQR = null;
                    qrArea.innerHTML = '<div class="loader"></div><p style="color:var(--text-dim);font-size:0.9rem;">Servidor reiniciando, aguarde...</p>';
                }
            } catch(e) {
                console.error('Erro ao checar status:', e);
            }
            setTimeout(checkQR, 3000);
        }

        // Só inicia polling se desconectado
        if (document.getElementById('panel-pairing')) {
            checkQR();
        }
    })();
    </script>
</body>
</html>
