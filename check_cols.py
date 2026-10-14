import mysql.connector, os, sys
try:
    conn = mysql.connector.connect(
        host=os.getenv("DB_HOST"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASS"),
        database=os.getenv("DB_NAME")
    )
    c = conn.cursor(dictionary=True)
    print("=== MENTORIA QUEUE cols ===")
    c.execute("DESCRIBE mentoria_odysee_queue")
    for r in c.fetchall():
        print(r["Field"], r["Type"])
    print("=== INGLES QUEUE cols ===")
    c.execute("DESCRIBE odysee_queue")
    for r in c.fetchall():
        print(r["Field"], r["Type"])
except Exception as e:
    print(f"ERRO: {e}", file=sys.stderr)
