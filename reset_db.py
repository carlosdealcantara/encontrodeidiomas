from worker import get_db_connection

conn = get_db_connection()
cursor = conn.cursor()
cursor.execute("UPDATE odysee_publish_queue SET status='pending' WHERE status='processing'")
conn.commit()
print('Rows affected:', cursor.rowcount)
