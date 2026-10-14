import mysql.connector, os, sys

try:
    conn = mysql.connector.connect(
        host=os.getenv("DB_HOST"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASS"),
        database=os.getenv("DB_NAME")
    )
    c = conn.cursor(dictionary=True)
    
    # MENTORIA_ODYSEE_LANGUAGE_ID = 1 = Ingles
    # Mas na linha 982: clean_channel_name = channel_name.lstrip('@')
    # e channel_name vem de creds['odysee_channel_name'] = languages WHERE id = MENTORIA_ODYSEE_LANGUAGE_ID
    
    lang_id = os.getenv("MENTORIA_ODYSEE_LANGUAGE_ID", "1")
    print(f"MENTORIA_ODYSEE_LANGUAGE_ID = {lang_id}")
    
    c.execute(f"SELECT id, name, odysee_channel_name, odysee_auth_token FROM languages WHERE id = {lang_id}")
    lang = c.fetchone()
    print(f"Credenciais usadas: {lang['name']} | Channel: {lang['odysee_channel_name']}")
    token_preview = lang['odysee_auth_token'][:20] + "..." if lang['odysee_auth_token'] else "NONE"
    print(f"Token: {token_preview}")
    
    # Verifica se a conta (token) tem o canal configurado
    # O vídeo publicado como "Anonymous" pode ser porque o token expirou ou não tem canal vinculado
    
except Exception as e:
    print(f"ERRO: {e}", file=sys.stderr)
