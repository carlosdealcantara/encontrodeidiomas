#!/usr/bin/env python3
"""
Patch para adicionar suporte ao Pairing Code no server.js do Baileys.
Resolve o problema do WhatsApp Linked Devices 2.0 que pede "Continue no outro dispositivo".
"""

import re

file_path = '/home/ubuntu/encontrodeidiomas/baileys-server/server.js'

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# ============================================================
# PATCH 1: Adicionar variável latestPairingCode e phoneNumber
# Após a linha: let latestQR = null;
# ============================================================
old_vars = "let latestQR = null;"
new_vars = """let latestQR = null;
let latestPairingCode = null;
let pairingPhoneNumber = null;
let usePairingCode = false;"""

content = content.replace(old_vars, new_vars, 1)

# ============================================================
# PATCH 2: Atualizar o endpoint /qr para incluir pairingCode na resposta JSON
# e atualizar a página HTML para suportar as duas opções
# ============================================================
old_qr_json = "        return res.json({ connected: isConnected, qr: latestQR });"
new_qr_json = "        return res.json({ connected: isConnected, qr: latestQR, pairingCode: latestPairingCode, pairingPhone: pairingPhoneNumber });"

content = content.replace(old_qr_json, new_qr_json, 1)

# ============================================================
# PATCH 3: Substituir todo o HTML do /qr por versão atualizada com pairing code
# ============================================================
old_html_start = "    res.send(`\n        <!DOCTYPE html>"
old_html_end = "    `);\n});\n\napp.get('/connection-status'"

# Encontrar e substituir o bloco HTML
start_idx = content.find(old_html_start)
end_idx = content.find(old_html_end)

if start_idx == -1 or end_idx == -1:
    print("ERRO: Não encontrou o bloco HTML para substituir!")
    exit(1)

new_html = """    res.send(`
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Conectar WhatsApp - Servidor Bot</title>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    background: #0b0f19;
                    color: #f3f4f6;
                    font-family: 'Inter', 'Segoe UI', sans-serif;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    padding: 20px;
                }
                .card {
                    background: #161c2d;
                    border: 1px solid #242c3d;
                    padding: 32px 28px;
                    border-radius: 20px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.6);
                    text-align: center;
                    width: 100%;
                    max-width: 440px;
                }
                h1 { font-size: 1.4rem; font-weight: 700; margin-bottom: 6px; }
                .subtitle { color: #6b7280; font-size: 0.88rem; margin-bottom: 24px; line-height: 1.5; }
                #qrcode { background: white; padding: 16px; border-radius: 12px; display: inline-block; margin: 16px 0; }
                .loader {
                    border: 3px solid #242c3d;
                    border-top: 3px solid #4f46e5;
                    border-radius: 50%;
                    width: 36px; height: 36px;
                    animation: spin 1s linear infinite;
                    margin: 20px auto;
                }
                @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

                /* Tabs */
                .tabs { display: flex; gap: 8px; margin-bottom: 24px; background: #0d1117; border-radius: 10px; padding: 4px; }
                .tab-btn {
                    flex: 1; padding: 8px 12px; border: none; border-radius: 7px;
                    font-family: 'Inter', sans-serif; font-size: 0.85rem; font-weight: 500;
                    cursor: pointer; transition: all 0.2s; color: #6b7280; background: transparent;
                }
                .tab-btn.active { background: #4f46e5; color: white; }
                .tab-btn:hover:not(.active) { color: #d1d5db; background: #1f2937; }

                /* Pairing code */
                .pairing-code-box {
                    background: #0d1117;
                    border: 2px solid #4f46e5;
                    border-radius: 14px;
                    padding: 20px;
                    margin: 16px 0;
                    letter-spacing: 6px;
                    font-size: 2.2rem;
                    font-weight: 700;
                    color: #a5b4fc;
                    font-family: monospace;
                }
                .phone-input-row { display: flex; gap: 8px; margin: 16px 0; }
                .phone-input {
                    flex: 1; padding: 10px 14px;
                    background: #0d1117; border: 1px solid #374151;
                    border-radius: 10px; color: #f3f4f6;
                    font-family: 'Inter', sans-serif; font-size: 0.95rem;
                    outline: none;
                }
                .phone-input:focus { border-color: #4f46e5; }
                .phone-input::placeholder { color: #4b5563; }
                .btn-primary {
                    padding: 10px 18px; background: #4f46e5; color: white;
                    border: none; border-radius: 10px; font-family: 'Inter', sans-serif;
                    font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
                    white-space: nowrap;
                }
                .btn-primary:hover { background: #4338ca; }
                .btn-primary:disabled { background: #374151; cursor: not-allowed; }

                .step { background: #0d1117; border-radius: 10px; padding: 12px 14px; margin: 8px 0; text-align: left; }
                .step-num { color: #4f46e5; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; }
                .step-text { color: #9ca3af; font-size: 0.85rem; margin-top: 2px; line-height: 1.4; }
                .step-text strong { color: #f3f4f6; }
                .timer { color: #6b7280; font-size: 0.8rem; margin-top: 8px; }
                .success-icon { font-size: 3rem; margin-bottom: 12px; }
                .msg-success { color: #10b981; }
                .msg-error { color: #ef4444; font-size: 0.85rem; margin-top: 8px; }
                .divider { border: none; border-top: 1px solid #1f2937; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="card" id="mainCard">
                <div id="content">
                    <div class="loader"></div>
                    <p style="color:#6b7280; margin-top:10px;">Carregando sistema...</p>
                </div>
            </div>

            <script>
                let currentQR = null;
                let qrCodeObj = null;
                let activeTab = 'pairing'; // pairing | qr
                let pairingRequested = false;
                let pollTimer = null;

                function renderTabs() {
                    return \`<div class="tabs">
                        <button class="tab-btn \${activeTab==='pairing'?'active':''}" onclick="switchTab('pairing')">📱 Código por Número</button>
                        <button class="tab-btn \${activeTab==='qr'?'active':''}" onclick="switchTab('qr')">📷 QR Code</button>
                    </div>\`;
                }

                function switchTab(tab) {
                    activeTab = tab;
                    checkStatus();
                }

                async function requestPairingCode() {
                    const input = document.getElementById('phoneInput');
                    const btn = document.getElementById('pairingBtn');
                    const errEl = document.getElementById('pairingErr');
                    const phone = input.value.replace(/\\D/g, '');
                    if (!phone || phone.length < 10) {
                        errEl.textContent = 'Digite um número válido com DDI (ex: 5511999999999)';
                        return;
                    }
                    btn.disabled = true;
                    btn.textContent = 'Gerando...';
                    errEl.textContent = '';
                    try {
                        const res = await fetch('/request-pairing-code', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ phone })
                        });
                        const data = await res.json();
                        if (data.success) {
                            pairingRequested = true;
                            checkStatus();
                        } else {
                            errEl.textContent = data.error || 'Erro ao gerar código. Tente novamente.';
                            btn.disabled = false;
                            btn.textContent = 'Gerar Código';
                        }
                    } catch(e) {
                        errEl.textContent = 'Erro de comunicação com o servidor.';
                        btn.disabled = false;
                        btn.textContent = 'Gerar Código';
                    }
                }

                async function checkStatus() {
                    if (pollTimer) clearTimeout(pollTimer);
                    try {
                        const urlParams = new URLSearchParams(window.location.search);
                        const fetchUrl = urlParams.has('action') ? '?action=qr&json=true' : '/qr?json=true';
                        const res = await fetch(fetchUrl);
                        const data = await res.json();
                        const contentDiv = document.getElementById('content');

                        if (data.connected) {
                            contentDiv.innerHTML = \`
                                <div class="success-icon">✅</div>
                                <h1 class="msg-success">WhatsApp Conectado!</h1>
                                <p class="subtitle">O servidor Baileys está ativo e pronto para enviar mensagens.</p>
                            \`;
                            return;
                        }

                        if (activeTab === 'pairing') {
                            if (data.pairingCode) {
                                // Exibe o código
                                const formatted = data.pairingCode.match(/.{1,4}/g)?.join('-') || data.pairingCode;
                                contentDiv.innerHTML = \`
                                    \${renderTabs()}
                                    <h1>Digite este código no WhatsApp</h1>
                                    <p class="subtitle">No celular: <strong>WhatsApp → Menu → Aparelhos conectados → Vincular com número de telefone</strong></p>
                                    <div class="pairing-code-box">\${formatted}</div>
                                    <p class="subtitle">📱 Número: \${data.pairingPhone || ''}</p>
                                    <hr class="divider">
                                    <div class="step"><div class="step-num">⏳ Aguardando</div><div class="step-text">O código expira em ~60 segundos. Se não conectar, clique no botão abaixo para gerar um novo.</div></div>
                                    <button class="btn-primary" style="margin-top:16px; width:100%;" onclick="resetPairing()">🔄 Gerar Novo Código</button>
                                \`;
                            } else if (pairingRequested) {
                                contentDiv.innerHTML = \`
                                    \${renderTabs()}
                                    <div class="loader"></div>
                                    <p style="color:#6b7280; margin-top:10px;">Gerando código de pareamento...</p>
                                \`;
                            } else {
                                // Formulário para digitar número
                                contentDiv.innerHTML = \`
                                    \${renderTabs()}
                                    <h1>Vincular por Número</h1>
                                    <p class="subtitle">Use seu número de telefone para gerar um código de 8 dígitos.<br>Funciona mesmo com o novo sistema anti-automação do WhatsApp.</p>
                                    <div class="phone-input-row">
                                        <input class="phone-input" id="phoneInput" type="tel" placeholder="Ex: 5511999999999" />
                                        <button class="btn-primary" id="pairingBtn" onclick="requestPairingCode()">Gerar Código</button>
                                    </div>
                                    <p id="pairingErr" class="msg-error"></p>
                                    <hr class="divider">
                                    <div class="step"><div class="step-num">Passo 1</div><div class="step-text">Digite seu número com DDI + DDD (ex: <strong>5511999999999</strong>) e clique em "Gerar Código"</div></div>
                                    <div class="step"><div class="step-num">Passo 2</div><div class="step-text">No celular: <strong>WhatsApp → ⋮ Menu → Aparelhos Conectados → Vincular com número de telefone</strong></div></div>
                                    <div class="step"><div class="step-num">Passo 3</div><div class="step-text">Digite o código de 8 dígitos que aparecerá aqui na tela do WhatsApp do celular</div></div>
                                \`;
                            }
                        } else {
                            // Tab QR
                            if (data.qr) {
                                if (currentQR !== data.qr) {
                                    currentQR = data.qr;
                                    contentDiv.innerHTML = \`
                                        \${renderTabs()}
                                        <h1>Escaneie o QR Code</h1>
                                        <p class="subtitle">WhatsApp → ⋮ Menu → Aparelhos Conectados → Conectar um aparelho</p>
                                        <div id="qrcode"></div>
                                        <p class="timer">⚠️ O QR Code expira em ~20 segundos. Se o WhatsApp mostrar "Continue no outro dispositivo", use a aba "Código por Número" ao invés.</p>
                                    \`;
                                    new QRCode(document.getElementById("qrcode"), {
                                        text: data.qr,
                                        width: 240, height: 240,
                                        colorDark: "#000000", colorLight: "#ffffff",
                                        correctLevel: QRCode.CorrectLevel.H
                                    });
                                }
                            } else {
                                currentQR = null;
                                contentDiv.innerHTML = \`
                                    \${renderTabs()}
                                    <div class="loader"></div>
                                    <p style="color:#6b7280; margin-top:10px;">Aguardando QR Code do WhatsApp...</p>
                                \`;
                            }
                        }
                    } catch (e) {
                        console.error("Erro ao checar status", e);
                    }
                    pollTimer = setTimeout(checkStatus, 3000);
                }

                function resetPairing() {
                    pairingRequested = false;
                    checkStatus();
                }

                checkStatus();
            </script>
        </body>
        </html>
    `);
});"""

content = content[:start_idx] + new_html + "\n\n" + content[end_idx + len(old_html_end):]

# ============================================================
# PATCH 4: Adicionar endpoint /request-pairing-code ANTES do middleware de auth
# (deve ficar após /connection-status e antes do app.use auth middleware)
# ============================================================
old_auth_middleware = "// Auth middleware\napp.use((req, res, next) => {"
new_pairing_endpoint = """// Endpoint para gerar Pairing Code (sem auth, acesso público como /qr)
app.post('/request-pairing-code', async (req, res) => {
    try {
        const { phone } = req.body;
        if (!phone) {
            return res.status(400).json({ success: false, error: 'Número de telefone obrigatório' });
        }
        const cleanPhone = phone.replace(/\\D/g, '');
        if (cleanPhone.length < 10 || cleanPhone.length > 15) {
            return res.status(400).json({ success: false, error: 'Número inválido' });
        }
        if (isConnected) {
            return res.status(400).json({ success: false, error: 'WhatsApp já está conectado.' });
        }
        if (!sock || !sock.requestPairingCode) {
            return res.status(503).json({ success: false, error: 'Servidor ainda iniciando. Aguarde alguns segundos.' });
        }
        console.log(`[Pairing Code] Solicitando código para número: ${cleanPhone}`);
        const code = await sock.requestPairingCode(cleanPhone);
        latestPairingCode = code;
        pairingPhoneNumber = cleanPhone;
        usePairingCode = true;
        console.log(`[Pairing Code] Código gerado: ${code}`);
        return res.json({ success: true, code });
    } catch (e) {
        console.error('[Pairing Code] Erro:', e);
        return res.status(500).json({ success: false, error: e.message || 'Erro ao gerar código de pareamento' });
    }
});

// Auth middleware
app.use((req, res, next) => {"""

content = content.replace(old_auth_middleware, new_pairing_endpoint, 1)

# ============================================================
# PATCH 5: Atualizar o middleware de auth para incluir /request-pairing-code na whitelist
# ============================================================
old_auth_check = "    if (req.path === '/qr' || req.path === '/connection-status' || req.path === '/status') {"
new_auth_check = "    if (req.path === '/qr' || req.path === '/connection-status' || req.path === '/status' || req.path === '/request-pairing-code') {"

content = content.replace(old_auth_check, new_auth_check, 1)

# ============================================================
# PATCH 6: Atualizar connectToWhatsApp para suporte ao pairing code
# Adicionar: mobile: false e handler de pairing code
# ============================================================
old_make_socket = """    sock = makeWASocket({
        version,
        auth: state,
        printQRInTerminal: false,
        logger: pino({ level: 'silent' }),
        browser: ['Ubuntu', 'Chrome', '20.0.04']
    });"""

new_make_socket = """    // Pairing Code requer mobile: false e auth com número registrado
    sock = makeWASocket({
        version,
        auth: state,
        printQRInTerminal: false,
        logger: pino({ level: 'silent' }),
        browser: ['Ubuntu', 'Chrome', '20.0.04'],
        // Necessário para o Pairing Code funcionar no Linked Devices 2.0
        mobile: false
    });"""

content = content.replace(old_make_socket, new_make_socket, 1)

# ============================================================
# PATCH 7: Limpar pairingCode quando conectar ou desconectar
# ============================================================
old_connection_open = """        } else if (connection === 'open') {
            isConnected = true;
            latestQR = null;
            console.log('WhatsApp connection opened successfully!');"""

new_connection_open = """        } else if (connection === 'open') {
            isConnected = true;
            latestQR = null;
            latestPairingCode = null;
            pairingPhoneNumber = null;
            usePairingCode = false;
            console.log('WhatsApp connection opened successfully!');"""

content = content.replace(old_connection_open, new_connection_open, 1)

old_connection_close = """            isConnected = false;
            latestQR = null;"""

new_connection_close = """            isConnected = false;
            latestQR = null;
            latestPairingCode = null;"""

content = content.replace(old_connection_close, new_connection_close, 1)

# Salvar
with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("✅ Patch aplicado com sucesso!")
print("Verificando patches...")

# Verificações
checks = [
    ('latestPairingCode', 'Variável pairingCode'),
    ('request-pairing-code', 'Endpoint /request-pairing-code'),
    ('requestPairingCode', 'Chamada sock.requestPairingCode'),
    ('pairingCode', 'Resposta JSON com pairingCode'),
    ('Código por Número', 'Tab "Código por Número" na UI'),
    ('Vincular com número de telefone', 'Instruções do WhatsApp'),
]
for check_str, label in checks:
    if check_str in content:
        print(f"  ✅ {label}")
    else:
        print(f"  ❌ FALTOU: {label}")
