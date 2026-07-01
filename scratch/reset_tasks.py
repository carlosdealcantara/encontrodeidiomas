import mysql.connector

conn = mysql.connector.connect(
    host="77.37.127.146",
    user="u879045076_carlos",
    password="#Nadier38",
    database="u879045076_central"
)
cursor = conn.cursor()
cursor.execute("UPDATE odysee_publish_queue SET status='pending' WHERE status IN ('processing')")
conn.commit()
print("Tasks reset to pending!")
cursor.close()
conn.close()
