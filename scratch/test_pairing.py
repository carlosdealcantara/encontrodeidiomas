import urllib.request
import json

url = 'http://127.0.0.1:3000/request-pairing-code'
data = json.dumps({'phone': '5521999999999'}).encode('utf-8')
req = urllib.request.Request(url, data=data, headers={'Content-Type': 'application/json'}, method='POST')
try:
    with urllib.request.urlopen(req, timeout=10) as resp:
        body = resp.read().decode('utf-8')
        print('STATUS:', resp.status)
        print('BODY:', body)
except urllib.error.HTTPError as e:
    body = e.read().decode('utf-8')
    print('HTTP ERROR:', e.code)
    print('BODY:', body)
except Exception as ex:
    print('EXCEPTION:', ex)
