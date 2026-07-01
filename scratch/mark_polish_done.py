import sys
sys.path.append('/app')
from worker import get_db_connection
c = get_db_connection()
cursor = c.cursor()
cursor.execute("UPDATE odysee_publish_queue SET status = 'done', error_message = 'Cancelado manualmente pois video ja postado' WHERE language_id = 10 AND status IN ('pending', 'processing')")
print(f"Atualizado: {cursor.rowcount}")
c.commit()
c.close()
