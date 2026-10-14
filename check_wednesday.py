import os, mysql.connector
try:
    conn = mysql.connector.connect(
        host=os.getenv('DB_HOST'),
        user=os.getenv('DB_USER'),
        password=os.getenv('DB_PASS'),
        database=os.getenv('DB_NAME')
    )
    cur = conn.cursor(dictionary=True)
    cur.execute('SELECT id, day_of_week, start_time, group_jid, lang_id FROM class_schedule WHERE day_of_week = 3')
    rows = cur.fetchall()
    for r in rows:
        print(r)
except Exception as e:
    print(f"ERRO: {e}")
