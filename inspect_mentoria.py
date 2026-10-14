import sys
sys.path.append('/app')
from mentoria_worker import get_db_connection

conn = get_db_connection()
c = conn.cursor(dictionary=True)

print("=== REGISTROS RECENTES DE mentoria_odysee_queue ===")
c.execute("SELECT id, status, drive_file_name, titulo_final, odysee_url, drive_file_id, created_at, processed_at, error_message FROM mentoria_odysee_queue ORDER BY id DESC LIMIT 15")
for r in c.fetchall():
    print(r)
