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

// Auth middleware
app.use((req, res, next) => {
    const key = req.headers['apikey'] || req.headers['authorization'];
    if (key !== API_KEY && key !== `Bearer ${API_KEY}`) {
        return res.status(401).json({ error: 'Unauthorized' });
    }
    next();
});

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
    if (isConnected) {
        return res.send(`
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>WhatsApp Conectado</title>
                <style>
                    body {
                        background: #0b0f19;
                        color: #10b981;
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        min-height: 100vh;
                        margin: 0;
                    }
                    .card {
                        background: #161c2d;
                        border: 1px solid #242c3d;
                        padding: 40px;
                        border-radius: 16px;
                        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
                        text-align: center;
                    }
                    h1 { margin-bottom: 10px; }
                    p { color: #9ca3af; }
                </style>
            </head>
            <body>
                <div class="card">
                    <h1>✅ WhatsApp Conectado!</h1>
                    <p>O servidor Baileys está ativo e pronto para disparos.</p>
                </div>
            </body>
            </html>
        `);
    }

    if (!latestQR) {
        return res.send(`
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Aguardando QR Code</title>
                <style>
                    body {
                        background: #0b0f19;
                        color: #f3f4f6;
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        min-height: 100vh;
                        margin: 0;
                    }
                    .card {
                        background: #161c2d;
                        border: 1px solid #242c3d;
                        padding: 40px;
                        border-radius: 16px;
                        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
                        text-align: center;
                    }
                    .loader {
                        border: 4px solid #1f2937;
                        border-top: 4px solid #4f46e5;
                        border-radius: 50%;
                        width: 40px;
                        height: 40px;
                        animation: spin 1s linear infinite;
                        margin: 20px auto;
                    }
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                </style>
                <meta http-equiv="refresh" content="3">
            </head>
            <body>
                <div class="card">
                    <h1>Aguardando QR Code...</h1>
                    <div class="loader"></div>
                    <p>Esta página irá atualizar automaticamente a cada 3 segundos.</p>
                </div>
            </body>
            </html>
        `);
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
                    min-height: 100vh;
                    margin: 0;
                }
                .card {
                    background: #161c2d;
                    border: 1px solid #242c3d;
                    padding: 30px;
                    border-radius: 16px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
                    text-align: center;
                }
                #qrcode {
                    background: white;
                    padding: 20px;
                    border-radius: 8px;
                    display: inline-block;
                    margin: 20px 0;
                }
                p { color: #9ca3af; margin: 5px 0; }
            </style>
        </head>
        <body>
            <div class="card">
                <h1>Escaneie o QR Code</h1>
                <p>Abra o WhatsApp > Aparelhos conectados > Conectar um aparelho</p>
                <div id="qrcode"></div>
                <p style="font-size: 0.85rem; color: #6b7280;">A página atualizará automaticamente após conectar.</p>
            </div>
            <script>
                new QRCode(document.getElementById("qrcode"), {
                    text: ${JSON.stringify(latestQR)},
                    width: 256,
                    height: 256,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });
                
                // Auto-refresh when connected
                setInterval(async () => {
                    try {
                        const res = await fetch('/connection-status');
                        const data = await res.json();
                        if (data.connected) {
                            window.location.reload();
                        }
                    } catch(e) {}
                }, 3000);
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
    if (req.path === '/qr' || req.path === '/connection-status') {
        return next();
    }
    const key = req.headers['apikey'] || req.headers['authorization'];
    if (key !== API_KEY && key !== `Bearer ${API_KEY}`) {
        return res.status(401).json({ error: 'Unauthorized' });
    }
    next();
});

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
        logger: pino({ level: 'silent' }), // silence noisy logs
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
            const shouldReconnect = lastDisconnect?.error?.output?.statusCode !== DisconnectReason.loggedOut;
            console.log('Connection closed:', lastDisconnect?.error, 'Reconnecting:', shouldReconnect);
            if (shouldReconnect) {
                setTimeout(connectToWhatsApp, 3000);
            }
        } else if (connection === 'open') {
            isConnected = true;
            latestQR = null;
            console.log('WhatsApp connection opened successfully!');
            if (!isProcessingQueue) {
                processQueue();
            }
        }
    });

    sock.ev.on('creds.update', saveCreds);
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
                const jid = number.includes('@') ? number : `${number}@s.whatsapp.net`;
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

// Single send route (for compatibility if needed)
app.post('/send-text', async (req, res) => {
    if (!isConnected) return res.status(503).json({ success: false, error: 'WhatsApp not connected' });
    try {
        const { number, textMessage } = req.body;
        if (!number || !textMessage?.text) return res.status(400).json({ success: false, error: 'Invalid payload' });
        
        const jid = number.includes('@') ? number : `${number}@s.whatsapp.net`;
        const result = await sock.sendMessage(jid, { text: textMessage.text });
        
        res.json({ success: true, messageId: result?.key?.id });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
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
