from worker import get_db_connection
conn = get_db_connection()
c = conn.cursor(dictionary=True)
c.execute("SELECT q.id, l.name, q.status, q.titulo_final, q.odysee_url, q.created_at FROM odysee_publish_queue q JOIN languages l ON q.language_id = l.id ORDER BY q.id DESC LIMIT 5")
for r in c.fetchall():
    print(r)
