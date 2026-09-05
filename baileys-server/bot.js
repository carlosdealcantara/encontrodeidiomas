const fs = require('fs');
const path = require('path');

// Módulos por escopo — cada um cuida exclusivamente do seu universo
const adminCmds          = require('./modules/admin_cmds');
const communityGlobalMod = require('./modules/community_global');
const mentoriaMod        = require('./modules/mentoria');

let sock    = null;
let dataDir = '';

// Proxy seguro para garantir que referências antigas em callbacks (ex: setTimeouts)
// sempre acessem a instância mais atualizada do socket após reconexões.
const safeSock = new Proxy({}, {
    get: function(target, prop) {
        if (!sock) throw new Error("Socket disconnected");
        if (typeof sock[prop] === 'function') {
            return (...args) => sock[prop](...args);
        }
        return sock[prop];
    }
});

// Deduplication: impede contar o mesmo evento duas vezes
// (Baileys às vezes dispara 2 eventos para mensagens de mídia)
let processedMessageIds = new Set();
let processedIdsDate    = '';

// Cache de admins de grupo para evitar buscar metadata a cada mensagem
let groupAdminsCache     = {};
let groupAdminsCacheTime = {};

// ─── INFRAESTRUTURA COMPARTILHADA ─────────────────────────────────────────────

function getTodayDate() {
    const formatter = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Sao_Paulo' });
    return formatter.format(new Date()); // YYYY-MM-DD
}

function getActivityFile()          { return path.join(dataDir, 'activity_log.json'); }
function getConfigFile()            { return path.join(dataDir, 'mentoria_config.json'); }
function getConfigBackupFile()      { return path.join(dataDir, 'mentoria_config.backup.json'); }
function getCommunityConfigFile()   { return path.join(dataDir, 'community_config.json'); }
function getCommunityActivityFile() { return path.join(dataDir, 'community_activity_log.json'); }

function loadCommunityConfig() {
    try {
        const file = getCommunityConfigFile();
        if (fs.existsSync(file)) return JSON.parse(fs.readFileSync(file, 'utf8'));
    } catch (e) { console.error('Error loading community config:', e); }
    return { groups: {}, templates: {} };
}

function saveCommunityConfig(config) {
    fs.writeFileSync(getCommunityConfigFile(), JSON.stringify(config, null, 2));
}

function loadCommunityActivity() {
    try {
        const file = getCommunityActivityFile();
        if (fs.existsSync(file)) return JSON.parse(fs.readFileSync(file, 'utf8'));
    } catch (e) { console.error('Error loading community activity:', e); }
    return {};
}

function saveCommunityActivity(data) {
    fs.writeFileSync(getCommunityActivityFile(), JSON.stringify(data, null, 2));
}

function loadConfig() {
    try {
        const file = getConfigFile();
        if (fs.existsSync(file)) {
            const config = JSON.parse(fs.readFileSync(file, 'utf8'));
            // ⚠️ SAFETY CHECK: se groups está vazio mas existe backup, auto-restaura
            const groupCount    = Object.keys(config.groups || {}).length;
            const groupsWithJid = Object.values(config.groups || {}).filter(g => g.jid && g.jid.trim() !== '').length;
            if (groupsWithJid === 0) {
                const backupFile = getConfigBackupFile();
                if (fs.existsSync(backupFile)) {
                    const backup = JSON.parse(fs.readFileSync(backupFile, 'utf8'));
                    const backupGroupCount = Object.values(backup.groups || {}).filter(g => g.jid && g.jid.trim() !== '').length;
                    if (backupGroupCount > 0) {
                        console.warn(`[CONFIG] ⚠️ GRUPOS AUSENTES no config principal (${groupCount} entradas, 0 com JID). Restaurando ${backupGroupCount} grupos do backup automaticamente!`);
                        config.groups = backup.groups;
                        fs.writeFileSync(file, JSON.stringify(config, null, 2));
                    }
                } else {
                    console.warn('[CONFIG] ⚠️ ATENÇÃO: Nenhum grupo cadastrado e nenhum backup disponível. O bot vai IGNORAR todas as mensagens!');
                }
            }
            return config;
        }
    } catch (e) { console.error('Error loading mentoria config:', e); }
    return { admin_jid: '556192666148@s.whatsapp.net', groups: {}, templates: {} };
}

function saveConfig(config) {
    // SAFETY: nunca deixa grupos da comunidade entrarem no config da mentoria
    const cleanGroups = {};
    for (const [key, val] of Object.entries(config.groups || {})) {
        if (!val.is_community_group) cleanGroups[key] = val;
    }
    config.groups = cleanGroups;
    fs.writeFileSync(getConfigFile(), JSON.stringify(config, null, 2));
    const groupsWithJid = Object.values(config.groups || {}).filter(g => g.jid && g.jid.trim() !== '').length;
    if (groupsWithJid > 0) {
        fs.writeFileSync(getConfigBackupFile(), JSON.stringify(config, null, 2));
        console.log(`[CONFIG] Backup salvo com ${groupsWithJid} grupos configurados.`);
    }
}

function loadActivity() {
    try {
        const file = getActivityFile();
        if (fs.existsSync(file)) return JSON.parse(fs.readFileSync(file, 'utf8'));
    } catch (e) { console.error('Error loading activity:', e); }
    return {};
}

function saveActivity(data) {
    fs.writeFileSync(getActivityFile(), JSON.stringify(data, null, 2));
}

// ─── FILA DE ESCRITA (MENTORIA) ───────────────────────────────────────────────

const writeQueue = [];
let isSaving     = false;

async function processWriteQueue() {
    if (isSaving || writeQueue.length === 0) return;
    isSaving = true;
    const events = writeQueue.splice(0, writeQueue.length);
    try {
        const data = loadActivity();
        for (const event of events) {
            const { groupJid, senderJid, senderName, type, date } = event;
            if (!data[date]) data[date] = {};
            if (!data[date][groupJid]) data[date][groupJid] = {};
            if (!data[date][groupJid][senderJid]) {
                data[date][groupJid][senderJid] = {
                    name: senderName, messages: 0, reactions_given: 0,
                    images_sent: 0, audios_sent: 0,
                    first_message_at: new Date().toISOString(),
                    last_message_at:  new Date().toISOString(),
                };
            }
            if (type === 'message')  data[date][groupJid][senderJid].messages        += 1;
            else if (type === 'reaction') data[date][groupJid][senderJid].reactions_given += 1;
            else if (type === 'image') {
                data[date][groupJid][senderJid].images_sent = (data[date][groupJid][senderJid].images_sent || 0) + 1;
                data[date][groupJid][senderJid].messages   += 1;
            } else if (type === 'audio') {
                data[date][groupJid][senderJid].audios_sent = (data[date][groupJid][senderJid].audios_sent || 0) + 1;
                data[date][groupJid][senderJid].messages   += 1;
            }
            if (data[date][groupJid][senderJid].name === 'Desconhecido' && senderName && senderName !== 'Desconhecido') {
                data[date][groupJid][senderJid].name = senderName;
            }
            data[date][groupJid][senderJid].last_message_at = new Date().toISOString();
        }
        saveActivity(data);
    } catch (e) {
        console.error('Error processing write queue:', e);
    } finally {
        isSaving = false;
        if (writeQueue.length > 0) setTimeout(processWriteQueue, 0);
    }
}

function logActivity(groupJid, senderJid, senderName, type) {
    const date = getTodayDate();
    writeQueue.push({ groupJid, senderJid, senderName, type, date });
    processWriteQueue();
}

// ─── FILA DE ESCRITA (COMMUNITY) ──────────────────────────────────────────────

const communityWriteQueue = [];
let isSavingCommunity     = false;

async function processCommunityQueue() {
    if (isSavingCommunity || communityWriteQueue.length === 0) return;
    isSavingCommunity = true;
    const events = communityWriteQueue.splice(0, communityWriteQueue.length);
    try {
        const data = loadCommunityActivity();
        for (const event of events) {
            const { groupJid, senderJid, senderName, type, date } = event;
            if (!data[date]) data[date] = {};
            if (!data[date][groupJid]) data[date][groupJid] = {};
            if (!data[date][groupJid][senderJid]) {
                data[date][groupJid][senderJid] = {
                    name: senderName, messages: 0, reactions_given: 0,
                    images_sent: 0, audios_sent: 0,
                    first_message_at: new Date().toISOString(),
                    last_message_at:  new Date().toISOString(),
                };
            }
            if (type === 'message')       data[date][groupJid][senderJid].messages        += 1;
            else if (type === 'reaction') data[date][groupJid][senderJid].reactions_given += 1;
            else if (type === 'image') {
                data[date][groupJid][senderJid].images_sent = (data[date][groupJid][senderJid].images_sent || 0) + 1;
                data[date][groupJid][senderJid].messages   += 1;
            } else if (type === 'audio') {
                data[date][groupJid][senderJid].audios_sent = (data[date][groupJid][senderJid].audios_sent || 0) + 1;
                data[date][groupJid][senderJid].messages   += 1;
            }
            if (data[date][groupJid][senderJid].name === 'Desconhecido' && senderName && senderName !== 'Desconhecido') {
                data[date][groupJid][senderJid].name = senderName;
            }
            data[date][groupJid][senderJid].last_message_at = new Date().toISOString();
        }
        saveCommunityActivity(data);
    } catch (e) {
        console.error('Error processing community write queue:', e);
    } finally {
        isSavingCommunity = false;
        if (communityWriteQueue.length > 0) setTimeout(processCommunityQueue, 0);
    }
}

function logCommunityActivity(groupJid, senderJid, senderName, type) {
    const date = getTodayDate();
    communityWriteQueue.push({ groupJid, senderJid, senderName, type, date });
    processCommunityQueue();
}

// ─── HANDLER DE MENSAGENS (DISPATCHER) ────────────────────────────────────────

async function handleMessages({ messages, type }) {
    if (type !== 'notify') return;
    if (!sock) return;

    const config    = loadConfig();
    const adminJid  = config.admin_jid || '556192666148@s.whatsapp.net';
    const extraAdminJids = config.extra_admin_jids || [];
    const excludedJids = new Set([adminJid, ...extraAdminJids]);

    // Reset deduplication Set diariamente
    const currentDate = getTodayDate();
    if (currentDate !== processedIdsDate) {
        processedMessageIds = new Set();
        processedIdsDate    = currentDate;
    }

    for (const msg of messages) {
        const groupJid = msg.key.remoteJid;
        if (!groupJid?.endsWith('@g.us')) continue; // Só grupos

        // === DEBUG LOG (temporário) ===
        {
            const rawMsg  = msg.message?.ephemeralMessage?.message || msg.message?.viewOnceMessageV2?.message || msg.message?.viewOnceMessage?.message || msg.message;
            const rawText = rawMsg?.conversation || rawMsg?.extendedTextMessage?.text || '';
            const participant = msg.key.participant || 'n/a';
            if (rawText.length > 0) {
                console.log(`[DEBUG-ALL] group: ${groupJid}, fromMe: ${msg.key.fromMe}, participant: ${participant}, text: '${rawText}'`);
            }
        }

        // Extrai info básica para comandos globais
        const participantJid = (msg.key.participant || msg.key.remoteJid).replace(/:\d+@/, '@');
        const isMasterAdmin  = msg.key.fromMe || excludedJids.has(participantJid) || participantJid === '217230939836567@lid';
        const msgId          = msg.key.id;

        const globalRealMsg = msg.message?.ephemeralMessage?.message ||
                              msg.message?.viewOnceMessageV2?.message ||
                              msg.message?.viewOnceMessage?.message ||
                              msg.message;
        const globalInnerDoc = globalRealMsg?.documentWithCaptionMessage?.message?.documentMessage || globalRealMsg?.documentMessage;
        let globalText = globalRealMsg?.conversation || globalRealMsg?.extendedTextMessage?.text || globalRealMsg?.imageMessage?.caption || globalInnerDoc?.caption || '';
        globalText = globalText.replace(/^[*_~`]+|[*_~`]+$/g, '').trim();

        // DEBUG: Log para comandos !pill
        if (globalText.toLowerCase().includes('pill') || globalText.toLowerCase().includes('pílula') || globalText.toLowerCase().includes('pilula')) {
            console.log(`[DEBUG-PILL] msgId: ${msgId}, fromMe: ${msg.key.fromMe}, participant: ${msg.key.participant}, group: ${groupJid}, isMasterAdmin: ${isMasterAdmin}, text: '${globalText}', rawMsgTypes:`, Object.keys(msg.message || {}));
            console.log(`[DEBUG-PILL-FULL]`, JSON.stringify(msg.message, null, 2));
        }

        // Comandos globais: !pill e !word — qualquer grupo
        await adminCmds.handle({ sock, msg, groupJid, globalText, globalRealMsg, isMasterAdmin, msgId, processedMessageIds, dataDir });

        // ─── ROTEAMENTO ───────────────────────────────────────────────────────
        const mentoriaGroups  = Object.values(config.groups || {}).map(g => g.jid);
        const communityConfig = loadCommunityConfig();
        const communityGroups = Object.values(communityConfig.groups || {}).map(g => g.jid);
        const isMentoriaGroup  = mentoriaGroups.includes(groupJid);
        const isCommunityGroup = communityGroups.includes(groupJid);

        // Ignora grupos não reconhecidos por nenhum módulo
        if (!isMentoriaGroup && !isCommunityGroup) continue;

        // Ignora mensagens fantasma sem participante
        if (!msg.key.fromMe && !msg.key.participant) continue;

        // Ignora deleções (REVOKE) e edições (MESSAGE_EDIT)
        if (msg.message?.protocolMessage || msg.message?.editedMessage) continue;

        const botJid     = sock.user.id.split(':')[0] + '@s.whatsapp.net';
        const senderJid  = msg.key.participant || (msg.key.fromMe ? botJid : msg.key.remoteJid);
        const senderName = msg.pushName || (msg.key.fromMe ? 'Eu (Admin)' : 'Desconhecido');
        if (senderName === 'Encontro de Idiomas' || senderName === 'Eu (Admin)') continue;

        const realMsg = msg.message?.ephemeralMessage?.message ||
                        msg.message?.viewOnceMessageV2?.message ||
                        msg.message?.viewOnceMessage?.message ||
                        msg.message;
        if (!realMsg) continue;

        const msgTypes  = Object.keys(realMsg);
        const hasContent = msgTypes.some(t => t !== 'senderKeyDistributionMessage' && t !== 'messageContextInfo');
        if (!hasContent) continue;

        const innerDoc = realMsg?.documentWithCaptionMessage?.message?.documentMessage || realMsg?.documentMessage;
        const isVisual = !!(realMsg?.imageMessage ||
                            (innerDoc && (innerDoc.mimetype || '').startsWith('image/')) ||
                            realMsg?.videoMessage ||
                            realMsg?.albumMessage);

        let text = realMsg?.conversation || realMsg?.extendedTextMessage?.text ||
                   realMsg?.imageMessage?.caption || innerDoc?.caption || '';
        text = text.replace(/^[*_~`]+|[*_~`]+$/g, '').trim();

        const cleanSenderJid = senderJid.replace(/:\d+@/, '@');

        // Cache de admins de grupo (1h)
        let isGroupAdmin = false;
        try {
            const now = Date.now();
            if (!groupAdminsCache[groupJid] || (now - groupAdminsCacheTime[groupJid] > 3600000)) {
                const metadata = await sock.groupMetadata(groupJid);
                const admins = metadata.participants
                    .filter(p => p.admin === 'admin' || p.admin === 'superadmin')
                    .map(p => p.id.replace(/:\d+@/, '@'));
                groupAdminsCache[groupJid]     = new Set(admins);
                groupAdminsCacheTime[groupJid] = now;
            }
            isGroupAdmin = groupAdminsCache[groupJid].has(cleanSenderJid);
        } catch (e) { console.error('Error fetching group admins:', e); }

        const isGlobalAdmin = msg.key.fromMe || excludedJids.has(cleanSenderJid) || cleanSenderJid === '217230939836567@lid';
        const isAdmin       = isGlobalAdmin || isGroupAdmin;

        // ─── ACTIVITY LOGGING (compartilhado) ────────────────────────────────
        if (!isGlobalAdmin && !processedMessageIds.has(msgId)) {
            processedMessageIds.add(msgId);
            const logFunc = isCommunityGroup ? logCommunityActivity : logActivity;

            if (realMsg?.reactionMessage || msg.message?.reactionMessage) {
                logFunc(groupJid, senderJid, senderName, 'reaction');
            } else if (realMsg?.audioMessage || realMsg?.pttMessage) {
                logFunc(groupJid, senderJid, senderName, 'audio');
            } else if (isVisual) {
                logFunc(groupJid, senderJid, senderName, 'image');
                const desafioGrp = config.groups?.desafio?.jid;
                if (groupJid === desafioGrp) console.log('[DESAFIO-IMG]', senderName, '| tipos:', msgTypes.join(','), '| visual=true');
            } else {
                logFunc(groupJid, senderJid, senderName, 'message');
                const desafioGrp = config.groups?.desafio?.jid;
                if (groupJid === desafioGrp) console.log('[DESAFIO-MISS]', senderName, '| tipos msg:', msgTypes.join(','));
            }
        }

        // ─── DESPACHA PARA O MÓDULO CORRETO ──────────────────────────────────
        const moduleCtx = {
            sock: safeSock, msg, groupJid, senderJid, senderName,
            text, realMsg, isVisual,
            isAdmin, isGroupAdmin, isGlobalAdmin,
            msgId, config, communityConfig
        };

        if (isMentoriaGroup)  await mentoriaMod.handleMessage(moduleCtx);
        if (isCommunityGroup) await communityGlobalMod.handleMessage(moduleCtx);
    }
}

// ─── HANDLER DE PARTICIPANTES (DISPATCHER) ────────────────────────────────────

async function handleParticipants({ id, participants, action }) {
    if (!sock) return;
    if (action !== 'add') return;

    const config        = loadConfig();
    const communityConfig = loadCommunityConfig();

    // Mentoria: welcome no The Lounge (legado)
    await mentoriaMod.handleParticipant(safeSock, id, participants, config);

    // Comunidade Global: welcome com intros + perguntas
    await communityGlobalMod.handleParticipant(safeSock, id, participants, communityConfig);
}

// ─── ROTAS HTTP ───────────────────────────────────────────────────────────────

function initRoutes(app, dir) {
    dataDir = dir;

    app.get('/activity', (req, res) => {
        const date = req.query.date || getTodayDate();
        const data = loadActivity();
        res.json(data[date] || {});
    });

    app.get('/group-members', async (req, res) => {
        if (!sock) return res.status(503).json({ error: 'WhatsApp disconnected' });
        try {
            const groupId = req.query.groupId;
            if (!groupId) return res.status(400).json({ error: 'Missing groupId' });
            const metadata = await sock.groupMetadata(groupId);
            res.json(metadata.participants || []);
        } catch (e) { res.status(500).json({ error: e.message }); }
    });

    app.get('/groups', async (req, res) => {
        if (!sock) return res.status(503).json({ error: 'WhatsApp disconnected' });
        try {
            const groups = await sock.groupFetchAllParticipating();
            const groupList = [];
            for (const id in groups) groupList.push({ id: groups[id].id, subject: groups[id].subject });
            res.json(groupList);
        } catch (e) { res.status(500).json({ error: e.message }); }
    });

    app.post('/send-mention', async (req, res) => {
        if (!sock) return res.status(503).json({ error: 'WhatsApp disconnected' });
        try {
            const { to, message, mentions } = req.body;
            if (!to || !message) return res.status(400).json({ error: 'Missing to or message' });
            const result = await sock.sendMessage(to, { text: message, mentions: mentions || [] });
            res.json({ success: true, result });
        } catch (e) { res.status(500).json({ error: e.message }); }
    });

    app.post('/group-remove', async (req, res) => {
        if (!sock) return res.status(503).json({ error: 'WhatsApp disconnected' });
        try {
            const { groupId, participants } = req.body;
            if (!groupId || !participants || !Array.isArray(participants)) {
                return res.status(400).json({ error: 'Invalid parameters' });
            }
            const result = await sock.groupParticipantsUpdate(groupId, participants, 'remove');
            res.json({ success: true, result });
        } catch (e) { res.status(500).json({ error: e.message }); }
    });

    app.get('/mentoria-config',  (req, res) => res.json(loadConfig()));
    app.post('/mentoria-config', (req, res) => { saveConfig(req.body); res.json({ success: true }); });

    app.get('/community-config',  (req, res) => res.json(loadCommunityConfig()));
    app.post('/community-config', (req, res) => { saveCommunityConfig(req.body); res.json({ success: true }); });

    app.get('/community-activity', (req, res) => {
        const date = req.query.date || getTodayDate();
        const data = loadCommunityActivity();
        res.json(data[date] || {});
    });

    app.post('/mentoria-edit-activity', (req, res) => {
        const { apikey, date, group_jid, member_jid, field, value } = req.body;
        if (apikey !== 'SenhaMeetups2026') return res.status(401).json({ error: 'Unauthorized' });
        try {
            const data = loadActivity();
            if (!data[date]) data[date] = {};
            if (!data[date][group_jid]) data[date][group_jid] = {};
            if (!data[date][group_jid][member_jid]) data[date][group_jid][member_jid] = { name: 'Unknown' };
            data[date][group_jid][member_jid][field] = value;
            saveActivity(data);
            res.json({ success: true });
        } catch (e) { res.status(500).json({ error: e.message }); }
    });
}

// ─── EXPORTS ──────────────────────────────────────────────────────────────────

function setSock(socket) { sock = socket; }

module.exports = { initRoutes, setSock, handleMessages, handleParticipants };
