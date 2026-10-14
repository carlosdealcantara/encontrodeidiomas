import json

path = '/app/data/mentoria_config_es.json'
with open(path, 'r', encoding='utf-8') as f:
    data = json.load(f)

if 'templates' in data:
    for k in ['class_aviso', 'practice_aviso']:
        if k in data['templates'] and data['templates'][k]:
            data['templates'][k] = data['templates'][k].replace('!attend', '!confirmar')

with open(path, 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2, ensure_ascii=False)

print("SUCESSO: templates class_aviso e practice_aviso agora usam !confirmar!")
