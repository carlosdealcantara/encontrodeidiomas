import mysql.connector

conn = mysql.connector.connect(
    host="77.37.127.146",
    user="u879045076_carlos",
    password="#Nadier38",
    database="u879045076_central"
)
cursor = conn.cursor(dictionary=True)
cursor.execute("SELECT id, LENGTH(last_screenshot) as size, last_screenshot_time FROM odysee_publish_queue WHERE id = 26")
row = cursor.fetchone()
print(f"ID: {row['id']} | Size: {row['size']} bytes | Time: {row['last_screenshot_time']}")
cursor.close()
conn.close()
