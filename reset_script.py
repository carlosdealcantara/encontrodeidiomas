import mysql.connector, os, sys

try:
    conn = mysql.connector.connect(
        host=os.getenv("DB_HOST"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASS"),
        database=os.getenv("DB_NAME")
    )
    c = conn.cursor(dictionary=True)
    c.execute("UPDATE mentoria_odysee_queue SET status='pending', retry_count=0, error_message='[RESET] Retomando para captura de link' WHERE id=65")
    conn.commit()
    print("Sucesso! Linhas afetadas:", c.rowcount)
    c.execute("SELECT id, status, retry_count, odysee_slug, error_message FROM mentoria_odysee_queue WHERE id=65")
    print("Registro atual:", c.fetchone())
    conn.close()
except Exception as e:
    print("ERRO:", e, file=sys.stderr)
