import mysql.connector

conn = mysql.connector.connect(
    host='host.docker.internal',
    user='root',
    password='meetups2024',
    database='encontrodeidiomas'
)
cursor = conn.cursor()
cursor.execute("INSERT INTO settings (setting_key, setting_value) VALUES ('tly_api_key', 'sExaM2oyhcL6NfqykK3dMHbPbWM82LmptmJQLBXal5cP2ClhBgdATaN1Z6JB') ON DUPLICATE KEY UPDATE setting_value = 'sExaM2oyhcL6NfqykK3dMHbPbWM82LmptmJQLBXal5cP2ClhBgdATaN1Z6JB'")
cursor.execute("UPDATE odysee_publish_queue SET odysee_url = 'https://t.ly/5Ic6e' WHERE language_id = 7 ORDER BY id DESC LIMIT 1")
conn.commit()
print("DB UPDATE SUCCESS")
