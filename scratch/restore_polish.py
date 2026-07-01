import mysql.connector
import os

conn = mysql.connector.connect(
    host="77.37.127.146",
    user="u879045076_carlos",
    password="#Nadier38",
    database="u879045076_central"
)
cursor = conn.cursor()
cursor.execute("UPDATE meetup_replays SET link = 'https://is.gd/G848Wy' WHERE language_id = 10 AND (link IS NULL OR link = '') ORDER BY semana DESC LIMIT 1")
conn.commit()
print("Link do polonês restaurado!")
cursor.close()
conn.close()
