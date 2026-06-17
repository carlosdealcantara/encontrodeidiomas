const fs = require('fs');
const path = require('path');

let sock = null;
let dataDir = '';

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

function logActivity(groupJid, senderJid, senderName, type) {
    const date = getTodayDate();
    const data = loadActivity();

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
    } else if (type === 'audio') {
        data[date][groupJid][senderJid].audios_sent = (data[date][groupJid][senderJid].audios_sent || 0) + 1;
    }
    
    data[date][groupJid][senderJid].last_message_at = new Date().toISOString();
    
    saveActivity(data);
}

async function handleMessages({ messages, type }) {
    if (type !== 'notify') return;
    if (!sock) return;
    
    const config = loadConfig();
    const adminJid = config.admin_jid || "556192666148@s.whatsapp.net";

    for (const msg of messages) {
        const groupJid = msg.key.remoteJid;
        if (!groupJid?.endsWith('@g.us')) continue; // Only groups

        // Check if group is one of the configured ones, ignore others
        const allowedGroups = Object.values(config.groups || {}).map(g => g.jid);
        if (!allowedGroups.includes(groupJid)) continue;

        // Ignore phantom/system messages in groups that lack a participant
        if (!msg.key.fromMe && !msg.key.participant) continue;

        // Se a mensagem foi enviada pelo próprio aparelho do bot (fromMe)
        // e o participant estiver vazio, usamos o JID do próprio bot
        const botJid = sock.user.id.split(':')[0] + '@s.whatsapp.net';
        const senderJid = msg.key.participant || (msg.key.fromMe ? botJid : msg.key.remoteJid);
        const senderName = msg.pushName || (msg.key.fromMe ? 'Eu (Admin)' : 'Desconhecido');

        // Ignora apenas as mensagens automáticas do próprio bot para evitar loop e pontuação falsa.
        // Vamos processar se for um comando manual começando com !
        const text = msg.message?.conversation || msg.message?.extendedTextMessage?.text || msg.message?.imageMessage?.caption || '';
        
        if (msg.key.fromMe && !text.startsWith('!')) continue;

        // Check if reaction (ignora reações automáticas do bot)
        if (msg.message?.reactionMessage && !msg.key.fromMe) {
            logActivity(groupJid, senderJid, senderName, 'reaction');
        } else if (msg.message?.audioMessage && !msg.key.fromMe) {
            // Áudio detectado — conta como atividade de pronúncia (Reading out loud)
            logActivity(groupJid, senderJid, senderName, 'audio');
        } else if (msg.message?.imageMessage && !msg.key.fromMe) {
            logActivity(groupJid, senderJid, senderName, 'image');
            
            // === Streak System (Desafio Group) ===
            const desafioGroup = config.groups?.desafio?.jid;
            if (groupJid === desafioGroup) {
                try {
                    const res = await fetch('https://dev.encontrodeidiomas.com.br/bot_whatsapp/mentoria_desafio_streak_api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            member_jid: senderJid,
                            member_name: senderName
                        })
                    });
                    const data = await res.json();
                    
                    if (data.success && !data.already_computed) {
                        // Confirmação de imagem
                        const nameOnly = senderJid.split('@')[0];
                        let msgTemplate = config.templates?.streak_confirm || `✅ Image computed, @{name}! You are on a {streak}-day streak! 🔥`;
                        let confirmMsg = msgTemplate.replace('@{name}', `@${nameOnly}`)
                                                    .replace('{name}', nameOnly)
                                                    .replace('{streak}', data.streak);
                        
                        await sock.sendMessage(groupJid, { 
                            text: confirmMsg,
                            mentions: [senderJid]
                        });
                        
                        // Milestone celebration
                        if (data.is_milestone) {
                            let msTemplate = config.templates?.streak_milestone || `🎉 CONGRATULATIONS! @{name} just hit a {streak}-day streak! Legend! 🏆`;
                            let milestoneMsg = msTemplate.replace('@{name}', `@${nameOnly}`)
                                                         .replace('{name}', nameOnly)
                                                         .replace('{streak}', data.streak);
                            
                            // Atraso de 2 segundos para dar tempo da primeira mensagem chegar
                            setTimeout(async () => {
                                await sock.sendMessage(groupJid, { 
                                    text: milestoneMsg,
                                    mentions: [senderJid]
                                });
                            }, 2000);
                        }
                    }
                } catch (err) {
                    console.error('Error calling streak API:', err);
                }
            }
        } else if (!msg.key.fromMe) {
            logActivity(groupJid, senderJid, senderName, 'message');
        }
            
        // Check for commands (e.g. !confirm in Our Classes)
        if (text.startsWith('!book') || text.startsWith('!confirm') || text.startsWith('!attend')) {
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
                    let tStr = data.class_time_en || (data.class_time?.substring(0,5) + ' BRT');
                    let listText = `\n\n📅 *Class: ${dStr} at ${tStr}*\n\n*Confirmed Attendees:*\n`;
                    if (data.attendees && data.attendees.length > 0) {
                        data.attendees.forEach((name, i) => listText += `${i+1}. ${name}\n`);
                    } else {
                        listText += "No one yet.";
                    }

                    if (data.success) {
                        let msg = config.templates?.attend_confirm || `✅ Registration confirmed for @{name}!{listText}`;
                        msg = msg.replace('{name}', senderJid.split('@')[0]).replace('{listText}', listText);
                        
                        await sock.sendMessage(groupJid, { 
                            text: msg,
                            mentions: [senderJid]
                        });
                    } else if (data.reason === 'deadline_passed') {
                        let msg = data.class_confirmed 
                            ? (config.templates?.attend_late_good || `⏰ The deadline to confirm attendance has passed, @{name}.\n\n✅ *Good news:* The class is confirmed and will happen anyway!{listText}`)
                            : (config.templates?.attend_late_bad || `⏰ The deadline to confirm attendance has passed, @{name}.\n\n❌ *Bad news:* The class was already cancelled due to lack of attendees.`);
                        
                        msg = msg.replace('{name}', senderJid.split('@')[0]).replace('{listText}', data.class_confirmed ? listText : '');
                        
                        await sock.sendMessage(groupJid, { 
                            text: msg,
                            mentions: [senderJid]
                        });
                    } else {
                        await sock.sendMessage(groupJid, { text: `❌ ${data.message || 'Error confirming registration.'}` });
                    }
                } catch (err) {
                    await sock.sendMessage(groupJid, { text: `⚠️ Error reaching the server to confirm: ${err.message}` });
                }
            }
        } else if (text.startsWith('!unattend') || text.startsWith('!cancel')) {
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
                        let tStr = data.class_time_en || (data.class_time?.substring(0,5) + ' BRT');
                        let listText = `\n\n📅 *Class: ${dStr} at ${tStr}*\n\n*Confirmed Attendees:*\n`;
                        if (data.attendees && data.attendees.length > 0) {
                            data.attendees.forEach((name, i) => listText += `${i+1}. ${name}\n`);
                        } else {
                            listText += "No one yet.";
                        }

                        let msg = config.templates?.unattend_confirm || `🗑️ Registration cancelled for @{name}.{listText}`;
                        msg = msg.replace('{name}', senderJid.split('@')[0]).replace('{listText}', listText);

                        await sock.sendMessage(groupJid, { 
                            text: msg,
                            mentions: [senderJid]
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
