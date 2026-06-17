import json

f = '/home/ubuntu/encontrodeidiomas/baileys-server/data/activity_log.json'
with open(f, 'r') as fp:
    data = json.load(fp)

removed = 0
for date_key in data:
    for group_jid in list(data[date_key].keys()):
        members = data[date_key][group_jid]
        phantom_keys = [m for m in members if m.endswith('@g.us')]
        for k in phantom_keys:
            print(f"Removing phantom member: {k} ({members[k].get('name','?')}) from group {group_jid} on {date_key}")
            del members[k]
            removed += 1

with open(f, 'w') as fp:
    json.dump(data, fp, ensure_ascii=False, indent=2)

print(f"\nDone. Removed {removed} phantom entries.")
