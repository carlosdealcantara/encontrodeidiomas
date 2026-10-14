from worker import get_db_connection

conn = get_db_connection()
c = conn.cursor(dictionary=True)
c.execute("UPDATE mentoria_odysee_queue SET status='pending', retry_count=0, error_message='[RESET] Retomando para captura de link' WHERE id=65")
conn.commit()
print(f"Sucesso: {c.rowcount} registro(s) resetado(s).")

c.execute("SELECT id, status, retry_count, odysee_slug, error_message FROM mentoria_odysee_queue WHERE id=65")
print("Registro atual:", c.fetchone())
conn.close()
