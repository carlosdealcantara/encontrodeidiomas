import os
import time
import logging
from playwright.sync_api import sync_playwright
import mysql.connector
from mentoria_worker import get_db_connection, get_odysee_credentials, salvar_screenshot

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

def fix_links():
    logger.info("Iniciando correção de links da mentoria")
    
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    
    # Busca os vídeos concluídos que ainda não têm ody.sh
    cursor.execute("""
        SELECT id, odysee_slug, odysee_url, whatsapp_message 
        FROM mentoria_odysee_queue 
        WHERE status = 'done' AND odysee_url NOT LIKE '%ody.sh%'
    """)
    videos = cursor.fetchall()
    
    if not videos:
        logger.info("Nenhum vídeo precisando de correção.")
        cursor.close()
        conn.close()
        return
        
    creds = get_odysee_credentials()
    if not creds:
        logger.error("Credenciais Odysee não encontradas.")
        return
        
    auth_token = creds['odysee_auth_token']
    channel_name = creds['odysee_channel_name'].lstrip('@')
    
    with sync_playwright() as p:
        browser = p.chromium.launch(
            headless=True,
            args=[
                '--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage',
                '--disable-gpu', '--disable-blink-features=AutomationControlled'
            ]
        )
        context = browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36",
            viewport={"width": 1920, "height": 1080}
        )
        page = context.new_page()
        
        # Injeta o token na home
        page.goto("https://odysee.com", timeout=60000)
        context.add_cookies([{"name": "auth_token", "value": auth_token, "domain": ".odysee.com", "path": "/"}])
        page.evaluate(f"window.localStorage.setItem('auth_token', '{auth_token}')")
        
        for video in videos:
            try:
                logger.info(f"Processando vídeo ID {video['id']}")
                video_url = f"https://odysee.com/@{channel_name}/{video['odysee_slug']}"
                
                page.goto(video_url, timeout=60000, wait_until="domcontentloaded")
                page.wait_for_timeout(4000)
                
                share_btn = page.locator('button:has-text("Compartilhar"), button:has-text("Share")').first
                if share_btn.is_visible():
                    share_btn.click(timeout=15000)
                    page.wait_for_timeout(2000)
                    
                    share_input = page.locator('input[value*="ody.sh"]').first
                    if not share_input.is_visible():
                        share_input = page.locator('.modal input[type="text"], .dialog input[type="text"]').first
                    
                    share_link = share_input.input_value(timeout=10000)
                    if share_link and "ody.sh" in share_link:
                        logger.info(f"Link extraído: {share_link}")
                        
                        # Atualiza no banco
                        novo_wpp = video['whatsapp_message'].replace(video['odysee_url'], share_link)
                        
                        cursor.execute("UPDATE mentoria_odysee_queue SET odysee_url = %s, whatsapp_message = %s WHERE id = %s", 
                                     (share_link, novo_wpp, video['id']))
                        conn.commit()
                        logger.info(f"Vídeo {video['id']} atualizado com sucesso!")
                    else:
                        logger.warning(f"Não conseguiu achar link ody.sh para ID {video['id']}")
                else:
                    logger.warning(f"Botão share não encontrado para ID {video['id']}")
            except Exception as e:
                logger.error(f"Erro no vídeo {video['id']}: {e}")
                
        browser.close()
        
    cursor.close()
    conn.close()
    logger.info("Processo concluído.")

if __name__ == "__main__":
    fix_links()
