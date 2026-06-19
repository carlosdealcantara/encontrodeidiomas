import json

filepath = 'scratch/activity_log.json'
with open(filepath, 'r', encoding='utf-8') as f:
    data = json.load(f)

# JIDs conhecidos
FLAVIA_JID    = '277583904125013@lid'
RAYZA_JID     = '90370440462475@lid'
ENCONTRO_JID  = '82073285095670@lid'
OUR_CLASSES   = '120363228807801778@g.us'
OUTRO_GRUPO   = '120363246518434750@g.us'
OUTRO_GRUPO2  = '120363426891416419@g.us'  # grupo onde está o Encontro de Idiomas

fixes = []

# --- 1. Restaurar o !attend legítimo da Flávia em Our Classes (2026-06-16) ---
# Ela tinha 1 mensagem que zeramos erroneamente (era o !attend)
if FLAVIA_JID in data.get('2026-06-16', {}).get(OUR_CLASSES, {}):
    before = data['2026-06-16'][OUR_CLASSES][FLAVIA_JID]['messages']
    data['2026-06-16'][OUR_CLASSES][FLAVIA_JID]['messages'] = 1
    fixes.append(f'Flávia Our Classes 2026-06-16: msg {before} -> 1 (restaura !attend)')

# --- 2. Restaurar 1 mensagem legítima da Rayza em Our Classes (2026-06-16) ---
# Ela tinha 1 mensagem real que zeramos
if RAYZA_JID in data.get('2026-06-16', {}).get(OUR_CLASSES, {}):
    before = data['2026-06-16'][OUR_CLASSES][RAYZA_JID]['messages']
    data['2026-06-16'][OUR_CLASSES][RAYZA_JID]['messages'] = 1
    fixes.append(f'Rayza Our Classes 2026-06-16: msg {before} -> 1 (restaura 1 msg real)')

# --- 3. Restaurar 1 mensagem legítima da Rayza em Our Classes (2026-06-17, o !attend de hoje) ---
if RAYZA_JID in data.get('2026-06-17', {}).get(OUR_CLASSES, {}):
    before = data['2026-06-17'][OUR_CLASSES][RAYZA_JID]['messages']
    data['2026-06-17'][OUR_CLASSES][RAYZA_JID]['messages'] = 1
    fixes.append(f'Rayza Our Classes 2026-06-17: msg {before} -> 1 (restaura !attend de hoje)')

# --- 4. Remover completamente "Encontro de Idiomas" de TODOS os grupos e datas ---
removed = 0
for date in data:
    for gid in list(data[date].keys()):
        if ENCONTRO_JID in data[date][gid]:
            del data[date][gid][ENCONTRO_JID]
            removed += 1
            fixes.append(f'Removido Encontro de Idiomas do grupo {gid[:22]} em {date}')

with open(filepath, 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2, ensure_ascii=False)

print(f'Feitas {len(fixes)} correcoes:')
for f in fixes:
    print(f'  - {f}')
