import sys
sys.path.append('/app')
from worker import get_db_connection
c = get_db_connection()
cursor = c.cursor()
cursor.execute("UPDATE odysee_publish_queue SET status='error' WHERE status='processing' AND id < 25")
c.commit()
print('Fix applied!')
