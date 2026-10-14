import sys
sys.path.insert(0, '/app')
import os
os.chdir('/app')
import requests
from mentoria_worker import get_odysee_credentials, get_db_connection

creds = get_odysee_credentials()
auth_token = creds['odysee_auth_token']
print(f"Token (first 30 chars): {auth_token[:30]}...")

# Slug do Franklin publicado indevidamente
franklin_slug = "mentorship_2025_10_13_19h"
claim_id = "dbd3b6334085f994bc1ac6a796a4e95666a0fb17"

# Tenta abandonar via API LBRY com o token como query param
# O Odysee usa o token como query param: ?auth_token=...
abandon_resp = requests.post(
    f"https://api.na-backend.odysee.com/api/v1/proxy?m=stream_abandon&auth_token={auth_token}",
    json={
        "jsonrpc": "2.0",
        "method": "stream_abandon",
        "params": {"claim_id": claim_id}
    },
    timeout=30
)
print(f"Abandon v1 HTTP: {abandon_resp.status_code}")
print(f"Abandon v1 Body: {abandon_resp.text[:400]}")
