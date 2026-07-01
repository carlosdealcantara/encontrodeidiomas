import mysql.connector

conn = mysql.connector.connect(
    host="localhost",
    user="root",
    password="SenhaMeetups2026",
    database="encontrodeidiomas"
)
cursor = conn.cursor(dictionary=True)
cursor.execute("SELECT id, language_id, status, titulo_final, created_at, updated_at FROM odysee_publish_queue ORDER BY id DESC LIMIT 5;")
rows = cursor.fetchall()
for r in rows:
    print(r)
