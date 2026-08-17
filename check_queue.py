from worker import get_db_connection
conn = get_db_connection()
cursor = conn.cursor()
cursor.execute("CREATE TABLE IF NOT EXISTS odysee_worker_restarts (id INT AUTO_INCREMENT PRIMARY KEY, restart_time DATETIME DEFAULT CURRENT_TIMESTAMP, reason VARCHAR(255));")
conn.commit()
cursor.close()
conn.close()
print("Tabela odysee_worker_restarts criada com sucesso!")
