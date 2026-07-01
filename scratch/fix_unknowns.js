const fs = require('fs');

const activityFile = '/home/ubuntu/encontrodeidiomas/baileys-server/data/activity_log.json';
if (!fs.existsSync(activityFile)) {
    console.error("activity_log.json not found");
    process.exit(1);
}

const activity = JSON.parse(fs.readFileSync(activityFile, 'utf8'));
let changed = false;

// We know from DB queries:
const knownNames = {
    '277583904125013@lid': 'Flávia Lopes',
    '90370440462475@lid': 'Rayza 🤍',
    '207605951635698@lid': 'Julyana Bello'
};

for (const date in activity) {
    for (const groupJid in activity[date]) {
        for (const memberJid in activity[date][groupJid]) {
            const data = activity[date][groupJid][memberJid];
            if (!data.name || data.name === 'Unknown' || data.name === 'Desconhecido') {
                if (knownNames[memberJid]) {
                    data.name = knownNames[memberJid];
                    changed = true;
                }
            }
        }
    }
}

if (changed) {
    fs.writeFileSync(activityFile, JSON.stringify(activity, null, 2), 'utf8');
    console.log("Fixed Unknown names in activity_log.json");
} else {
    console.log("No Unknown names needed fixing");
}
