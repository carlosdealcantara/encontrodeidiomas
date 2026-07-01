import sys
sys.path.append('/app')
from worker import get_db_connection
c = get_db_connection()
cursor = c.cursor(dictionary=True)
cursor.execute('SELECT id, titulo, mensagem, status FROM wpp_broadcast_queue ORDER BY id DESC LIMIT 5')
for r in cursor.fetchall():
    print(r)
