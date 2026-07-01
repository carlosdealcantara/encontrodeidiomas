import requests
import json

def test_token():
    token = "78hTspvSudJWK7hSecVohUvqxauc82to"
    url = "https://api.na-backend.odysee.com/api/v1/proxy"
    
    payload = {
        "method": "channel_list",
        "params": {}
    }
    
    # Odysee requires auth_token either in params or in a query string / header
    # Let's try passing it in the JSON body first
    payload_with_token = payload.copy()
    
    headers = {
        "X-Lbry-Auth-Token": token,
        "Authorization": f"Bearer {token}"
    }
    
    response = requests.post(url, json=payload, headers=headers)
    print("Status Code:", response.status_code)
    try:
        print("Response JSON:", json.dumps(response.json(), indent=2))
    except Exception as e:
        print("Response Text:", response.text)

if __name__ == "__main__":
    test_token()
