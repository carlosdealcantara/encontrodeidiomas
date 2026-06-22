import os
from playwright.sync_api import sync_playwright

auth_token = "GGvENFkMmskXf1mY9T2U6zBquE9YwQz" # Assuming this is the prefix, wait I don't know the full token.
# I will fetch it from DB in the python script.

import mysql.connector
from dotenv import load_dotenv

load_dotenv('/app/.env')

def get_token():
    conn = mysql.connector.connect(
        host=os.getenv('DB_HOST'),
        user=os.getenv('DB_USER'),
        password=os.getenv('DB_PASS'),
        database=os.getenv('DB_NAME')
    )
    cursor = conn.cursor()
    cursor.execute("SELECT odysee_auth_token FROM languages WHERE name = 'Inglês'")
    row = cursor.fetchone()
    conn.close()
    return row[0]

with sync_playwright() as p:
    browser = p.chromium.launch(
        headless=True,
        args=[
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--disable-blink-features=AutomationControlled',
            '--disable-infobars',
            '--window-size=1920,1080',
        ]
    )
    context = browser.new_context(
        user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36",
        viewport={"width": 1920, "height": 1080},
        locale="pt-BR"
    )
    page = context.new_page()
    
    auth_token = get_token()
    print("Got token length:", len(auth_token))
    
    page.goto("https://odysee.com", timeout=60000)
    page.wait_for_timeout(5000)
    
    page.evaluate(f"window.localStorage.setItem('auth_token', '{auth_token}')")
    print("Token injected. Reloading...")
    
    page.reload()
    page.wait_for_timeout(5000)
    
    # Save screenshot of logged in state
    page.screenshot(path="/app/test_logged_in.png")
    
    page.goto("https://odysee.com/$/upload")
    page.wait_for_timeout(5000)
    
    page.screenshot(path="/app/test_upload_page.png")
    print("Done. URL is now:", page.url)
    
    browser.close()
