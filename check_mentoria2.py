import mysql.connector, os, sys
try:
    conn = mysql.connector.connect(
        host=os.getenv("DB_HOST"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASS"),
        database=os.getenv("DB_NAME")
    )
    c = conn.cursor(dictionary=True)
    c.execute("SELECT id, status, drive_file_name, titulo_final, error_message, retry_count, odysee_url, processed_at FROM mentoria_odysee_queue ORDER BY id DESC LIMIT 10")
    for r in c.fetchall():
        print(f"ID={r['id']} status={r['status']} retry={r['retry_count']} url={r['odysee_url']}")
        print(f"  arquivo: {r['drive_file_name']}")
        print(f"  erro: {str(r['error_message'])[:200]}")
        print()
except Exception as e:
    print(f"ERRO: {e}", file=sys.stderr)
