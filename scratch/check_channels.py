#!/usr/bin/env python3
"""
Script para buscar os channel names de cada conta Odysee via API.
Segundo request: /channel/list que traz os canais vinculados à conta.
"""
import requests

tokens = {
    'Inglês':   'GGvENFkMmsBB9V7ZWpfty4X8BRWskkcR',
    'Espanhol': '9eHg2MgyUHZ5VRS2u6LoV2EyXnRrUXmR',
    'Francês':  '78hTspvSudJWK7hSecVohUvqxauc82to',
    'Alemão':   'D1HW9Ex9pjmVpThbvQpXVP1nShFEHTNy',
    'Libras':   'DJyQycooLiCg9A79nuA2XQ2rgGgdVYCN',
}

print(f"{'Idioma':<15} {'Channel Name':<40} {'URL'}")
print("-" * 90)

for name, token in tokens.items():
    try:
        r = requests.post(
            'https://api.na-backend.odysee.com/api/v1/proxy',
            json={
                "method": "channel_list",
                "params": {}
            },
            headers={'X-Lbry-Auth-Token': token},
            timeout=10
        )
        data = r.json()
        items = data.get('result', {}).get('items', [])
        if items:
            for ch in items:
                ch_name = ch.get('name', 'N/A')
                print(f"{name:<15} @{ch_name:<39} odysee.com/@{ch_name}")
        else:
            print(f"{name:<15} {'SEM_CANAL':<40} (conta existe mas sem canal criado)")
    except Exception as e:
        print(f"{name:<15} ERRO: {str(e)[:60]}")
