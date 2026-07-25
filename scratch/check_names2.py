import json

data = json.load(open('/home/ubuntu/encontrodeidiomas/baileys-server/data/activity_log.json'))

# Foca apenas no dia do problema
date = '2026-07-22'
print(f"\n=== DIAGNÓSTICO DETALHADO: {date} ===\n")

jid_julyana = '207605951635698@lid'

if date in data:
    for grp, members in data[date].items():
        print(f"GRUPO: {grp}")
        for jid, info in members.items():
            if jid == jid_julyana:
                print(f"  *** JULYANA ENCONTRADA ***")
                print(f"  JID: {jid}")
                print(f"  Name: '{info.get('name', 'SEM_NOME')}'")
                print(f"  Messages: {info.get('messages', 0)}")
                print(f"  Reactions: {info.get('reactions_given', 0)}")
                print(f"  Images: {info.get('images_sent', 0)}")
                print(f"  Audios: {info.get('audios_sent', 0)}")
                print()

# Mostra TODOS os registros do JID da Julyana em todas as datas
print("\n=== HISTÓRICO COMPLETO DO JID DA JULYANA ===")
for d in sorted(data.keys()):
    for grp, members in data[d].items():
        if jid_julyana in members:
            name = members[jid_julyana].get('name', 'SEM_NOME')
            print(f"  {d} | grupo:{grp[-20:]} | nome: '{name}'")
