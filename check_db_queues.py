import mysql.connector, os, sys
try:
    conn = mysql.connector.connect(
        host=os.getenv("DB_HOST"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASS"),
        database=os.getenv("DB_NAME")
    )
    c = conn.cursor(dictionary=True)
    print("=== MENTORIA QUEUE ===")
    c.execute("SELECT id, status, video_title, updated_at FROM mentoria_odysee_queue WHERE status IN ('processing', 'pending', 'error') ORDER BY updated_at DESC LIMIT 10")
    for r in c.fetchall():
        print(r)
    print("=== INGLES QUEUE ===")
    c.execute("SELECT id, status, video_title, updated_at FROM odysee_queue WHERE status IN ('processing', 'pending', 'error') ORDER BY updated_at DESC LIMIT 10")
    for r in c.fetchall():
        print(r)
except Exception as e:
    print(f"ERRO: {e}", file=sys.stderr)
