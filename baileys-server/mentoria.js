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
            first_message_at: new Date().toISOString(),
            last_message_at: new Date().toISOString(),
            streaks: 0 // Simplification for now, full streak calc needs historical check
        };
    }

    if (type === 'message') {
        data[date][groupJid][senderJid].messages += 1;
    } else if (type === 'reaction') {
        data[date][groupJid][senderJid].reactions_given += 1;
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
        if (msg.key.fromMe) continue; // Ignore bot's own messages

        const senderJid = msg.key.participant;
        const senderName = msg.pushName || 'Desconhecido';

        // Check if reaction
        if (msg.message?.reactionMessage) {
            logActivity(groupJid, senderJid, senderName, 'reaction');
        } else {
            logActivity(groupJid, senderJid, senderName, 'message');
            
            // Check for commands (e.g. !confirm in Our Meetups)
            const text = msg.message?.conversation || msg.message?.extendedTextMessage?.text || '';
            if (text.startsWith('!book') || text.startsWith('!confirm')) {
                // If it's the Our Meetups group, log the booking
                const ourMeetupsGroup = config.groups?.our_meetups?.jid;
                if (groupJid === ourMeetupsGroup) {
                    await sock.sendMessage(groupJid, { 
                        text: `✅ Booking confirmed for @${senderJid.split('@')[0]}!`,
                        mentions: [senderJid]
                    });
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
                const text = config.templates.welcome.replace('{name}', name).replace('@{name}', `@${name}`);
                
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
