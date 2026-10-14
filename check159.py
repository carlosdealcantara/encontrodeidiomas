from worker import get_db_connection
conn = get_db_connection()
c = conn.cursor(dictionary=True)
c.execute("SELECT id, status, titulo_final, odysee_url, error_message FROM odysee_publish_queue WHERE id=159")
print(c.fetchone())
