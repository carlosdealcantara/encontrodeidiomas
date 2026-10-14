import sys
sys.path.insert(0, '/app')
import os
os.chdir('/app')
from mentoria_worker import get_db_connection

conn = get_db_connection()
c = conn.cursor(dictionary=True)
c.execute('SELECT id, status, titulo_final, odysee_url, whatsapp_message, error_message, retry_count, processed_at FROM mentoria_odysee_queue WHERE id IN (67, 68, 154)')
rows = c.fetchall()
for r in rows:
    print(f"\n=== ID {r['id']} ===")
    print(f"  status: {r['status']!r}")
    print(f"  titulo: {r['titulo_final']!r}")
    print(f"  odysee_url: {r['odysee_url']!r}")
    print(f"  whatsapp_message: {r['whatsapp_message']!r}")
    print(f"  error_message: {r['error_message']!r}")
    print(f"  retry_count: {r['retry_count']}")
    print(f"  processed_at: {r['processed_at']}")
