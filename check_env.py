import mysql.connector, os, sys, requests

try:
    conn = mysql.connector.connect(
        host=os.getenv("DB_HOST"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASS"),
        database=os.getenv("DB_NAME")
    )
    c = conn.cursor(dictionary=True)

    # Verifica a env var
    lang_id = os.getenv("MENTORIA_ODYSEE_LANGUAGE_ID", "NOT SET")
    print(f"MENTORIA_ODYSEE_LANGUAGE_ID env: {lang_id}")
    
    # Lista todas as envs relevantes
    for k, v in os.environ.items():
        if "MENTORIA" in k or "LANGUAGE" in k or "CHANNEL" in k or "ODYSEE" in k:
            print(f"  {k}={v[:50] if v else 'None'}")

    # Testa a API LBRY com o slug que temos
    slug = "mentorship_2026_09_23_13h"
    
    # Pega o channel_name do environment ou tenta descobrir da tabela mentoria_odysee_queue
    c.execute("SELECT * FROM mentoria_odysee_queue WHERE id = 65")
    row65 = c.fetchone()
    print(f"\nMentoria ID 65 completo: {row65}")
    
except Exception as e:
    print(f"ERRO: {e}", file=sys.stderr)
