const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion } = require('@whiskeysockets/baileys');
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

// Expose QR code page without auth middleware (local access only via PHP proxy)
app.get('/qr', (req, res) => {
    if (req.query.json) {
        return res.json({ connected: isConnected, qr: latestQR });
    }

    res.send(`
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Conectar WhatsApp</title>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
            <style>
                body {
                    background: #0b0f19;
                    color: #f3f4f6;
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    height: 100vh;
                    overflow: hidden;
                    margin: 0;
                }
                .card {
                    background: #161c2d;
                    border: 1px solid #242c3d;
                    padding: 20px;
                    border-radius: 16px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
                    text-align: center;
                    transition: all 0.3s ease;
                }
                #qrcode {
                    background: white;
                    padding: 20px;
                    border-radius: 8px;
                    display: inline-block;
                    margin: 20px 0;
                }
                .loader {
                    border: 4px solid #242c3d;
                    border-top: 4px solid #4f46e5;
                    border-radius: 50%;
                    width: 40px;
                    height: 40px;
                    animation: spin 1s linear infinite;
                    margin: 20px auto;
                }
                @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                p { color: #9ca3af; margin: 5px 0; font-size: 0.95rem; }
                h1 { margin-top: 0; font-size: 1.5rem; }
            </style>
        </head>
        <body>
            <div class="card" id="mainCard">
                <div id="content">
                    <div class="loader"></div>
                    <p>Carregando sistema...</p>
                </div>
            </div>
            
            <script>
                let currentQR = null;
                let qrCodeObj = null;

                async function checkStatus() {
                    try {
                        const urlParams = new URLSearchParams(window.location.search);
                        const fetchUrl = urlParams.has('action') ? '?action=qr&json=true' : '/qr?json=true';
                        
                        const res = await fetch(fetchUrl);
                        const data = await res.json();
                        
                        const contentDiv = document.getElementById('content');
                        
                        if (data.connected) {
                            contentDiv.innerHTML = '<h1 style="color: #10b981;">✅ WhatsApp Conectado!</h1><p>O servidor Baileys está ativo e pronto.</p>';
                            return; // Stop polling
                        }
                        
                        if (data.qr) {
                            if (currentQR !== data.qr) {
                                currentQR = data.qr;
                                contentDiv.innerHTML = '<h1>Escaneie o QR Code</h1><p>Abra o WhatsApp > Aparelhos conectados</p><div id="qrcode"></div><p style="font-size: 0.85rem; color: #6b7280;">Atualizando em tempo real...</p>';
                                qrCodeObj = new QRCode(document.getElementById("qrcode"), {
                                    text: data.qr,
                                    width: 256,
                                    height: 256,
                                    colorDark : "#000000",
                                    colorLight : "#ffffff",
                                    correctLevel : QRCode.CorrectLevel.H
                                });
                            }
                        } else {
                            currentQR = null;
                            contentDiv.innerHTML = '<div class="loader"></div><p>Aguardando QR Code do WhatsApp...</p>';
                        }
                    } catch (e) {
                        console.error("Erro ao checar status", e);
                    }
                    setTimeout(checkStatus, 3000);
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

// Auth middleware
app.use((req, res, next) => {
    if (req.path === '/qr' || req.path === '/connection-status' || req.path === '/status') {
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
    const { state, saveCreds } = await useMultiFileAuthState(path.join(dataDir, 'auth'));
    const { version, isLatest } = await fetchLatestBaileysVersion();
    console.log(`Using WA v${version.join('.')}, isLatest: ${isLatest}`);

    sock = makeWASocket({
        version,
        auth: state,
        printQRInTerminal: false,
        logger: pino({ level: 'silent' }),
        browser: ['Ubuntu', 'Chrome', '20.0.04']
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
            const statusCode = lastDisconnect?.error?.output?.statusCode;
            const shouldReconnect = statusCode !== DisconnectReason.loggedOut;
            console.log('Connection closed:', lastDisconnect?.error, 'StatusCode:', statusCode, 'Reconnecting:', shouldReconnect);
            
            if (shouldReconnect) {
                setTimeout(connectToWhatsApp, 3000);
            } else {
                // Desconectado pelo celular (401/loggedOut): limpar auth e gerar novo QR
                console.log('[AUTO-RECOVERY] Sessão expirada/deslogada. Limpando credenciais e gerando novo QR...');
                try {
                    fs.rmSync(path.join(dataDir, 'auth'), { recursive: true, force: true });
                } catch (e) {
                    console.error('Erro ao limpar auth:', e);
                }
                setTimeout(connectToWhatsApp, 2000);
            }
        if (connection === 'open') {
            isConnected = true;
            latestQR = null;
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
                
                await sock.sendMessage(jid, { text });
                
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
        const { to, message, source } = req.body;
        if (!to || !message) return res.status(400).json({ success: false, error: 'Parâmetros "to" e "message" são obrigatórios' });
        
        const jobId = 'unit_' + Math.random().toString(36).substring(2, 10);
        jobs[jobId] = { status: 'running', progress: { current: 0, total: 1 }, logs: [] };
        
        console.log(`[Universal Endpoint] Enfileirando mensagem para ${to} (Source: ${source || 'desconhecida'})`);
        
        queue.push({
            jobId,
            number: to,
            text: message,
            index: 1
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
        fs.rmSync(path.join(dataDir, 'auth'), { recursive: true, force: true });
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
