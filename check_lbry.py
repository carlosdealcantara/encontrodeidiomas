import os, sys, requests

# Verifica as envs do container
print("=== ENVS DO CONTAINER ===")
for k, v in sorted(os.environ.items()):
    if any(x in k for x in ["MENTORIA", "ODYSEE", "CHANNEL", "LANGUAGE"]):
        safe_v = v[:60] if v else "None"
        print(f"  {k}={safe_v}")

# Testa na API LBRY com alguns channel names possíveis
slug = "mentorship_2026_09_23_13h"
candidates = [
    "@EncontrodeIdiomesMentoria",
    "@EncontrodeIdiomasMentoria", 
    "@MentorshipClassEI",
    "@encontrodeidiomasMentoria",
]

for channel in candidates:
    lbry_url = f"lbry://{channel}/{slug}"
    api_url = "https://api.na-backend.odysee.com/api/v1/proxy?m=resolve"
    payload = {"jsonrpc": "2.0", "method": "resolve", "params": {"urls": [lbry_url]}}
    try:
        res = requests.post(api_url, json=payload, timeout=15)
        data = res.json()
        result = data.get("result", {})
        entry = result.get(lbry_url, {})
        if entry and "error" not in entry:
            print(f"ENCONTRADO! Channel={channel} | slug={slug}")
            print(f"  canonical_url: {entry.get('canonical_url', 'N/A')}")
            print(f"  short_url: {entry.get('short_url', 'N/A')}")
            print(f"  permanent_url: {entry.get('permanent_url', 'N/A')}")
        else:
            print(f"Nao encontrado: {channel}")
    except Exception as e:
        print(f"Erro em {channel}: {e}")
