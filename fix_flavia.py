import json
f='/home/ubuntu/encontrodeidiomas/baileys-server/data/activity_log.json'
d=json.load(open(f))
if '2026-08-07' in d and '120363246518434750@g.us' in d['2026-08-07'] and '277583904125013@lid' in d['2026-08-07']['120363246518434750@g.us']:
    d['2026-08-07']['120363246518434750@g.us']['277583904125013@lid']['images_sent'] = 1
    json.dump(d, open(f, 'w'), indent=2)
    print('Fixed Flavia!')
else:
    print('Flavia not found')
