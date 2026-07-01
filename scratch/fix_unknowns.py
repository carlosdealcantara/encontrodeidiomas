import json
import os

activity_file = '/home/ubuntu/encontrodeidiomas/baileys-server/data/activity_log.json'
if not os.path.exists(activity_file):
    print("activity_log.json not found")
    exit(1)

with open(activity_file, 'r', encoding='utf-8') as f:
    activity = json.load(f)

changed = False
known_names = {
    '277583904125013@lid': 'Flávia Lopes',
    '90370440462475@lid': 'Rayza 🤍',
    '207605951635698@lid': 'Julyana Bello'
}

for date, groups in activity.items():
    for group_jid, members in groups.items():
        for member_jid, data in members.items():
            name = data.get('name', '')
            if not name or name == 'Unknown' or name == 'Desconhecido':
                if member_jid in known_names:
                    data['name'] = known_names[member_jid]
                    changed = True

if changed:
    with open(activity_file, 'w', encoding='utf-8') as f:
        json.dump(activity, f, ensure_ascii=False, indent=2)
    print("Fixed Unknown names in activity_log.json")
else:
    print("No Unknown names needed fixing")
