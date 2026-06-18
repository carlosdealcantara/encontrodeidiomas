import json
import os

filepath = os.path.join(os.path.dirname(__file__), 'activity_log.json')
with open(filepath, 'r', encoding='utf-8') as f:
    data = json.load(f)

dates = ['2026-06-16', '2026-06-17']
fixed = 0

for date in dates:
    if date not in data:
        continue
    for groupJid, members in data[date].items():
        for memberJid, stats in members.items():
            name = stats.get('name', '').lower()
            
            if 'rayza' in name and stats.get('messages', 0) > 0:
                print(f"Corrigindo Rayza no grupo {groupJid} em {date}: de {stats['messages']} para 0.")
                stats['messages'] = 0
                fixed += 1
                
            if ('flávia' in name or 'flavia' in name) and stats.get('messages', 0) > 0:
                print(f"Corrigindo Flavia no grupo {groupJid} em {date}: zerando {stats['messages']} mensagens indevidas.")
                stats['messages'] = 0
                fixed += 1

if fixed > 0:
    with open(filepath, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=2, ensure_ascii=False)
    print(f"Pronto! Foram feitas {fixed} correções locais.")
else:
    print("Nenhuma correção necessária ou nomes não encontrados.")
