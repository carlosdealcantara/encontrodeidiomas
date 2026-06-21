const fs = require('fs');
const path = require('path');

let sock = null;
let dataDir = '';

// Deduplication: prevents counting the same WhatsApp message event twice
// (Baileys sometimes fires 2 events for media messages)
let processedMessageIds = new Set();
let processedIdsDate = '';


// Helper to get today's date in YYYY-MM-DD for BRT
function getTodayDate() {
    // Current time in Brazil
    const formatter = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Sao_Paulo' });
    return formatter.format(new Date()); // Returns YYYY-MM-DD
}

function getActivityFile() {
    return path.join(dataDir, 'activity_log.json');
}

function getConfigFile() {
    return path.join(dataDir, 'mentoria_config.json');
}

function loadConfig() {
    try {
        const file = getConfigFile();
        if (fs.existsSync(file)) {
            return JSON.parse(fs.readFileSync(file, 'utf8'));
        }
    } catch (e) {
        console.error('Error loading mentoria config:', e);
    }
    // Default fallback
    return {
        admin_jid: "556192666148@s.whatsapp.net",
        groups: {},
        templates: {}
    };
}

function saveConfig(config) {
    fs.writeFileSync(getConfigFile(), JSON.stringify(config, null, 2));
}

function loadActivity() {
    try {
        const file = getActivityFile();
        if (fs.existsSync(file)) {
            return JSON.parse(fs.readFileSync(file, 'utf8'));
        }
    } catch (e) {
        console.error('Error loading activity:', e);
    }
    return {};
}

function saveActivity(data) {
    fs.writeFileSync(getActivityFile(), JSON.stringify(data, null, 2));
}

// === WRITE QUEUE SYSTEM ===
const writeQueue = [];
let isSaving = false;

async function processQueue() {
    if (isSaving || writeQueue.length === 0) return;
    isSaving = true;
    
    // Extrai todos os eventos atualmente na fila de uma vez (batch)
    const events = writeQueue.splice(0, writeQueue.length);
    
    try {
        const data = loadActivity();
        
        for (const event of events) {
            const { groupJid, senderJid, senderName, type, date } = event;
            
            if (!data[date]) data[date] = {};
            if (!data[date][groupJid]) data[date][groupJid] = {};
            if (!data[date][groupJid][senderJid]) {
                data[date][groupJid][senderJid] = {
                    name: senderName,
                    messages: 0,
                    reactions_given: 0,
                    images_sent: 0,
                    audios_sent: 0,
                    first_message_at: new Date().toISOString(),
                    last_message_at: new Date().toISOString(),
                };
            }

            if (type === 'message') {
                data[date][groupJid][senderJid].messages += 1;
            } else if (type === 'reaction') {
                data[date][groupJid][senderJid].reactions_given += 1;
            } else if (type === 'image') {
                data[date][groupJid][senderJid].images_sent = (data[date][groupJid][senderJid].images_sent || 0) + 1;
                data[date][groupJid][senderJid].messages += 1; // Soma também nas mensagens globais (Word Slingers)
            } else if (type === 'audio') {
                data[date][groupJid][senderJid].audios_sent = (data[date][groupJid][senderJid].audios_sent || 0) + 1;
                data[date][groupJid][senderJid].messages += 1; // Soma também nas mensagens globais (Word Slingers)
            }
            
            data[date][groupJid][senderJid].last_message_at = new Date().toISOString();
        }
        
        saveActivity(data);
    } catch (e) {
        console.error('Error processing write queue:', e);
    } finally {
        isSaving = false;
        // Se novos eventos chegaram enquanto salvávamos, processa-os
        if (writeQueue.length > 0) {
            setTimeout(processQueue, 0);
        }
    }
}
// =========================


function logActivity(groupJid, senderJid, senderName, type) {
    const date = getTodayDate();
    writeQueue.push({ groupJid, senderJid, senderName, type, date });
    processQueue();
}

async function handleMessages({ messages, type }) {
    if (type !== 'notify') return;
    if (!sock) return;
    
    const config = loadConfig();
    const adminJid = config.admin_jid || "556192666148@s.whatsapp.net";
    // Support for multiple excluded admin JIDs (configured as array in config)
    const extraAdminJids = config.extra_admin_jids || [];
    const excludedJids = new Set([adminJid, ...extraAdminJids]);

    // Reset deduplication Set daily
    const currentDate = getTodayDate();
    if (currentDate !== processedIdsDate) {
        processedMessageIds = new Set();
        processedIdsDate = currentDate;
    }

    for (const msg of messages) {
        const groupJid = msg.key.remoteJid;
        if (!groupJid?.endsWith('@g.us')) continue; // Only groups

        // Check if group is one of the configured ones, ignore others
        const allowedGroups = Object.values(config.groups || {}).map(g => g.jid);
        if (!allowedGroups.includes(groupJid)) continue;

        // Ignore phantom/system messages in groups that lack a participant
        if (!msg.key.fromMe && !msg.key.participant) continue;

        // FIX 1: Skip protocol messages (message deletions = REVOKE, edits = MESSAGE_EDIT)
        // These are meta-events from WhatsApp and should never count as activity.
        if (msg.message?.protocolMessage || msg.message?.editedMessage) continue;

        // FIX 2: Skip stickers (no educational/engagement value for ranking)
        if (msg.message?.stickerMessage) continue;

        const botJid = sock.user.id.split(':')[0] + '@s.whatsapp.net';
        const senderJid = msg.key.participant || (msg.key.fromMe ? botJid : msg.key.remoteJid);
        const senderName = msg.pushName || (msg.key.fromMe ? 'Eu (Admin)' : 'Desconhecido');
        if (senderName === 'Encontro de Idiomas' || senderName === 'Eu (Admin)') continue; // Ignora o bot oficial e o admin local

        // Desembrulhar qualquer tipo de envelope (efêmero, view-once, document-with-caption)
        // NOTA: documentWithCaptionMessage contém a mensagem diretamente em .message,
        // NÃO num sub-campo, então ele já é aberto pelo último fallback abaixo.
        const realMsg = msg.message?.ephemeralMessage?.message ||
                        msg.message?.viewOnceMessageV2?.message ||
                        msg.message?.viewOnceMessage?.message ||
                        msg.message;

        // Imagem pode chegar em vários formatos conforme versão do WhatsApp / dispositivo:
        // 1. imageMessage        — foto clássica
        // 2. documentMessage     — arquivo enviado; quando mimeType começa com 'image/' é uma foto salva como doc
        // 3. documentWithCaptionMessage — mesmo que acima, mas com legenda
        // 4. viewOnceMessage (já desembrulhado acima) — visualização única
        const innerDoc = realMsg?.documentWithCaptionMessage?.message?.documentMessage
                         || realMsg?.documentMessage;
        const isVisual = !!(realMsg?.imageMessage ||
                            (innerDoc && (innerDoc.mimetype || '').startsWith('image/')) ||
                            realMsg?.videoMessage);

        const text = realMsg?.conversation || realMsg?.extendedTextMessage?.text ||
                     realMsg?.imageMessage?.caption ||
                     innerDoc?.caption || '';

        // === ACTIVITY LOGGING (students only, no commands, no duplicates) ===
        const isAdmin = msg.key.fromMe || excludedJids.has(senderJid);
        const msgId = msg.key.id;

        if (!isAdmin && !processedMessageIds.has(msgId)) {
            processedMessageIds.add(msgId);

            const msgTypes = Object.keys(realMsg || {});

            if (realMsg?.reactionMessage || msg.message?.reactionMessage) {
                logActivity(groupJid, senderJid, senderName, 'reaction');
            } else if (realMsg?.audioMessage || realMsg?.pttMessage) {
                logActivity(groupJid, senderJid, senderName, 'audio');
            } else if (isVisual) {
                logActivity(groupJid, senderJid, senderName, 'image');
                // Log diagnóstico para acompanhamento
                const desafioGrp = config.groups?.desafio?.jid;
                if (groupJid === desafioGrp) {
                    console.log('[DESAFIO-IMG]', senderName, '| tipos:', msgTypes.join(','), '| visual=true');
                }
            } else {
                logActivity(groupJid, senderJid, senderName, 'message');
                // Log diagnóstico temporário para qualquer tipo não-texto no Desafio
                const desafioGrp = config.groups?.desafio?.jid;
                if (groupJid === desafioGrp) {
                    console.log('[DESAFIO-MISS]', senderName, '| tipos msg:', msgTypes.join(','));
                }
            }
        }

        // === STREAK SYSTEM (Desafio Group — runs independently of activity logging) ===
        if (isVisual && !isAdmin) {
            const desafioGroup = config.groups?.desafio?.jid;
            if (groupJid === desafioGroup) {
                try {
                    const res = await fetch('https://dev.encontrodeidiomas.com.br/bot_whatsapp/mentoria_desafio_streak_api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ member_jid: senderJid, member_name: senderName })
                    });
                    const data = await res.json();
                    
                    if (data.success && !data.already_computed) {
                        const nameOnly = senderJid.split('@')[0];
                        let msgTemplate = config.templates?.streak_confirm || `✅ Image computed, @{name}! You are on a {streak}-day streak! 🔥`;
                        let confirmMsg = msgTemplate.replace('@{name}', `@${nameOnly}`).replace('{name}', nameOnly).replace('{streak}', data.streak);
                        await sock.sendMessage(groupJid, { text: confirmMsg, mentions: [senderJid] });
                        
                        if (data.is_milestone) {
                            let msTemplate = config.templates?.streak_milestone || `🎉 CONGRATULATIONS! @{name} just hit a {streak}-day streak! Legend! 🏆`;
                            let milestoneMsg = msTemplate.replace('@{name}', `@${nameOnly}`).replace('{name}', nameOnly).replace('{streak}', data.streak);
                            setTimeout(async () => {
                                await sock.sendMessage(groupJid, { text: milestoneMsg, mentions: [senderJid] });
                            }, 2000);
                        }
                    }
                } catch (err) {
                    console.error('Error calling streak API:', err);
                }
            }
        }
            
        // Check for commands (e.g. !attend in Our Classes)
        if (text.startsWith('!attend')) {
            // If it's the Our Classes group, log the booking
            const ourClassesGroup = config.groups?.our_classes?.jid;
            if (groupJid === ourClassesGroup) {
                try {
                    const res = await fetch('https://dev.encontrodeidiomas.com.br/bot_whatsapp/class_api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'attend',
                            group_jid: groupJid,
                            member_jid: senderJid,
                            member_name: senderName
                        })
                    });
                    const data = await res.json();
                    
                    let dStr = data.class_date_en || data.class_date;
                    let tParts = (data.class_time || "00:00").split(':');
                    let h = parseInt(tParts[0]);
                    let ampm = "AM";
                    if (h >= 12) { ampm = "PM"; if (h > 12) h -= 12; }
                    if (h === 0) h = 12;
                    let mStr = tParts[1] === "00" ? "" : ":" + tParts[1];
                    let tStr = `${h}${mStr} ${ampm}`;
                    
                    let listText = `📅 ${dStr}\n⏰ Starting at ${tStr}\n\n*Confirmed Attendees:*\n`;
                    if (data.attendees && data.attendees.length > 0) {
                        data.attendees.forEach((name, i) => listText += `${i+1}. ${name}\n`);
                    } else {
                        listText += "No one yet.";
                    }

                    if (data.success) {
                        // Reação ✅ na mensagem do usuário
                        await sock.sendMessage(groupJid, { react: { text: '✅', key: msg.key } });
                        
                        let msgTxt = config.templates?.attend_confirm || `{listText}`;
                        msgTxt = msgTxt.replace('{name}', senderJid.split('@')[0]).replace('{listText}', listText);
                        
                        await sock.sendMessage(groupJid, { 
                            text: msgTxt
                        });
                    } else if (data.reason === 'deadline_passed') {
                        let msgTxt = data.class_confirmed 
                            ? (config.templates?.attend_late_good || `⏰ The deadline to confirm attendance has passed, @{name}.\n\n✅ *Good news:* The class is confirmed and will happen anyway!\n\n{listText}`)
                            : (config.templates?.attend_late_bad || `⏰ The deadline to confirm attendance has passed, @{name}.\n\n❌ *Bad news:* The class was already cancelled due to lack of attendees.`);
                        
                        msgTxt = msgTxt.replace('{name}', senderJid.split('@')[0]).replace('{listText}', data.class_confirmed ? listText : '');
                        
                        await sock.sendMessage(groupJid, { 
                            text: msgTxt,
                            mentions: [senderJid]
                        });
                    } else {
                        await sock.sendMessage(groupJid, { text: `❌ ${data.message || 'Error confirming registration.'}` });
                    }
                } catch (err) {
                    await sock.sendMessage(groupJid, { text: `⚠️ Error reaching the server to confirm: ${err.message}` });
                }
            }
        } else if (text.startsWith('!unattend')) {
            const ourClassesGroup = config.groups?.our_classes?.jid;
            if (groupJid === ourClassesGroup) {
                try {
                    const res = await fetch('https://dev.encontrodeidiomas.com.br/bot_whatsapp/class_api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'unattend',
                            group_jid: groupJid,
                            member_jid: senderJid
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        let dStr = data.class_date_en || data.class_date;
                        let tParts = (data.class_time || "00:00").split(':');
                        let h = parseInt(tParts[0]);
                        let ampm = "AM";
                        if (h >= 12) { ampm = "PM"; if (h > 12) h -= 12; }
                        if (h === 0) h = 12;
                        let mStr = tParts[1] === "00" ? "" : ":" + tParts[1];
                        let tStr = `${h}${mStr} ${ampm}`;
                        
                        let listText = `📅 ${dStr}\n⏰ Starting at ${tStr}\n\n*Confirmed Attendees:*\n`;
                        if (data.attendees && data.attendees.length > 0) {
                            data.attendees.forEach((name, i) => listText += `${i+1}. ${name}\n`);
                        } else {
                            listText += "No one yet.";
                        }

                        // Reação de lixeira 🗑️
                        await sock.sendMessage(groupJid, { react: { text: '🗑️', key: msg.key } });

                        let msgTxt = config.templates?.unattend_confirm || `{listText}`;
                        msgTxt = msgTxt.replace('{name}', senderJid.split('@')[0]).replace('{listText}', listText);

                        await sock.sendMessage(groupJid, { 
                            text: msgTxt
                        });
                        
                        // O Cancelamento Extremo (caiu para 0 depois do prazo)
                        if (data.cancelled_now) {
                            let msgCancel = config.templates?.unattend_cancelled_now || `🚨 *CLASS CANCELLED*\n\nSince there are no more students confirmed, today's class is now cancelled.`;
                            await sock.sendMessage(groupJid, { 
                                text: msgCancel
                            });
                        }
                    } else {
                        await sock.sendMessage(groupJid, { text: `❌ ${data.message || 'Error cancelling registration.'}` });
                    }
                } catch (err) {}
            }
        } else if (text.startsWith('!list')) {
            const ourClassesGroup = config.groups?.our_classes?.jid;
            if (groupJid === ourClassesGroup) {
                try {
                    const res = await fetch('https://dev.encontrodeidiomas.com.br/bot_whatsapp/class_api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'list',
                            group_jid: groupJid,
                            member_jid: senderJid
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        let dStr = data.class_date_en || data.class_date;
                        let tStr = data.class_time_en || (data.class_time?.substring(0,5) + ' BRT');
                        
                        let attendeesList = "";
                        if (data.attendees && data.attendees.length > 0) {
                            data.attendees.forEach((name, i) => attendeesList += `${i+1}. ${name}\n`);
                        } else {
                            attendeesList += "No one yet.";
                        }
                        
                        // Calcula deadline (1 hora antes)
                        let dparts = (data.class_time || "00:00").split(':');
                        let h = parseInt(dparts[0]);
                        let ampm = "AM";
                        h = h - 1; // 1 hora antes
                        if (h < 0) h = 23;
                        if (h >= 12) { ampm = "PM"; if (h > 12) h -= 12; }
                        if (h === 0) h = 12;
                        let hStr = h.toString().padStart(2, '0');
                        let deadlineInfo = `${hStr}:${dparts[1]} ${ampm} (UTC-3)`;
                        
                        let msg = config.templates?.class_status || `📋 *Class Status — {class_info}*\n\n*Confirmed Attendees:*\n{attendees}\n\nDeadline to confirm: {deadline_info}`;
                        msg = msg.replace('{class_info}', `${dStr} at ${tStr}`)
                                 .replace('{attendees}', attendeesList.trim())
                                 .replace('{deadline_info}', deadlineInfo);

                        await sock.sendMessage(groupJid, { text: msg });
                    } else {
                        await sock.sendMessage(groupJid, { text: `❌ ${data.message || 'Error fetching status.'}` });
                    }
                } catch (err) {}
            }
        } else if (text.startsWith('!streaks')) {
            const desafioGroup = config.groups?.desafio?.jid;
            if (groupJid === desafioGroup) {
                try {
                    const res = await fetch('https://dev.encontrodeidiomas.com.br/bot_whatsapp/mentoria_desafio_streak_list_api.php');
                    const data = await res.json();
                    if (data.success) {
                        let allTimeList = '';
                        let activeList = '';
                        const medals = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣'];

                        if (data.allTime && data.allTime.length > 0) {
                            data.allTime.forEach((item, i) => {
                                const name = item.member_name || item.member_jid.split('@')[0];
                                allTimeList += `${medals[i] || '🏅'} @${name} — ${item.longest_streak} days\n`;
                            });
                        } else {
                            allTimeList = 'No records yet.\n';
                        }

                        if (data.active && data.active.length > 0) {
                            data.active.forEach((item, i) => {
                                const name = item.member_name || item.member_jid.split('@')[0];
                                activeList += `${i+1}. @${name} — ${item.current_streak} days\n`;
                            });
                        } else {
                            activeList = 'No active streaks right now.\n';
                        }

                        let msgTemplate = config.templates?.streak_leaderboard || `🏆 *All-Time Streak Records*\n\n{allTimeList}\n🔥 *Active Streaks Today*\n\n{activeList}`;
                        let replyMsg = msgTemplate.replace('{allTimeList}', allTimeList).replace('{activeList}', activeList);

                        // Coletar as menções necessárias
                        let mentions = [];
                        if (data.allTime) data.allTime.forEach(m => mentions.push(m.member_jid));
                        if (data.active) data.active.forEach(m => mentions.push(m.member_jid));
                        mentions = [...new Set(mentions)]; // Remover duplicatas

                        await sock.sendMessage(groupJid, { 
                            text: replyMsg,
                            mentions: mentions
                        });
                    }
                } catch (err) {
                    console.error('Error fetching streaks list:', err);
                }
            }
        }
    }
}

async function handleParticipants({ id, participants, action }) {
    if (!sock) return;
    if (action === 'add') {
        const config = loadConfig();
        const theLoungeJid = config.groups?.the_lounge?.jid;
        
        if (id === theLoungeJid && config.templates?.welcome) {
            for (const participantJid of participants) {
                const name = participantJid.split('@')[0];
                const text = config.templates.welcome.replace('@{name}', `@${name}`).replace('{name}', name);
                
                await sock.sendMessage(id, {
                    text,
                    mentions: [participantJid]
                });
                console.log(`[WELCOME] Welcome message sent to ${participantJid} in The Lounge`);
            }
        }
    }
}

function initRoutes(app, dir) {
    dataDir = dir;

    // GET /activity
    app.get('/activity', (req, res) => {
        const date = req.query.date || getTodayDate();
        const data = loadActivity();
        res.json(data[date] || {});
    });

    // GET /group-members
    app.get('/group-members', async (req, res) => {
        if (!sock) return res.status(503).json({ error: 'WhatsApp disconnected' });
        try {
            const groupId = req.query.groupId;
            if (!groupId) return res.status(400).json({ error: 'Missing groupId' });
            
            const metadata = await sock.groupMetadata(groupId);
            res.json(metadata.participants || []);
        } catch (e) {
            res.status(500).json({ error: e.message });
        }
    });

    // GET /groups
    app.get('/groups', async (req, res) => {
        if (!sock) return res.status(503).json({ error: 'WhatsApp disconnected' });
        try {
            const groups = await sock.groupFetchAllParticipating();
            const groupList = [];
            for (const id in groups) {
                groupList.push({
                    id: groups[id].id,
                    subject: groups[id].subject
                });
            }
            res.json(groupList);
        } catch (e) {
            res.status(500).json({ error: e.message });
        }
    });

    // POST /send-mention
    app.post('/send-mention', async (req, res) => {
        if (!sock) return res.status(503).json({ error: 'WhatsApp disconnected' });
        try {
            const { to, message, mentions } = req.body;
            if (!to || !message) return res.status(400).json({ error: 'Missing to or message' });
            
            const result = await sock.sendMessage(to, { text: message, mentions: mentions || [] });
            res.json({ success: true, result });
        } catch (e) {
            res.status(500).json({ error: e.message });
        }
    });

    // POST /group-remove
    app.post('/group-remove', async (req, res) => {
        if (!sock) return res.status(503).json({ error: 'WhatsApp disconnected' });
        try {
            const { groupId, participants } = req.body;
            if (!groupId || !participants || !Array.isArray(participants)) {
                return res.status(400).json({ error: 'Invalid parameters' });
            }
            
            const result = await sock.groupParticipantsUpdate(groupId, participants, 'remove');
            res.json({ success: true, result });
        } catch (e) {
            res.status(500).json({ error: e.message });
        }
    });

    // Config endpoints
    app.get('/mentoria-config', (req, res) => {
        res.json(loadConfig());
    });

    app.post('/mentoria-config', (req, res) => {
        saveConfig(req.body);
        res.json({ success: true });
    });

    // POST /mentoria-edit-activity
    app.post('/mentoria-edit-activity', (req, res) => {
        const { apikey, date, group_jid, member_jid, field, value } = req.body;
        if (apikey !== 'SenhaMeetups2026') return res.status(401).json({ error: 'Unauthorized' });
        
        try {
            const data = loadActivity();
            if (!data[date]) data[date] = {};
            if (!data[date][group_jid]) data[date][group_jid] = {};
            if (!data[date][group_jid][member_jid]) data[date][group_jid][member_jid] = { name: "Unknown" };
            
            data[date][group_jid][member_jid][field] = value;
            saveActivity(data);
            res.json({ success: true });
        } catch (e) {
            res.status(500).json({ error: e.message });
        }
    });
}

function setSock(socket) {
    sock = socket;
}

module.exports = {
    initRoutes,
    setSock,
    handleMessages,
    handleParticipants
};
