import os
import mysql.connector

conn = mysql.connector.connect(
    host=os.getenv('DB_HOST'),
    user=os.getenv('DB_USER'),
    password=os.getenv('DB_PASS'),
    database=os.getenv('DB_NAME')
)
cursor = conn.cursor(dictionary=True)
cursor.execute('SELECT id, name FROM languages')
for row in cursor.fetchall():
    print(f"{row['id']} = {row['name']}")
