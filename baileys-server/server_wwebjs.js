const { Client, LocalAuth } = require('whatsapp-web.js');
const express = require('express');
const qrcode = require('qrcode-terminal');
const fs = require('fs');
const path = require('path');

// ============================================================
// CONFIGURAÇÃO
// ============================================================
const CHROMIUM_PATH = '/usr/bin/chromium';
const PORT = 3000;
const DELAY_ENTRE_GRUPOS_MS = 5000;
const API_KEY = 'SenhaMeetups2026';

const app = express();
app.use(express.json({ limit: '10mb' }));

const dataDir = path.join(__dirname, 'data');
if (!fs.existsSync(dataDir)) fs.mkdirSync(dataDir, { recursive: true });

// === STATE ===
let queue = [];
let jobs = {};
let waClient = null;
let isConnected = false;
let isProcessingQueue = false;
let latestQR = null;

// === MENTORIA PLUGIN ===
const mentoriaPlugin = require('./mentoria');

// ============================================================
// ROTAS PÚBLICAS (sem autenticação)
// ============================================================

app.get('/qr', (req, res) => {
    if (req.query.json) {
        return res.json({ connected: isConnected, qr: latestQR });
    }
    // HTML simples para a rota /qr
    res.send(`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>WhatsApp Bot</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body{background:#0b0f19;color:#f3f4f6;font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh}
        .card{background:#161c2d;border:1px solid #242c3d;padding:32px;border-radius:20px;text-align:center;max-width:420px;width:100%}
        #qrcode{background:white;padding:12px;border-radius:10px;display:inline-block;margin:16px 0}
        .loader{border:3px solid #242c3d;border-top:3px solid #4f46e5;border-radius:50%;width:36px;height:36px;animation:spin 1s linear infinite;margin:20px auto}
        @keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
    </style></head><body><div class="card">
    <div id="c"><div class="loader"></div><p>Carregando...</p></div>
    </div><script>
    let q=null;
    async function poll(){
        const d=await(await fetch('/qr?json=true')).json();
        const c=document.getElementById('c');
        if(d.connected){c.innerHTML='<h2 style="color:#10b981">✅ Conectado!</h2>';return;}
        if(d.qr&&d.qr!==q){q=d.qr;c.innerHTML='<h2>Escaneie no WhatsApp</h2><div id="qrcode"></div>';new QRCode(document.getElementById("qrcode"),{text:d.qr,width:240,height:240});}
        else if(!d.qr&&!q){c.innerHTML='<div class="loader"></div><p>Aguardando QR...</p>';}
        setTimeout(poll,3000);
    }
    poll();
    </script></body></html>`);
});

app.get('/connection-status', (req, res) => res.json({ connected: isConnected }));

// ============================================================
// MIDDLEWARE DE AUTENTICAÇÃO
// ============================================================

app.use((req, res, next) => {
    const publicPaths = ['/qr', '/connection-status', '/status'];
    if (publicPaths.includes(req.path)) return next();
    const key = req.headers['apikey'] || req.headers['authorization'];
    if (key !== API_KEY && key !== `Bearer ${API_KEY}`) {
        return res.status(401).json({ error: 'Unauthorized' });
    }
    next();
});

mentoriaPlugin.initRoutes(app, dataDir);

// ============================================================
// GERENCIAMENTO DE JOBS
// ============================================================

function saveJobs() {
    try { fs.writeFileSync(path.join(dataDir, 'jobs.json'), JSON.stringify(jobs, null, 2)); } catch(e) {}
}

function loadJobs() {
    try {
        const file = path.join(dataDir, 'jobs.json');
        if (fs.existsSync(file)) {
            jobs = JSON.parse(fs.readFileSync(file, 'utf8'));
            for (const id in jobs) {
                if (jobs[id].status === 'running') {
                    jobs[id].status = 'error';
                    jobs[id].logs.push({ type: 'error', message: '[ERRO] Servidor reiniciou antes do término.' });
                }
            }
        }
    } catch(e) {}
}

function addLog(jobId, type, message) {
    if (!jobs[jobId]) return;
    jobs[jobId].logs.push({ type, message });
    console.log(`[JOB ${jobId}] [${type.toUpperCase()}] ${message}`);
    saveJobs();
}

function updateProgress(jobId, current) {
    if (!jobs[jobId]) return;
    jobs[jobId].progress.current = current;
    saveJobs();
}

function setJobStatus(jobId, status) {
    if (!jobs[jobId]) return;
    jobs[jobId].status = status;
    saveJobs();
}

loadJobs();

// ============================================================
// PROCESSADOR DE FILA
// ============================================================

async function processQueue() {
    if (isProcessingQueue || !isConnected) return;
    isProcessingQueue = true;
    try {
        while (isConnected && queue.length > 0) {
            const item = queue.shift();
            const { jobId, number, text, index } = item;
            addLog(jobId, 'process', `[${index}/${jobs[jobId].progress.total}] Enviando para: ${number}`);
            try {
                // Normaliza JID: wwebjs usa @c.us para contatos e @g.us para grupos
                let jid = number;
                if (!number.includes('@')) {
                    jid = number + '@c.us';
                } else if (number.includes('@s.whatsapp.net')) {
                    jid = number.replace('@s.whatsapp.net', '@c.us');
                }
                
                const startTime = Date.now();
                await waClient.sendMessage(jid, text);
                const duration = ((Date.now() - startTime) / 1000).toFixed(2);
                addLog(jobId, 'success', `[SUCESSO] Mensagem entregue. Tempo: ${duration}s.`);
            } catch (err) {
                const errorMsg = err.message || 'Erro desconhecido';
                addLog(jobId, 'error', `[FALHA] Erro no disparo: ${errorMsg}`);
                if (errorMsg.includes('not connected') || errorMsg.includes('Session closed') || errorMsg.includes('Connection Closed')) {
                    addLog(jobId, 'info', '[PAUSA] Conexão instável. Pausando fila.');
                    queue.unshift(item);
                    isConnected = false;
                    break;
                }
            }
            updateProgress(jobId, index);
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

// ============================================================
// ADAPTADOR: converte APIs do wwebjs para o formato do mentoria.js (Baileys-compatible)
// ============================================================

function buildSockAdapter() {
    const wid = waClient.info?.wid?._serialized || '';
    const phoneNumber = wid.replace('@c.us', '').replace('@s.whatsapp.net', '');

    return {
        user: { id: `${phoneNumber}:0@s.whatsapp.net` },

        sendMessage: async (jid, payload) => {
            const normalJid = jid.includes('@s.whatsapp.net')
                ? jid.replace('@s.whatsapp.net', '@c.us')
                : jid;
            if (typeof payload === 'string') {
                return waClient.sendMessage(normalJid, payload);
            }
            if (payload.react) {
                try {
                    const chat = await waClient.getChatById(normalJid);
                    const msgs = await chat.fetchMessages({ limit: 20 });
                    const target = msgs.find(m => m.id._serialized === payload.react.key?.id);
                    if (target) return target.react(payload.react.text);
                } catch(e) { console.error('Erro ao reagir:', e.message); }
                return;
            }
            if (payload.text) {
                return waClient.sendMessage(normalJid, payload.text);
            }
        },

        groupMetadata: async (groupId) => {
            const normalGroupId = groupId.includes('@s.whatsapp.net')
                ? groupId.replace('@s.whatsapp.net', '@g.us')
                : groupId;
            const chat = await waClient.getChatById(normalGroupId);
            return {
                participants: (chat.participants || []).map(p => ({
                    id: p.id._serialized,
                    admin: p.isAdmin ? 'admin' : (p.isSuperAdmin ? 'superadmin' : null)
                }))
            };
        },

        groupFetchAllParticipating: async () => {
            const chats = await waClient.getChats();
            const groups = {};
            for (const chat of chats) {
                if (chat.isGroup) {
                    groups[chat.id._serialized] = {
                        id: chat.id._serialized,
                        subject: chat.name
                    };
                }
            }
            return groups;
        },

        groupParticipantsUpdate: async (groupId, participants, action) => {
            const normalGroupId = groupId.includes('@s.whatsapp.net')
                ? groupId.replace('@s.whatsapp.net', '@g.us')
                : groupId;
            const chat = await waClient.getChatById(normalGroupId);
            const normalParticipants = participants.map(p =>
                p.includes('@s.whatsapp.net') ? p.replace('@s.whatsapp.net', '@c.us') : p
            );
            if (action === 'remove') {
                return chat.removeParticipants(normalParticipants);
            }
        }
    };
}

// ============================================================
// CLIENTE WHATSAPP-WEB.JS
// ============================================================

async function connectToWhatsApp() {
    console.log('[WWebJS] Inicializando cliente Chromium...');

    waClient = new Client({
        authStrategy: new LocalAuth({
            dataPath: path.join(dataDir, 'wwebjs_auth')
        }),
        puppeteer: {
            executablePath: CHROMIUM_PATH,
            protocolTimeout: 120000,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--no-first-run',
                '--no-default-browser-check',
                '--disable-extensions',
                '--disable-background-timer-throttling',
                '--disable-backgrounding-occluded-windows',
                '--disable-renderer-backgrounding',
                '--disable-features=TranslateUI',
                '--js-flags=--max_old_space_size=512'
            ],
            headless: true
        }
    });

    waClient.on('qr', (qr) => {
        latestQR = qr;
        console.log('[WWebJS] QR Code gerado! Acesse o painel admin para escanear.');
        qrcode.generate(qr, { small: true });
    });

    waClient.on('authenticated', () => {
        console.log('[WWebJS] Sessão autenticada com sucesso!');
        latestQR = null;
    });

    waClient.on('auth_failure', (msg) => {
        console.error('[WWebJS] Falha na autenticação:', msg);
        latestQR = null;
        setTimeout(connectToWhatsApp, 10000);
    });

    waClient.on('ready', () => {
        console.log('[WWebJS] WhatsApp pronto e conectado!');
        isConnected = true;
        latestQR = null;

        const sockAdapter = buildSockAdapter();
        mentoriaPlugin.setSock(sockAdapter);

        if (!isProcessingQueue) processQueue();
    });

    waClient.on('disconnected', (reason) => {
        console.log('[WWebJS] Desconectado:', reason);
        isConnected = false;
        latestQR = null;
        console.log('[WWebJS] Reconectando em 5s...');
        setTimeout(connectToWhatsApp, 5000);
    });

    let isProcessingMessage = false;
    // Evento de mensagem: adapta formato wwebjs → formato Baileys esperado pelo mentoria.js
    // OTIMIZAÇÃO: usa dados brutos do msg para evitar chamadas caras ao Chrome (getChat/getContact)
    waClient.on('message', async (msg) => {
        // Filtra fora do grupo antes de adquirir o lock
        const chatId = msg.from;
        if (!chatId || !chatId.endsWith('@g.us')) return;

        if (isProcessingMessage) return;
        isProcessingMessage = true;
        try {
            // Dados disponíveis sem chamar o Chrome
            const participantJid = msg.author || (msg.fromMe ? null : chatId);
            const pushName = msg._data?.notifyName || msg._data?.pushname || 'Desconhecido';
            
            const adaptedMsg = {
                key: {
                    remoteJid: chatId,
                    fromMe: msg.fromMe,
                    participant: participantJid,
                    id: msg.id._serialized
                },
                message: {
                    conversation: msg.body || '',
                    extendedTextMessage: msg.body ? { text: msg.body } : null,
                    imageMessage: (msg.type === 'image') ? {} : null,
                    videoMessage: (msg.type === 'video') ? {} : null,
                    audioMessage: (msg.type === 'audio') ? {} : null,
                    pttMessage: (msg.type === 'ptt') ? {} : null,
                    reactionMessage: (msg.type === 'reaction') ? { text: msg.body } : null,
                    documentMessage: (msg.type === 'document') ? { mimetype: msg.mimetype || '' } : null
                },
                pushName: pushName,
                messageTimestamp: msg.timestamp
            };
            
            await mentoriaPlugin.handleMessages({ messages: [adaptedMsg], type: 'notify' });
        } catch (e) {
            console.error('[WWebJS] Erro ao processar mensagem:', e.message);
        } finally {
            isProcessingMessage = false;
        }
    });

    waClient.on('group_join', async (notification) => {
        try {
            const participants = notification.recipientIds || [notification.id];
            await mentoriaPlugin.handleParticipants({
                id: notification.chatId,
                participants: participants.map(p => p.includes('@') ? p : p + '@c.us'),
                action: 'add'
            });
        } catch (e) {
            console.error('[WWebJS] Erro ao processar entrada no grupo:', e.message);
        }
    });

    await waClient.initialize();
}

// ============================================================
// ROTAS DA API
// ============================================================

app.post('/clear-queue', (req, res) => {
    const cleared = queue.length;
    queue.length = 0;
    for (const id in jobs) {
        if (jobs[id].status === 'running') {
            jobs[id].status = 'error';
            jobs[id].logs.push({ type: 'error', message: '[ABORTADO] Fila esvaziada manualmente via /clear-queue.' });
        }
    }
    saveJobs();
    console.log(`[EMERGÊNCIA] Fila esvaziada! ${cleared} itens removidos.`);
    res.json({ success: true, cleared });
});

app.post('/send-bulk', (req, res) => {
    try {
        if (!isConnected) {
            return res.status(503).json({ success: false, error: 'WhatsApp não está conectado. Aguarde a conexão.' });
        }
        const { groups, textMessage } = req.body;
        if (!Array.isArray(groups) || !textMessage?.text) {
            return res.status(400).json({ success: false, error: 'Invalid payload' });
        }
        const jobId = Math.random().toString(36).substring(2, 10);
        jobs[jobId] = { status: 'running', progress: { current: 0, total: groups.length }, logs: [] };
        saveJobs();
        addLog(jobId, 'info', 'Iniciando teste de cadência no servidor Node.js...');
        groups.forEach((groupInfo, idx) => {
            const number = groupInfo.group_id || groupInfo;
            queue.push({ jobId, number, text: textMessage.text, index: idx + 1 });
        });
        if (isConnected && !isProcessingQueue) processQueue();
        res.json({ success: true, jobId, queued: groups.length });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

app.post('/send', (req, res) => {
    if (!isConnected) return res.status(503).json({ success: false, error: 'WhatsApp não conectado.' });
    try {
        const { to, message, source } = req.body;
        if (!to || !message) return res.status(400).json({ success: false, error: 'Parâmetros "to" e "message" são obrigatórios' });
        const jobId = 'unit_' + Math.random().toString(36).substring(2, 10);
        jobs[jobId] = { status: 'running', progress: { current: 0, total: 1 }, logs: [] };
        console.log(`[Universal Endpoint] Enfileirando mensagem para ${to} (Source: ${source || 'desconhecida'})`);
        queue.push({ jobId, number: to, text: message, index: 1 });
        if (isConnected && !isProcessingQueue) processQueue();
        res.json({ success: true, jobId, queued: 1 });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

app.delete('/logout', async (req, res) => {
    try {
        if (waClient) await waClient.logout();
        isConnected = false;
        res.json({ success: true });
        setTimeout(() => process.exit(0), 1000);
    } catch (e) {
        res.status(500).json({ success: false, error: e.message });
    }
});

app.post('/reset', async (req, res) => {
    try {
        if (waClient) { try { await waClient.logout(); } catch(e) {} }
        isConnected = false;
        latestQR = null;
        try { fs.rmSync(path.join(dataDir, 'wwebjs_auth'), { recursive: true, force: true }); } catch(e) {}
        res.json({ success: true });
        setTimeout(() => process.exit(0), 1000);
    } catch (e) {
        res.status(500).json({ success: false, error: e.message });
    }
});

app.post('/request-pairing-code', (req, res) => {
    res.status(400).json({ success: false, error: 'Modo Chromium ativo: use o QR Code no painel admin.' });
});

app.get('/status', (req, res) => {
    const { jobId } = req.query;
    if (jobId && jobs[jobId]) return res.json(jobs[jobId]);
    res.json({ status: isConnected ? 'idle' : 'disconnected', progress: { current: 0, total: queue.length }, logs: [] });
});

// ============================================================
// START
// ============================================================

app.listen(PORT, '0.0.0.0', () => {
    console.log(`[WWebJS] Servidor escutando na porta ${PORT}`);
    connectToWhatsApp();
});
