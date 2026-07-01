import sys
from playwright.sync_api import sync_playwright

auth_token = "GGvENFkMmsBB9V7ZWpfty4X8BRWskkcR" # Token do inglês

with sync_playwright() as p:
    browser = p.chromium.launch(
        headless=True,
        args=[
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--js-flags="--max-old-space-size=256"',
            '--blink-settings=imagesEnabled=false',
            '--disable-extensions'
        ]
    )
    context = browser.new_context()
    page = context.new_page()
    
    print("Acessando odysee.com...")
    page.goto("https://odysee.com", timeout=60000)
    page.evaluate(f"window.localStorage.setItem('auth_token', '{auth_token}')")
    
    print("Acessando página de upload...")
    page.goto("https://odysee.com/$/upload", timeout=60000)
    page.wait_for_load_state("networkidle")
    
    print(f"URL final: {page.url}")
    
    # Take screenshot
    page.screenshot(path="/app/test_screenshot.png")
    print("Screenshot salva em /app/test_screenshot.png")
    
    browser.close()
