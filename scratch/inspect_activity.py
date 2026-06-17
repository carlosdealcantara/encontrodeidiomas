import json

f = '/home/ubuntu/encontrodeidiomas/baileys-server/data/activity_log.json'
with open(f, 'r') as fp:
    data = json.load(fp)

# Print structure summary
for date_key in data:
    print(f"\nDate: {date_key}")
    for group_jid in data[date_key]:
        print(f"  Group: {group_jid}")
        for member_jid in data[date_key][group_jid]:
            member = data[date_key][group_jid][member_jid]
            name = member.get('name', '?')
            print(f"    Member: {member_jid} ({name})")
