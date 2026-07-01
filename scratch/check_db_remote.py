import sys
sys.path.append('/app')
from worker import get_db_connection
c = get_db_connection()
cursor = c.cursor(dictionary=True)
cursor.execute('SELECT id, language_id, status, titulo_final, odysee_url FROM odysee_publish_queue ORDER BY id DESC LIMIT 5')
for r in cursor.fetchall():
    print(r)
