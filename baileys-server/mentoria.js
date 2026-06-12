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
            first_message_at: new Date().toISOString(),
            last_message_at: new Date().toISOString(),
            streaks: 0 // Simplification for now, full streak calc needs historical check
        };
    }

    if (type === 'message') {
        data[date][groupJid][senderJid].messages += 1;
    } else if (type === 'reaction') {
        data[date][groupJid][senderJid].reactions_given += 1;
    } else if (type === 'image') {
        data[date][groupJid][senderJid].messages += 1;
        data[date][groupJid][senderJid].images_sent = (data[date][groupJid][senderJid].images_sent || 0) + 1;
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
        } else if (msg.message?.imageMessage && !msg.key.fromMe) {
            logActivity(groupJid, senderJid, senderName, 'image');
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
                    
                    let listText = `\n\n📅 *Class: ${data.class_date} at ${data.class_time?.substring(0,5)} BRT*\n\n*Confirmed Attendees:*\n`;
                    if (data.attendees && data.attendees.length > 0) {
                        data.attendees.forEach((name, i) => listText += `${i+1}. ${name}\n`);
                    } else {
                        listText += "No one yet.";
                    }

                    if (data.success) {
                        await sock.sendMessage(groupJid, { 
                            text: `✅ Registration confirmed for @${senderJid.split('@')[0]}!${listText}`,
                            mentions: [senderJid]
                        });
                    } else if (data.reason === 'deadline_passed') {
                        // Prazo já passou, não confirmou. Mas dá o status da aula.
                        let statusText = data.class_confirmed 
                            ? "✅ *Good news:* The class is confirmed and will happen anyway!" 
                            : "❌ *Bad news:* The class was already cancelled due to lack of attendees.";
                            
                        await sock.sendMessage(groupJid, { 
                            text: `⏰ The deadline to confirm attendance has passed, @${senderJid.split('@')[0]}.\n\n${statusText}${data.class_confirmed ? listText : ''}`,
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
                        let listText = `\n\n📅 *Class: ${data.class_date} at ${data.class_time?.substring(0,5)} BRT*\n\n*Confirmed Attendees:*\n`;
                        if (data.attendees && data.attendees.length > 0) {
                            data.attendees.forEach((name, i) => listText += `${i+1}. ${name}\n`);
                        } else {
                            listText += "No one yet.";
                        }

                        await sock.sendMessage(groupJid, { 
                            text: `🗑️ Registration cancelled for @${senderJid.split('@')[0]}.${listText}`,
                            mentions: [senderJid]
                        });
                        
                        // O Cancelamento Extremo (caiu para 0 depois do prazo)
                        if (data.cancelled_now) {
                            await sock.sendMessage(groupJid, { 
                                text: `🚨 *CLASS CANCELLED*\n\nSince there are no more students confirmed, today's class is now cancelled.`
                            });
                        }
                    } else {
                        await sock.sendMessage(groupJid, { text: `❌ ${data.message || 'Error cancelling registration.'}` });
                    }
                } catch (err) {}
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
