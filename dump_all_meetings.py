import mysql.connector

conn = mysql.connector.connect(
    host='77.37.127.146',
    user='u879045076_ei',
    password='#Nadier38',
    database='u879045076_ei'
)
cur = conn.cursor(dictionary=True)
cur.execute("SELECT * FROM meetings")
rows = cur.fetchall()
for r in rows:
    print(r['id'], r['language_id'], r['title'], r['day_of_week'], r['time_hour'], r['meet_link'])
