import mysql.connector
import os

conn = mysql.connector.connect(
    host='195.35.43.197',
    user='u879045076_carlos',
    password='#Nadier38',
    database='u879045076_central'
)
cursor = conn.cursor()
cursor.execute('SELECT name FROM languages')
for row in cursor.fetchall():
    print(row[0])
