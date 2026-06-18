import json, sys, io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

d = json.load(open('scratch/activity_log.json', encoding='utf-8'))
targets = ['rayza', 'fl', 'encontro', 'julyana', 'juliana']

for date in sorted(d.keys())[-3:]:
    print(f'\n=== {date} ===')
    for gid, members in d[date].items():
        for mid, s in members.items():
            name = s.get('name','').lower()
            if any(x in name for x in targets):
                safe_name = s.get('name','').encode('ascii','replace').decode()
                print(f'  [{gid[:22]}] {safe_name}: msg={s.get("messages",0)} img={s.get("images_sent",0)} aud={s.get("audios_sent",0)} react={s.get("reactions_given",0)} | JID={mid}')
