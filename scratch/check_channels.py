#!/usr/bin/env python3
"""
Script para descobrir o odysee_channel_name de cada idioma usando o auth_token.
Usa a API do Odysee para buscar o channel_name associado a cada token.
"""
import requests
import json

tokens = {
    'Inglês':       'GGvENFkMmsBB9V7ZWpfty4X8BRWskkcR',
    'Espanhol':     '9eHg2MgyUHZ5VRS2u6LoV2EyXnRrUXmR',
    'Francês':      '78hTspvSudJWK7hSecVohUvqxauc82to',
    'Português':    '5TBnpbPz45Ttx5xbh9ksSEoX79qXETn9',
    'Alemão':       'D1HW9Ex9pjmVpThbvQpXVP1nShFEHTNy',
    'Italiano':     'GKKX4VsaruVRKyXBFKTDPrpag1tRSdMf',
    'Coreano':      'FtWwSehmKK5FS6KYDMbDz9JJ6LWEBELN',
    'Chinês':       '3DCEfDssh4ffKZhmSLV6wXMvgdtk7ktn',
    'Russo':        '9VrMnun7v3JYFKH1EvAGBxYYHxVULfoG',
    'Polonês':      'ByguMc6T8KgWK4GSgVVZbzyYbkH93bUN',
    'Libras':       'DJyQycooLiCg9A79nuA2XQ2rgGgdVYCN',
    'Servo-Croata': '2EuEcv9s2kkgtyxREkd9G8tkZG5v2LFx',
    'Japonês':      '5pPo6WShwn5o7L9r7PDB567YigPvjm4H',
}

print(f"{'Idioma':<15} {'Status':<10} {'Channel Name'}")
print("-" * 60)

for name, token in tokens.items():
    try:
        r = requests.post(
            'https://api.odysee.com/user/me',
            headers={'Authorization': f'Bearer {token}'},
            timeout=10
        )
        data = r.json()
        if data.get('success'):
            channel = data.get('data', {}).get('primary_email', 'SEM_EMAIL')
            # Tenta pegar o channel name do campo channels
            channels = data.get('data', {}).get('channels', [])
            ch_name = channels[0].get('name', 'SEM_CANAL') if channels else 'SEM_CANAL'
            print(f"{name:<15} {'OK':<10} @{ch_name}")
        else:
            err = data.get('error', 'desconhecido')
            print(f"{name:<15} {'ERRO':<10} {err}")
    except Exception as e:
        print(f"{name:<15} {'FALHA':<10} {e}")
