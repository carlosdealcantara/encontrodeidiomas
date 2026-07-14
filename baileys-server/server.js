const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, Browsers, makeCacheableSignalKeyStore, PHONENUMBER_MCC } = require('@whiskeysockets/baileys');
const express = require('express');
const pino = require('pino');
const qrcode = require('qrcode-terminal');
const fs = require('fs');
const path = require('path');

// Configs
const PORT = 3000;
const DELAY_ENTRE_GRUPOS_MS = 5000;
const API_KEY = 'SenhaMeetups2026';

const app = express();
app.use(express.json());

// Setup Data Dir
const dataDir = path.join(__dirname, 'data');
if (!fs.existsSync(dataDir)) {
    fs.mkdirSync(dataDir, { recursive: true });
}

// Memory Queue & Jobs
let queue = [];
let jobs = {}; 
// jobs format: { jobId: { status: 'running', progress: { current: 0, total: 0 }, logs: [] } }

let sock;
let isConnected = false;
let isProcessingQueue = false;
let latestQR = null;
let latestPairingCode = null;
let pairingPhoneNumber = null;

// Expose QR code page without auth middleware (local access only via PHP proxy)
app.get('/qr', (req, res) => {
    if (req.query.json) {
        return res.json({ connected: isConnected, qr: latestQR, pairingCode: latestPairingCode, pairingPhone: pairingPhoneNumber });
    }

    res.send(`
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
                    max-width: 460px;
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
                .tabs { display: flex; gap: 8px; margin-bottom: 24px; background: #0d1117; border-radius: 10px; padding: 4px; }
                .tab-btn {
                    flex: 1; padding: 8px 12px; border: none; border-radius: 7px;
                    font-family: 'Inter', sans-serif; font-size: 0.85rem; font-weight: 500;
                    cursor: pointer; transition: all 0.2s; color: #6b7280; background: transparent;
                }
                .tab-btn.active { background: #4f46e5; color: white; }
                .tab-btn:hover:not(.active) { color: #d1d5db; background: #1f2937; }
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
                let activeTab = 'pairing';
                let pairingRequested = false;
                let pollTimer = null;

                function renderTabs() {
                    return '<div class="tabs">' +
                        '<button class="tab-btn ' + (activeTab==='pairing'?'active':'') + '" onclick="switchTab(\'pairing\')">📱 Código por Número</button>' +
                        '<button class="tab-btn ' + (activeTab==='qr'?'active':'') + '" onclick="switchTab(\'qr\')">📷 QR Code</button>' +
                        '</div>';
                }

                function switchTab(tab) {
                    activeTab = tab;
                    currentQR = null;
                    if (pollTimer) clearTimeout(pollTimer);
                    checkStatus();
                }

                async function requestPairingCode() {
                    const input = document.getElementById('phoneInput');
                    const btn = document.getElementById('pairingBtn');
                    const errEl = document.getElementById('pairingErr');
                    const phone = input.value.replace(/\D/g, '');
                    if (!phone || phone.length < 10) {
                        errEl.textContent = 'Digite um número válido com DDI (ex: 5511999999999)';
                        return;
                    }
                    btn.disabled = true;
                    btn.textContent = 'Gerando...';
                    errEl.textContent = '';
                    try {
                        const resp = await fetch('/request-pairing-code', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ phone: phone })
                        });
                        const data = await resp.json();
                        if (data.success) {
                            pairingRequested = true;
                            if (pollTimer) clearTimeout(pollTimer);
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
                    try {
                        const urlParams = new URLSearchParams(window.location.search);
                        const fetchUrl = urlParams.has('action') ? '?action=qr&json=true' : '/qr?json=true';
                        const resp = await fetch(fetchUrl);
                        const data = await resp.json();
                        const contentDiv = document.getElementById('content');

                        if (data.connected) {
                            contentDiv.innerHTML = '<div class="success-icon">✅</div><h1 class="msg-success">WhatsApp Conectado!</h1><p class="subtitle">O servidor Baileys está ativo e pronto para enviar mensagens.</p>';
                            return;
                        }

                        if (activeTab === 'pairing') {
                            if (data.pairingCode) {
                                var code = data.pairingCode;
                                var formatted = code.length === 8 ? code.substring(0,4) + '-' + code.substring(4) : code;
                                contentDiv.innerHTML = renderTabs() +
                                    '<h1>Digite este código no WhatsApp</h1>' +
                                    '<p class="subtitle">No celular: <strong>WhatsApp → ⋮ Menu → Aparelhos Conectados → Vincular com número de telefone</strong></p>' +
                                    '<div class="pairing-code-box">' + formatted + '</div>' +
                                    '<p class="subtitle" style="margin-bottom:0">📱 Número: ' + (data.pairingPhone || '') + '</p>' +
                                    '<hr class="divider">' +
                                    '<div class="step"><div class="step-num">⏳ Aguardando conexão</div><div class="step-text">O código expira em ~60 segundos. Se não conectar, clique abaixo para gerar outro.</div></div>' +
                                    '<button class="btn-primary" style="margin-top:16px; width:100%;" onclick="resetPairing()">🔄 Gerar Novo Código</button>';
                            } else if (pairingRequested) {
                                contentDiv.innerHTML = renderTabs() +
                                    '<div class="loader"></div>' +
                                    '<p style="color:#6b7280; margin-top:10px;">Gerando código de pareamento...</p>';
                            } else {
                                contentDiv.innerHTML = renderTabs() +
                                    '<h1>Vincular por Número</h1>' +
                                    '<p class="subtitle">Gera um código de 8 dígitos para digitar no WhatsApp do celular.<br>Resolve o problema do "Continue no outro dispositivo".</p>' +
                                    '<div class="phone-input-row">' +
                                        '<input class="phone-input" id="phoneInput" type="tel" placeholder="Ex: 5511999999999" />' +
                                        '<button class="btn-primary" id="pairingBtn" onclick="requestPairingCode()">Gerar Código</button>' +
                                    '</div>' +
                                    '<p id="pairingErr" class="msg-error"></p>' +
                                    '<hr class="divider">' +
                                    '<div class="step"><div class="step-num">Passo 1</div><div class="step-text">Digite seu número com DDI + DDD (ex: <strong>5511999999999</strong>) e clique em "Gerar Código"</div></div>' +
                                    '<div class="step"><div class="step-num">Passo 2</div><div class="step-text">No celular: <strong>WhatsApp → ⋮ Menu → Aparelhos Conectados → Vincular com número de telefone</strong></div></div>' +
                                    '<div class="step"><div class="step-num">Passo 3</div><div class="step-text">Digite o código de 8 dígitos que aparecerá aqui na tela do celular</div></div>';
                            }
                        } else {
                            if (data.qr) {
                                if (currentQR !== data.qr) {
                                    currentQR = data.qr;
                                    contentDiv.innerHTML = renderTabs() +
                                        '<h1>Escaneie o QR Code</h1>' +
                                        '<p class="subtitle">WhatsApp → ⋮ Menu → Aparelhos Conectados → Conectar um aparelho</p>' +
                                        '<div id="qrcode"></div>' +
                                        '<p class="timer">⚠️ Se aparecer "Continue no outro dispositivo", use a aba 📱 Código por Número.</p>';
                                    new QRCode(document.getElementById("qrcode"), {
                                        text: data.qr, width: 240, height: 240,
                                        colorDark: "#000000", colorLight: "#ffffff",
                                        correctLevel: QRCode.CorrectLevel.H
                                    });
                                }
                            } else {
                                currentQR = null;
                                contentDiv.innerHTML = renderTabs() +
                                    '<div class="loader"></div>' +
                                    '<p style="color:#6b7280; margin-top:10px;">Aguardando QR Code do WhatsApp...</p>';
                            }
                        }
                    } catch (e) {
                        console.error("Erro ao checar status", e);
                    }
                    pollTimer = setTimeout(checkStatus, 3000);
                }

                function resetPairing() {
                    pairingRequested = false;
                    if (pollTimer) clearTimeout(pollTimer);
                    checkStatus();
                }

                checkStatus();
            </script>
        </body>
        </html>
    `);
});

app.get('/connection-status', (req, res) => {
    res.json({ connected: isConnected });
});

// Endpoint para gerar Pairing Code (sem auth, acesso público como /qr)
app.post('/request-pairing-code', async (req, res) => {
    try {
        const { phone } = req.body;
        if (!phone) {
            return res.status(400).json({ success: false, error: 'Número de telefone obrigatório' });
        }
        const cleanPhone = phone.replace(/\D/g, '');
        if (cleanPhone.length < 10 || cleanPhone.length > 15) {
            return res.status(400).json({ success: false, error: 'Número inválido' });
        }
        if (isConnected) {
            return res.status(400).json({ success: false, error: 'WhatsApp já está conectado.' });
        }
        if (!sock || !sock.requestPairingCode) {
            return res.status(503).json({ success: false, error: 'Servidor ainda iniciando. Aguarde alguns segundos e tente novamente.' });
        }
        console.log('[Pairing Code] Solicitando código para número: ' + cleanPhone);
        const code = await sock.requestPairingCode(cleanPhone);
        latestPairingCode = code;
        pairingPhoneNumber = cleanPhone;
        console.log('[Pairing Code] Código gerado: ' + code);
        return res.json({ success: true, code });
    } catch (e) {
        console.error('[Pairing Code] Erro:', e);
        return res.status(500).json({ success: false, error: e.message || 'Erro ao gerar código de pareamento' });
    }
});

// Auth middleware
app.use((req, res, next) => {
    if (req.path === '/qr' || req.path === '/connection-status' || req.path === '/status' || req.path === '/request-pairing-code') {
        return next();
    }
    const key = req.headers['apikey'] || req.headers['authorization'];
    if (key !== API_KEY && key !== `Bearer ${API_KEY}`) {
        return res.status(401).json({ error: 'Unauthorized' });
    }
    next();
});

const mentoriaPlugin = require('./mentoria');
mentoriaPlugin.initRoutes(app, dataDir);

// Helpers for logging
function saveJobsToFile() {
    fs.writeFileSync(path.join(dataDir, 'jobs.json'), JSON.stringify(jobs, null, 2));
}

function loadJobsFromFile() {
    try {
        const file = path.join(dataDir, 'jobs.json');
        if (fs.existsSync(file)) {
            jobs = JSON.parse(fs.readFileSync(file, 'utf8'));
            // Reset any running jobs to error if server crashed
            for (const id in jobs) {
                if (jobs[id].status === 'running') {
                    jobs[id].status = 'error';
                    jobs[id].logs.push({ type: 'error', message: '[ERRO CRÍTICO] Servidor reiniciou antes do término.' });
                }
            }
        }
    } catch (e) {
        console.error('Error loading jobs:', e);
    }
}

loadJobsFromFile();

function addLog(jobId, type, message) {
    if (!jobs[jobId]) return;
    jobs[jobId].logs.push({ type, message });
    console.log(`[JOB ${jobId}] [${type.toUpperCase()}] ${message}`);
    saveJobsToFile();
}

function updateProgress(jobId, current) {
    if (!jobs[jobId]) return;
    jobs[jobId].progress.current = current;
    saveJobsToFile();
}

function setJobStatus(jobId, status) {
    if (!jobs[jobId]) return;
    jobs[jobId].status = status;
    saveJobsToFile();
}

// Connect to WhatsApp
async function connectToWhatsApp() {
    const { state, saveCreds } = await useMultiFileAuthState(path.join(dataDir, 'auth_info_baileys'));
    const { version, isLatest } = await fetchLatestBaileysVersion();
    console.log(`Using WA v${version.join('.')}, isLatest: ${isLatest}`);

    sock = makeWASocket({
        version,
        auth: {
            creds: state.creds,
            // Usar cache para chaves de sinal melhora estabilidade
            keys: makeCacheableSignalKeyStore(state.keys, pino({ level: 'silent' }))
        },
        printQRInTerminal: false,
        logger: pino({ level: 'silent' }),
        // macOS Chrome fingerprint: menos suspeito para o WhatsApp do que 'Ubuntu'
        browser: Browsers.macOS('Chrome'),
        mobile: false,
        // Necessário para sessão estável — sem isso, algumas mensagens não chegam
        getMessage: async (key) => {
            return { conversation: '' };
        }
    });

    sock.ev.on('connection.update', (update) => {
        const { connection, lastDisconnect, qr } = update;
        
        if (qr) {
            latestQR = qr;
            console.log('\n======================================');
            console.log('SCAN THIS QR CODE TO CONNECT WHATSAPP:');
            qrcode.generate(qr, { small: true });
            console.log('======================================\n');
        }

        if (connection === 'close') {
            isConnected = false;
            latestQR = null;
            latestPairingCode = null;
            pairingPhoneNumber = null;
            const statusCode = lastDisconnect?.error?.output?.statusCode;
            const shouldReconnect = statusCode !== DisconnectReason.loggedOut;
            console.log('Connection closed. StatusCode:', statusCode, '| Reconnecting:', shouldReconnect);
            
            if (shouldReconnect) {
                setTimeout(connectToWhatsApp, 3000);
            } else {
                // 401/loggedOut durante Pairing Code pode ser transitório:
                // O WhatsApp leva até 8s para completar o handshake de credenciais
                // após o usuário confirmar no celular. Aguardamos antes de limpar.
                console.log('[RECOVERY] Status 401 recebido. Aguardando 8s antes de limpar credenciais...');
                setTimeout(() => {
                    // Verificar se não conectou nesse intervalo
                    if (!isConnected) {
                        console.log('[AUTO-RECOVERY] Nenhuma conexão estabelecida. Limpando credenciais e gerando novo QR...');
                        try {
                            fs.rmSync(path.join(dataDir, 'auth_info_baileys'), { recursive: true, force: true });
                        } catch (e) {
                            console.error('Erro ao limpar auth:', e);
                        }
                        setTimeout(connectToWhatsApp, 1000);
                    } else {
                        console.log('[RECOVERY] Conexão já restabelecida. Auth preservada.');
                    }
                }, 8000);
            }
        } else if (connection === 'open') {
            isConnected = true;
            latestQR = null;
            latestPairingCode = null;
            pairingPhoneNumber = null;
            console.log('WhatsApp connection opened successfully!');
            mentoriaPlugin.setSock(sock);
            if (!isProcessingQueue) {
                processQueue();
            }
        }
    });

    sock.ev.on('creds.update', saveCreds);
    sock.ev.on('messages.upsert', mentoriaPlugin.handleMessages);
    sock.ev.on('group-participants.update', mentoriaPlugin.handleParticipants);
}

// Queue Processor
async function processQueue() {
    if (isProcessingQueue || !isConnected) return;
    isProcessingQueue = true;

    try {
        while (isConnected && queue.length > 0) {
            const item = queue.shift();
            const { jobId, number, text, index } = item;
            
            addLog(jobId, 'process', `[${index}/${jobs[jobId].progress.total}] Enviando para: ${number}`);
            
            try {
                let jid = number.includes('@') ? number : `${number}@s.whatsapp.net`;
                
                // Resolução inteligente de JID para números brasileiros
                // sock.onWhatsApp() consulta o servidor oficial do WhatsApp
                // e retorna o JID correto independentemente do formato do número
                if (!number.includes('@')) {
                    try {
                        const results = await sock.onWhatsApp(number);
                        if (results && results.length > 0 && results[0].exists) {
                            jid = results[0].jid;
                            console.log(`[JID Resolver] ${number} -> ${jid}`);
                        } else {
                            // Número não encontrado como está. Tentar variações BR:
                            let altNumber = null;
                            if (number.startsWith('55') && number.length === 13) {
                                // Tem 13 dígitos (com 9): tentar sem o 9 (remover 5º dígito)
                                altNumber = number.substring(0, 4) + number.substring(5);
                            } else if (number.startsWith('55') && number.length === 12) {
                                // Tem 12 dígitos (sem 9): tentar com o 9 (inserir após DDD)
                                altNumber = number.substring(0, 4) + '9' + number.substring(4);
                            }
                            
                            if (altNumber) {
                                const altResults = await sock.onWhatsApp(altNumber);
                                if (altResults && altResults.length > 0 && altResults[0].exists) {
                                    jid = altResults[0].jid;
                                    console.log(`[JID Resolver] ${number} (alt: ${altNumber}) -> ${jid}`);
                                } else {
                                    console.log(`[JID Resolver] AVISO: Número ${number} não encontrado no WhatsApp.`);
                                }
                            } else {
                                console.log(`[JID Resolver] AVISO: Número ${number} não encontrado no WhatsApp.`);
                            }
                        }
                    } catch (resolveErr) {
                        console.log(`[JID Resolver] Erro ao resolver ${number}: ${resolveErr.message}. Usando formato padrão.`);
                    }
                }

                const startTime = Date.now();
                
                const msgPayload = { text };
                
                if (item.mentions && Array.isArray(item.mentions)) {
                    msgPayload.mentions = item.mentions;
                }
                
                // Injeção manual de linkPreview desativada para dar lugar ao generateHighQualityLinkPreview nativo
                // if (item.linkPreview) { ... }
                
                await sock.sendMessage(jid, msgPayload, { generateHighQualityLinkPreview: true });
                
                const duration = ((Date.now() - startTime) / 1000).toFixed(2);
                addLog(jobId, 'success', `[SUCESSO] Mensagem entregue. Tempo: ${duration}s.`);
                
            } catch (err) {
                const errorMsg = err.message || 'Erro desconhecido';
                addLog(jobId, 'error', `[FALHA] Erro no disparo: ${errorMsg}`);
                
                if (errorMsg.includes('Connection Closed') || errorMsg.includes('Stream Errored')) {
                    addLog(jobId, 'info', '[PAUSA] Conexão instável. Pausando fila.');
                    queue.unshift(item); // Put it back
                    isConnected = false;
                    break;
                }
            }
            
            updateProgress(jobId, index);

            // Check if job completed
            const isLastOfJob = queue.findIndex(q => q.jobId === jobId) === -1;
            if (isLastOfJob) {
                addLog(jobId, 'info', 'Teste de Fila Concluído!');
                setJobStatus(jobId, 'completed');
            } else {
                addLog(jobId, 'info', 'Aguardando 5 segundos para limpar a memória (Garbage Collector)...');
                await new Promise(r => setTimeout(r, DELAY_ENTRE_GRUPOS_MS));
            }
        }
    } finally {
        isProcessingQueue = false;
    }
}

// === ROUTES ===

// === EMERGENCY: Clear Queue Route ===
app.post('/clear-queue', (req, res) => {
    const cleared = queue.length;
    queue.length = 0; // Esvazia o array in-memory imediatamente
    
    // Marca todos os jobs 'running' como abortados
    for (const id in jobs) {
        if (jobs[id].status === 'running') {
            jobs[id].status = 'error';
            jobs[id].logs.push({ type: 'error', message: '[ABORTADO] Fila esvaziada manualmente via /clear-queue.' });
        }
    }
    saveJobsToFile();
    
    console.log(`[EMERGÊNCIA] Fila esvaziada! ${cleared} itens removidos.`);
    res.json({ success: true, cleared });
});

// Bulk Queue Route (Used by test_cadence and crons)
app.post('/send-bulk', (req, res) => {
    try {
        if (!isConnected) {
            return res.status(503).json({ success: false, error: 'WhatsApp não está conectado. Escaneie o QR Code primeiro.' });
        }

        const { groups, textMessage } = req.body;
        if (!Array.isArray(groups) || !textMessage?.text) {
            return res.status(400).json({ success: false, error: 'Invalid payload' });
        }
        
        const jobId = Math.random().toString(36).substring(2, 10);
        
        jobs[jobId] = {
            status: 'running',
            progress: { current: 0, total: groups.length },
            logs: []
        };
        saveJobsToFile();
        
        addLog(jobId, 'info', 'Iniciando teste de cadência no servidor Node.js...');

        groups.forEach((groupInfo, idx) => {
            // Support both object {group_id: "...", nome: "..."} or simple string array
            const number = groupInfo.group_id || groupInfo;
            queue.push({
                jobId,
                number,
                text: textMessage.text,
                index: idx + 1
            });
        });
        
        if (isConnected && !isProcessingQueue) {
            processQueue();
        }
        
        res.json({ success: true, jobId, queued: groups.length });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// Universal Single Send Route
app.post('/send', (req, res) => {
    if (!isConnected) return res.status(503).json({ success: false, error: 'WhatsApp não conectado. Escaneie o QR Code primeiro.' });
    try {
        const { to, message, source, linkPreview } = req.body;
        if (!to || !message) return res.status(400).json({ success: false, error: 'Parâmetros "to" e "message" são obrigatórios' });
        
        const jobId = 'unit_' + Math.random().toString(36).substring(2, 10);
        jobs[jobId] = { status: 'running', progress: { current: 0, total: 1 }, logs: [] };
        
        console.log(`[Universal Endpoint] Enfileirando mensagem para ${to} (Source: ${source || 'desconhecida'})`);
        
        queue.push({
            jobId,
            number: to,
            text: message,
            index: 1,
            linkPreview
        });
        
        if (isConnected && !isProcessingQueue) {
            processQueue();
        }
        
        res.json({ success: true, jobId, queued: 1 });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// Send Mention Route
app.post('/send-mention', (req, res) => {
    if (!isConnected) return res.status(503).json({ success: false, error: 'WhatsApp não conectado.' });
    try {
        const { to, message, mentions } = req.body;
        if (!to || !message || !Array.isArray(mentions)) return res.status(400).json({ success: false, error: 'Parâmetros "to", "message" e "mentions" são obrigatórios' });
        
        const jobId = 'ment_' + Math.random().toString(36).substring(2, 10);
        jobs[jobId] = { status: 'running', progress: { current: 0, total: 1 }, logs: [] };
        
        queue.push({
            jobId,
            number: to,
            text: message,
            index: 1,
            mentions
        });
        
        if (isConnected && !isProcessingQueue) {
            processQueue();
        }
        
        res.json({ success: true, jobId, queued: 1 });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// Logout Route
app.delete('/logout', async (req, res) => {
    try {
        if (sock) await sock.logout();
        isConnected = false;
        res.json({ success: true });
        setTimeout(() => process.exit(0), 1000); // Força reinício limpo do container
    } catch (e) {
        res.status(500).json({ success: false, error: e.message });
    }
});

// Reset Route
app.post('/reset', async (req, res) => {
    try {
        if (sock) await sock.logout();
        isConnected = false;
        latestQR = null;
        latestPairingCode = null;
        pairingPhoneNumber = null;
        fs.rmSync(path.join(dataDir, 'auth_info_baileys'), { recursive: true, force: true });
        res.json({ success: true });
        setTimeout(() => process.exit(0), 1000); // Força reinício limpo do container
    } catch (e) {
        res.status(500).json({ success: false, error: e.message });
    }
});

// Status route (matches test_cadence.php expected format)
app.get('/status', (req, res) => {
    const { jobId } = req.query;
    
    if (jobId && jobs[jobId]) {
        return res.json(jobs[jobId]);
    }
    
    // Default fallback if no jobId requested or found
    res.json({
        status: isConnected ? 'idle' : 'disconnected',
        progress: { current: 0, total: queue.length },
        logs: []
    });
});

app.listen(PORT, '0.0.0.0', () => {
    console.log(`Baileys server listening on 0.0.0.0:${PORT}`);
    connectToWhatsApp();
});
