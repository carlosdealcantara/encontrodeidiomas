import os
import time
from playwright.sync_api import sync_playwright
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
    
    page.goto("https://odysee.com", timeout=60000)
    page.wait_for_timeout(5000)
    
    context.add_cookies([
        {
            "name": "auth_token",
            "value": auth_token,
            "domain": ".odysee.com",
            "path": "/"
        }
    ])
    page.evaluate(f"window.localStorage.setItem('auth_token', '{auth_token}')")
    page.reload()
    page.wait_for_timeout(5000)
    
    page.goto("https://odysee.com/$/upload")
    page.wait_for_timeout(5000)
    
    file_input = page.locator('input[type="file"]')
    file_input.wait_for(state="attached", timeout=60000)
    
    html = page.content()
    with open("/app/upload_page.html", "w", encoding="utf-8") as f:
        f.write(html)
        
    browser.close()
