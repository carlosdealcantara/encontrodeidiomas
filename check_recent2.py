import mysql.connector, os, sys
try:
    conn = mysql.connector.connect(
        host=os.getenv("DB_HOST"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASS"),
        database=os.getenv("DB_NAME")
    )
    c = conn.cursor(dictionary=True)
    print("=== INGLES QUEUE - TODOS RECENTES ===")
    c.execute("SELECT id, status, drive_file_name, titulo_final, error_message, odysee_url, processed_at FROM odysee_publish_queue ORDER BY id DESC LIMIT 15")
    for r in c.fetchall():
        print(r)
except Exception as e:
    print(f"ERRO: {e}", file=sys.stderr)
