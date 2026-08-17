const fs = require('fs');
const file = '/home/ubuntu/encontrodeidiomas/baileys-server/data/activity_log.json';
const data = JSON.parse(fs.readFileSync(file, 'utf8'));
if (data['2026-08-07'] && data['2026-08-07']['120363246518434750@g.us'] && data['2026-08-07']['120363246518434750@g.us']['277583904125013@lid']) {
    data['2026-08-07']['120363246518434750@g.us']['277583904125013@lid'].images_sent = 1;
    fs.writeFileSync(file, JSON.stringify(data, null, 2));
    console.log('Fixed Flavia on VPS!');
} else {
    console.log('Flavia not found');
}
