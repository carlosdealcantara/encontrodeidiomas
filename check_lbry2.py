import mysql.connector, os, sys, requests

try:
    conn = mysql.connector.connect(
        host=os.getenv("DB_HOST"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASS"),
        database=os.getenv("DB_NAME")
    )
    c = conn.cursor(dictionary=True)
    
    # Language ID 1
    c.execute("SELECT id, name, odysee_channel_name, odysee_channel_id FROM languages WHERE id = 1")
    lang = c.fetchone()
    print(f"Language ID 1: {lang}")
    
    # Testa a API LBRY com o channel correto
    slug = "mentorship_2026_09_23_13h"
    channel = lang["odysee_channel_name"].lstrip("@") if lang else ""
    lbry_url = f"lbry://@{channel}/{slug}"
    print(f"\nTestando LBRY URL: {lbry_url}")
    
    api_url = "https://api.na-backend.odysee.com/api/v1/proxy?m=resolve"
    payload = {"jsonrpc": "2.0", "method": "resolve", "params": {"urls": [lbry_url]}}
    res = requests.post(api_url, json=payload, timeout=15)
    data = res.json()
    result = data.get("result", {})
    entry = result.get(lbry_url, {})
    if entry and "error" not in entry:
        print(f"ENCONTRADO!")
        print(f"  canonical_url: {entry.get('canonical_url', 'N/A')}")
        print(f"  short_url: {entry.get('short_url', 'N/A')}")
        print(f"  value.title: {entry.get('value', {}).get('title', 'N/A')}")
    else:
        print(f"NAO encontrado. Erro: {entry.get('error', {})}")
        
except Exception as e:
    print(f"ERRO: {e}", file=sys.stderr)
