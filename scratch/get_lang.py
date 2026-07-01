import mysql.connector

conn = mysql.connector.connect(
    host="77.37.127.146",
    user="u879045076_carlos",
    password="#Nadier38",
    database="u879045076_central"
)
cursor = conn.cursor(dictionary=True)
cursor.execute("SELECT id, name, odysee_channel_name FROM languages WHERE id = 10")
print(cursor.fetchone())
