#!/usr/bin/env python3
"""
Script de correção emergencial:
1. Deleta o vídeo Franklin do Odysee via API LBRY
2. Reenvia WPP para o Mentorship Class - 2026/09/28 (ID 67)
3. Corrige status no banco: ID 68 → done, ID 154 → cancelled
"""
import sys
sys.path.insert(0, '/app')
import os
os.chdir('/app')
import requests
import json
from mentoria_worker import get_db_connection, get_odysee_credentials, MENTORIA_ODYSEE_LANGUAGE_ID

BAILEYS_BASE = "http://host.docker.internal:3000"
BAILEYS_KEY = "SenhaMeetups2026"

print("=" * 60)
print("SCRIPT DE CORREÇÃO EMERGENCIAL")
print("=" * 60)

conn = get_db_connection()
c = conn.cursor(dictionary=True)
c.execute("SELECT * FROM mentoria_odysee_queue WHERE id IN (67, 68, 154)")
rows = {r['id']: r for r in c.fetchall()}
c.close()
conn.close()

# ============================================================
# PASSO 1: Deletar o vídeo do Franklin do Odysee
# ============================================================
print("\n[PASSO 1] Deletando vídeo Franklin do Odysee...")

creds = get_odysee_credentials()
auth_token = creds['odysee_auth_token'] if creds else None

if not auth_token:
    print("  ERRO: Não foi possível obter credenciais Odysee!")
else:
    # Slug do Franklin que foi publicado indevidamente
    franklin_slug = "mentorship_2025_10_13_19h"
    
    # Resolve o claim_id via API LBRY
    resolve_resp = requests.post(
        "https://api.na-backend.odysee.com/api/v1/proxy?m=resolve",
        json={
            "jsonrpc": "2.0",
            "method": "resolve",
            "params": {"urls": [f"lbry://@EncontrodeIdiomasIngles/{franklin_slug}"]}
        },
        timeout=20
    )
    
    print(f"  Resolve HTTP: {resolve_resp.status_code}")
    
    if resolve_resp.status_code == 200:
        result = resolve_resp.json().get("result", {})
        entry = result.get(f"lbry://@EncontrodeIdiomasIngles/{franklin_slug}", {})
        claim_id = entry.get("claim_id")
        
        if claim_id and "error" not in entry:
            print(f"  claim_id encontrado: {claim_id}")
            
            # Abandona o claim (deleta da blockchain LBRY)
            abandon_resp = requests.post(
                "https://api.na-backend.odysee.com/api/v1/proxy?m=stream_abandon",
                json={
                    "jsonrpc": "2.0",
                    "method": "stream_abandon",
                    "params": {"claim_id": claim_id}
                },
                headers={"Authorization": f"Bearer {auth_token}"},
                timeout=30
            )
            print(f"  Abandon HTTP: {abandon_resp.status_code} | Body: {abandon_resp.text[:300]}")
        else:
            print(f"  Vídeo não encontrado via API LBRY (pode já ter sido deletado): {entry.get('error', 'sem entry')}")
    else:
        print(f"  Erro ao resolver via LBRY API: {resolve_resp.text[:200]}")

# ============================================================
# PASSO 2: Enviar WPP manualmente para ID 67 (2026/09/28)
# ============================================================
print("\n[PASSO 2] Enviando WPP para Mentorship 2026/09/28 (ID 67)...")

row_67 = rows.get(67, {})
wpp_msg_67 = row_67.get('whatsapp_message', '')

# Remove o prefixo de erro se existir
if wpp_msg_67.startswith('[WPP FALHOU'):
    # Extrai a mensagem real após o prefixo
    idx = wpp_msg_67.find('] ')
    if idx != -1:
        wpp_msg_67 = wpp_msg_67[idx + 2:]

print(f"  Mensagem a enviar: {wpp_msg_67!r}")

# Busca o JID do grupo Our Classes EN
conf_resp = requests.get(
    f"{BAILEYS_BASE}/mentoria-config?lang=en",
    headers={"apikey": BAILEYS_KEY},
    timeout=15
)

if conf_resp.status_code == 200:
    conf = conf_resp.json()
    jid = conf.get("groups", {}).get("our_classes", {}).get("jid")
    print(f"  JID Our Classes EN: {jid}")
    
    if jid:
        send_resp = requests.post(
            f"{BAILEYS_BASE}/send",
            json={
                "to": jid,
                "message": wpp_msg_67,
                "source": "mentoria_pipeline"
            },
            headers={"apikey": BAILEYS_KEY},
            timeout=20
        )
        print(f"  Envio WPP HTTP: {send_resp.status_code} | Body: {send_resp.text[:200]}")
        
        if send_resp.status_code == 200:
            # Atualiza o banco: remove o prefixo de erro da mensagem
            conn2 = get_db_connection()
            c2 = conn2.cursor()
            c2.execute(
                "UPDATE mentoria_odysee_queue SET whatsapp_message = %s WHERE id = 67",
                (wpp_msg_67,)
            )
            conn2.commit()
            c2.close()
            conn2.close()
            print("  ✅ Mensagem enviada e banco atualizado!")
        else:
            print(f"  ❌ Falha ao enviar WPP: {send_resp.text}")
    else:
        print("  ❌ JID não encontrado no mentoria-config!")
else:
    print(f"  ❌ Erro ao buscar mentoria-config: {conf_resp.status_code}")

# ============================================================
# PASSO 3: Corrigir status no banco
# ============================================================
print("\n[PASSO 3] Corrigindo status no banco...")

conn3 = get_db_connection()
c3 = conn3.cursor()

# ID 68: aula legítima de 29/09 - tem URL e WPP foi enviado → marcar done
row_68 = rows.get(68, {})
if row_68.get('odysee_url'):
    c3.execute("""
        UPDATE mentoria_odysee_queue 
        SET status = 'done', 
            error_message = NULL,
            processed_at = '2026-09-29 19:46:34'
        WHERE id = 68
    """)
    print(f"  ID 68: marcado como 'done' (URL: {row_68.get('odysee_url')})")
else:
    print(f"  ID 68: sem URL, mantendo status atual")

# ID 154: Franklin - foi publicado indevidamente → marcar como cancelled com nota
c3.execute("""
    UPDATE mentoria_odysee_queue 
    SET status = 'cancelled',
        error_message = 'CANCELADO: Aula particular indevida publicada e deletada do Odysee. Publicada às 21:27 em 2026-09-29.'
    WHERE id = 154
""")
print(f"  ID 154 (Franklin): marcado como 'cancelled'")

conn3.commit()
c3.close()
conn3.close()

print("\n✅ Script concluído!")
print("=" * 60)
