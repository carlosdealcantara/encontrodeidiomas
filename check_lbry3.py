import os, sys, requests

# Busca por claim name (slug) sem especificar canal
slug = "mentorship_2026_09_23_13h"
api_url = "https://api.na-backend.odysee.com/api/v1/proxy?m=resolve"

# Tenta sem o @ do canal (busca direta pelo slug)
lbry_url = f"lbry://{slug}"
payload = {"jsonrpc": "2.0", "method": "resolve", "params": {"urls": [lbry_url]}}
try:
    res = requests.post(api_url, json=payload, timeout=15)
    data = res.json()
    result = data.get("result", {})
    entry = result.get(lbry_url, {})
    if entry and "error" not in entry:
        print(f"ENCONTRADO sem canal: {lbry_url}")
        print(f"  canonical_url: {entry.get('canonical_url', 'N/A')}")
        print(f"  short_url: {entry.get('short_url', 'N/A')}")
    else:
        print(f"Nao encontrado sem canal: {entry.get('error', {})}")
except Exception as e:
    print(f"Erro: {e}")

# Tenta busca por claim_search
print("\n--- claim_search ---")
search_api = "https://api.na-backend.odysee.com/api/v1/proxy?m=claim_search"
search_payload = {
    "jsonrpc": "2.0",
    "method": "claim_search",
    "params": {"claim_name": slug, "page_size": 5}
}
try:
    res2 = requests.post(search_api, json=search_payload, timeout=15)
    data2 = res2.json()
    items = data2.get("result", {}).get("items", [])
    print(f"Resultados: {len(items)}")
    for it in items:
        print(f"  canonical_url: {it.get('canonical_url')}")
        print(f"  short_url: {it.get('short_url')}")
        print(f"  signing_channel: {it.get('signing_channel', {}).get('canonical_url', 'N/A')}")
except Exception as e:
    print(f"Erro search: {e}")
