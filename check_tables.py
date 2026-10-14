import mysql.connector, os, sys
try:
    conn = mysql.connector.connect(
        host=os.getenv("DB_HOST"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASS"),
        database=os.getenv("DB_NAME")
    )
    c = conn.cursor(dictionary=True)
    print("=== TODAS AS TABELAS ===")
    c.execute("SHOW TABLES")
    for r in c.fetchall():
        vals = list(r.values())
        if "odysee" in str(vals[0]).lower() or "queue" in str(vals[0]).lower():
            print(vals[0])
    print("=== MENTORIA QUEUE STATUS ===")
    c.execute("SELECT id, status, drive_file_name, titulo_final, processed_at FROM mentoria_odysee_queue WHERE status IN ('processing', 'pending', 'error') ORDER BY created_at DESC LIMIT 10")
    for r in c.fetchall():
        print(r)
except Exception as e:
    print(f"ERRO: {e}", file=sys.stderr)
