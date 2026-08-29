"""
GERADOR DE TOKEN OAUTH PARA O WORKER DO DRIVE
================================================
Execute este script UMA VEZ na sua máquina local.
Ele abre o navegador para você autenticar com a conta Google
dona dos Drives e salva o refresh_token para uso permanente.

PRÉ-REQUISITO:
  pip install google-auth-oauthlib

PASSO A PASSO:
  1. Acesse: https://console.cloud.google.com/apis/credentials?project=ei-drive-odysee
  2. Clique em "+ CREATE CREDENTIALS" > "OAuth client ID"
  3. Tipo: "Desktop app" | Nome: "odysee-worker-oauth"
  4. Baixe o JSON e salve como "client_secret.json" NA MESMA PASTA DESTE SCRIPT
  5. Execute: python generate_oauth_token.py
  6. O navegador vai abrir — faça login com a conta Google dona dos Drives
  7. O arquivo "google_oauth_token.json" será salvo automaticamente
"""

import json
import os
from google_auth_oauthlib.flow import InstalledAppFlow

SCOPES = ['https://www.googleapis.com/auth/drive']
CLIENT_SECRET_FILE = 'client_secret.json'
OUTPUT_TOKEN_FILE = 'google_oauth_token.json'

if not os.path.exists(CLIENT_SECRET_FILE):
    print(f"ERRO: Arquivo '{CLIENT_SECRET_FILE}' nao encontrado.")
    print("Baixe o OAuth 2.0 Client ID (Desktop app) do Google Cloud Console e salve como 'client_secret.json'.")
    print("URL: https://console.cloud.google.com/apis/credentials?project=ei-drive-odysee")
    exit(1)

print("Abrindo navegador para autenticacao...")
print("Faca login com a conta Google que e dona das gravacoes do Google Meet.\n")

flow = InstalledAppFlow.from_client_secrets_file(CLIENT_SECRET_FILE, SCOPES)
creds = flow.run_local_server(port=0, open_browser=True)

# Salva o token completo (incluindo refresh_token que nunca expira)
token_data = {
    "token": creds.token,
    "refresh_token": creds.refresh_token,
    "token_uri": creds.token_uri,
    "client_id": creds.client_id,
    "client_secret": creds.client_secret,
    "scopes": list(creds.scopes),
}

with open(OUTPUT_TOKEN_FILE, 'w') as f:
    json.dump(token_data, f, indent=2)

print(f"\nToken salvo com sucesso em '{OUTPUT_TOKEN_FILE}'")
print("\nProximo passo: envie este arquivo ao Antigravity para ele fazer o upload para o servidor.")
