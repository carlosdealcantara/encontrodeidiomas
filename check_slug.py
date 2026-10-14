import mysql.connector, os, sys, requests

try:
    conn = mysql.connector.connect(
        host=os.getenv("DB_HOST"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASS"),
        database=os.getenv("DB_NAME")
    )
    c = conn.cursor(dictionary=True)
    
    # Pega o slug da mentoria ID 65
    c.execute("SELECT odysee_slug FROM mentoria_odysee_queue WHERE id = 65")
    row = c.fetchone()
    
    # Pega o channel name
    c.execute("SELECT odysee_channel_name FROM languages WHERE id = %s", (os.getenv('MENTORIA_ODYSEE_LANGUAGE_ID', '10'),))
    lang = c.fetchone()
    
    print(f"Slug: {row}")
    print(f"Language/channel: {lang}")
    
    # Tenta descobrir o channel name de outra forma
    c.execute("SELECT * FROM languages WHERE id = 10")
    lang_full = c.fetchone()
    print(f"Language full: {lang_full}")
    
except Exception as e:
    print(f"ERRO: {e}", file=sys.stderr)
