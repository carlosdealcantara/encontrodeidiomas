import requests
import json
import urllib.parse
import mysql.connector

# Buscar a última postagem do canal polonês na API da LBRY
url = 'https://api.na-backend.odysee.com/api/v1/proxy'
payload = {
    "method": "claim_search",
    "params": {
        "channel": "@EncontrodeIdiomasPolones",
        "order_by": ["release_time"],
        "page_size": 1
    }
}

try:
    res = requests.post(url, json=payload)
    data = res.json()
    if 'result' in data and 'items' in data['result'] and len(data['result']['items']) > 0:
        latest_claim = data['result']['items'][0]
        claim_name = latest_claim['name']
        channel_name = latest_claim['signing_channel']['name']
        
        odysee_url = f"https://odysee.com/{channel_name}/{claim_name}"
        print(f"URL Original: {odysee_url}")
        
        # Encurtar via is.gd
        r = requests.get(f"https://is.gd/create.php?format=simple&url={urllib.parse.quote(odysee_url)}", timeout=5)
        url_curta = r.text.strip() if r.status_code == 200 else odysee_url
        print(f"URL Curta: {url_curta}")
        
        # Conectar ao DB e atualizar
        conn = mysql.connector.connect(
            host="77.37.127.146",
            user="u879045076_carlos",
            password="#Nadier38",
            database="u879045076_central"
        )
        cursor = conn.cursor()
        # ID 25 é o Polonês
        cursor.execute("UPDATE odysee_publish_queue SET odysee_url = %s WHERE id = 25", (url_curta,))
        cursor.execute("UPDATE meetup_replays SET link = %s WHERE language_id = 10 AND (link IS NULL OR link = '') ORDER BY semana DESC LIMIT 1", (url_curta,))
        conn.commit()
        print(f"Banco de dados atualizado com sucesso! URL definida como {url_curta}")
        cursor.close()
        conn.close()
        
    else:
        print("Nenhum vídeo encontrado no canal polonês.")
except Exception as e:
    print(f"Erro: {e}")
