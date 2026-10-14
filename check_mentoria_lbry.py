import requests

api_url = "https://api.na-backend.odysee.com/api/v1/proxy?m=resolve"
payload = {"jsonrpc": "2.0", "method": "resolve", "params": {"urls": [
    "lbry://@EncontrodeIdiomasIngles/mentorship_2026_09_23_13h",
    "lbry://@EncontrodeIdiomasIngles/mentorship_2026_09_22_13h"
]}}
res = requests.post(api_url, json=payload, timeout=15)
print("HTTP:", res.status_code)
for url, val in res.json().get("result", {}).items():
    print(url, "->", "OK (claim_id: " + val.get('claim_id', '') + ")" if "claim_id" in val else val.get('error'))
