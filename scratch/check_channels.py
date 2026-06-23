#!/usr/bin/env python3
"""
Script para descobrir o odysee_channel_name de cada idioma usando o auth_token.
Tenta diferentes endpoints e formatos de autenticação do Odysee/LBRY.
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

print(f"{'Idioma':<15} {'Status':<10} {'Info'}")
print("-" * 70)

for name, token in tokens.items():
    try:
        # Os tokens do Odysee são passados como cookie/query param, não como Bearer JWT
        # Formato: auth_token como cookie ou como parâmetro POST
        r = requests.post(
            'https://api.odysee.com/user/me',
            data={'auth_token': token},
            timeout=10
        )
        data = r.json()
        if data.get('success'):
            user_data = data.get('data', {})
            email = user_data.get('primary_email', 'SEM_EMAIL')
            channels = user_data.get('channels', [])
            if channels:
                ch_names = ', '.join([f"@{c.get('name', '?')}" for c in channels])
            else:
                ch_names = 'SEM_CANAL'
            print(f"{name:<15} {'OK':<10} {email} | {ch_names}")
        else:
            err = data.get('error', str(data))[:60]
            print(f"{name:<15} {'ERRO':<10} {err}")
    except Exception as e:
        print(f"{name:<15} {'FALHA':<10} {str(e)[:60]}")
