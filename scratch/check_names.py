import json

data = json.load(open('/home/ubuntu/encontrodeidiomas/baileys-server/data/activity_log.json'))
dates = sorted(data.keys())[-3:]
for d in dates:
    print(f"\n=== {d} ===")
    for grp, members in data[d].items():
        for jid, info in members.items():
            name = info.get('name', 'SEM_NOME')
            msgs = info.get('messages', 0)
            reacts = info.get('reactions_given', 0)
            print(f"  JID: {jid}  NAME: {name}  msgs:{msgs} reacts:{reacts}")
