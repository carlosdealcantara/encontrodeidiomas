import sys
sys.path.append('/app')
from worker import get_db_connection
conn = get_db_connection()
c = conn.cursor(dictionary=True)
# Verifica os registros afetados
c.execute("SELECT id, status, titulo_final, odysee_url FROM mentoria_odysee_queue WHERE id >= 68")
rows = c.fetchall()
print(f"Encontrados {len(rows)} registros para limpar:")
for r in rows:
    print(r)

# Deleta os registros que estavam pending ou processing para não serem processados
c.execute("DELETE FROM mentoria_odysee_queue WHERE id >= 68 AND status IN ('pending', 'processing')")
deleted = c.rowcount
# Marca a tarefa 154 (já enviada) como cancelada/done_privado
c.execute("UPDATE mentoria_odysee_queue SET status='cancelled', error_message='Cancelado: Aula particular indevida' WHERE id >= 68")
conn.commit()
print(f"Deletados {deleted} registros indevidos da fila!")


