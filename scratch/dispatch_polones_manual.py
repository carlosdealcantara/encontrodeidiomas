import os, requests, mysql.connector

conn = mysql.connector.connect(
    host="77.37.127.146", user="u879045076_carlos",
    password="#Nadier38", database="u879045076_central"
)
cursor = conn.cursor(dictionary=True)

# Busca tarefa #25 (Polonês)
cursor.execute("""
    SELECT q.*, l.flag_emoji, l.name as language_name, l.odysee_channel_name, l.whatsapp_group_id, l.id as lang_id
    FROM odysee_publish_queue q JOIN languages l ON q.language_id = l.id
    WHERE q.id = 25
""")
tarefa = cursor.fetchone()

print(f"Tarefa: #{tarefa['id']} | {tarefa['titulo_final']} | Status: {tarefa['status']}")
url_curta = tarefa['odysee_url']
print(f"URL curta: {url_curta}")

# Busca template
cursor.execute("SELECT setting_value FROM settings WHERE setting_key='odysee_whatsapp_template'")
row = cursor.fetchone()
template = row['setting_value'] if row else "🎬 *Replay:* {bandeira} {titulo}\n\n🔗 {link}"

# Busca grupos
cursor.execute("""
    SELECT group_id FROM meetup_whatsapp_groups
    WHERE ativo=1 AND (categoria='multi_idioma' OR (categoria='especifico' AND language_id=%s))
""", (tarefa['language_id'],))
grupos = [r['group_id'] for r in cursor.fetchall()]

mensagem = template.replace('{titulo}', tarefa['titulo_final'] or '').replace('{link}', url_curta).replace('{idioma}', tarefa['language_name'] or '').replace('{bandeira}', tarefa['flag_emoji'] or '')

print(f"\nMensagem:\n{mensagem}")
print(f"Grupos: {len(grupos)}")

for gid in grupos:
    try:
        payload = {
            "to": gid,
            "message": mensagem,
            "source": "odysee_pipeline_manual",
            "linkPreview": {
                "title": tarefa['titulo_final'],
                "body": "Disponível agora no Odysee",
                "url": url_curta
            }
        }
        resp = requests.post("http://127.0.0.1:3000/send", json=payload, headers={"apikey": "SenhaMeetups2026"}, timeout=15)
        print(f"  [{resp.status_code}] {gid}")
    except Exception as e:
        print(f"  [ERR] {gid}: {e}")

# Notifica hosts via webhook
try:
    resp = requests.post("https://dev.encontrodeidiomas.com.br/ajax/webhook_odysee_success.php", json={
        "apikey": "SenhaMeetups2026", "lang_id": tarefa['language_id']
    }, timeout=15)
    print(f"\nWebhook Hosts: {resp.status_code}")
except Exception as e:
    print(f"Webhook Hosts erro: {e}")

cursor.close()
conn.close()
print("\n✅ Concluído!")
