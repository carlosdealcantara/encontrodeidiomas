import mysql.connector, os, sys
try:
    conn = mysql.connector.connect(
        host=os.getenv("DB_HOST"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASS"),
        database=os.getenv("DB_NAME")
    )
    c = conn.cursor(dictionary=True)
    print("=== INGLES QUEUE COLS ===")
    c.execute("DESCRIBE odysee_publish_queue")
    for r in c.fetchall():
        print(r["Field"], r["Type"])
    print("=== INGLES QUEUE STATUS ===")
    c.execute("SELECT id, status, drive_file_name, titulo_final, error_message, processed_at FROM odysee_publish_queue WHERE status IN ('processing', 'pending', 'error') ORDER BY created_at DESC LIMIT 10")
    for r in c.fetchall():
        print(r)
except Exception as e:
    print(f"ERRO: {e}", file=sys.stderr)
