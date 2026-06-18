import json

filepath = 'scratch/activity_log.json'
with open(filepath, 'r', encoding='utf-8') as f:
    data = json.load(f)

RAYZA_JID = '90370440462475@lid'
DESAFIO_GROUP = '120363246518434750@g.us'

if RAYZA_JID in data.get('2026-06-16', {}).get(DESAFIO_GROUP, {}):
    data['2026-06-16'][DESAFIO_GROUP][RAYZA_JID]['messages'] = 3
    print("Restauradas 3 msgs da Rayza no Desafio!")

with open(filepath, 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2, ensure_ascii=False)
