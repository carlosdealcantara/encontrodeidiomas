import os
import time
import urllib.parse
import logging
import requests
import mysql.connector
import datetime
import re
from dotenv import load_dotenv
from google.oauth2 import service_account
from googleapiclient.discovery import build
from googleapiclient.http import MediaIoBaseDownload
from playwright.sync_api import sync_playwright
from thumbnail_gen import gerar_thumbnail_inteligente
import unicodedata

def normalize_text(text):
    if not text:
        return ""
    return ''.join(c for c in unicodedata.normalize('NFD', text) if unicodedata.category(c) != 'Mn').lower()

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

load_dotenv()

DB_HOST = os.getenv('DB_HOST', 'localhost')
DB_USER = os.getenv('DB_USER', '')
DB_PASS = os.getenv('DB_PASS', '')
DB_NAME = os.getenv('DB_NAME', '')

GOOGLE_SA_JSON = 'google_service_account.json' 
DRIVE_MENTORIA_FOLDER_ID = os.getenv('DRIVE_MENTORIA_FOLDER_ID')
DRIVE_MENTORIA_ARCHIVE_FOLDER_ID = os.getenv('DRIVE_MENTORIA_ARCHIVE_FOLDER_ID')
MENTORIA_ODYSEE_LANGUAGE_ID = os.getenv('MENTORIA_ODYSEE_LANGUAGE_ID', '10')

def get_db_connection():
    print("Tentando conectar ao banco de dados...", flush=True)
    return mysql.connector.connect(
        host=DB_HOST,
        user=DB_USER,
        password=DB_PASS,
        database=DB_NAME,
        connection_timeout=5
    )

def init_drive_service():
    scopes = ['https://www.googleapis.com/auth/drive']
    creds = service_account.Credentials.from_service_account_file(GOOGLE_SA_JSON, scopes=scopes)
    return build('drive', 'v3', credentials=creds)

def buscar_proxima_tarefa():
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    try:
        cursor.execute('''
            SELECT *
            FROM mentoria_odysee_queue
            WHERE status = "pending"
            ORDER BY id ASC LIMIT 1
        ''')
        return cursor.fetchone()
    finally:
        cursor.close()
        conn.close()

def get_odysee_credentials():
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    try:
        cursor.execute('SELECT odysee_auth_token, odysee_channel_name FROM languages WHERE id = %s', (MENTORIA_ODYSEE_LANGUAGE_ID,))
        return cursor.fetchone()
    finally:
        cursor.close()
        conn.close()

def atualizar_status(tarefa_id, status, error_msg=None, odysee_url=None, retry_count=None, whatsapp_message=None):
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        update_cols = ["status = %s"]
        params = [status]
        
        if error_msg is not None:
            update_cols.append("error_message = %s")
            params.append(error_msg)
        if odysee_url is not None:
            update_cols.append("odysee_url = %s")
            params.append(odysee_url)
        if whatsapp_message is not None:
            update_cols.append("whatsapp_message = %s")
            params.append(whatsapp_message)
        if retry_count is not None:
            update_cols.append("retry_count = %s")
            params.append(retry_count)
            
        if status in ['done', 'error']:
            update_cols.append("processed_at = CURRENT_TIMESTAMP")
            
        params.append(tarefa_id)
        
        sql = f"UPDATE mentoria_odysee_queue SET {', '.join(update_cols)} WHERE id = %s"
        cursor.execute(sql, params)
        conn.commit()
    finally:
        cursor.close()
        conn.close()

def baixar_video_drive(drive_service, file_id, file_name):
    logger.info(f"Baixando vídeo: {file_id}")
    request = drive_service.files().get_media(fileId=file_id)
    safe_name = file_name.replace("/", "-").replace("\\", "-")
    temp_path = f"/tmp/{safe_name}"
    with open(temp_path, "wb") as f:
        downloader = MediaIoBaseDownload(f, request)
        done = False
        while not done:
            status, done = downloader.next_chunk()
    return temp_path

def mover_arquivos_mentoria(drive_service, file_id, file_name):
    try:
        if not DRIVE_MENTORIA_ARCHIVE_FOLDER_ID:
            logger.warning("[DRIVE] DRIVE_MENTORIA_ARCHIVE_FOLDER_ID não configurado.")
            return

        # 1. Move o vídeo
        file = drive_service.files().get(fileId=file_id, fields='parents').execute()
        drive_service.files().update(
            fileId=file_id,
            addParents=DRIVE_MENTORIA_ARCHIVE_FOLDER_ID,
            removeParents=",".join(file.get('parents', []))
        ).execute()
        logger.info(f"[DRIVE] Vídeo movido para a pasta de arquivos ({DRIVE_MENTORIA_ARCHIVE_FOLDER_ID})")
        
        # 2. Move o chat (mesmo nome base)
        base_name = file_name.replace(' - Recording.mp4', '').replace(' - Recording', '')
        chat_results = drive_service.files().list(
            q=f"'{DRIVE_MENTORIA_FOLDER_ID}' in parents and mimeType='text/plain' and name contains '{base_name}'",
            fields="files(id, name, parents)"
        ).execute()
        
        chats = chat_results.get('files', [])
        for chat in chats:
            drive_service.files().update(
                fileId=chat['id'],
                addParents=DRIVE_MENTORIA_ARCHIVE_FOLDER_ID,
                removeParents=",".join(chat.get('parents', []))
            ).execute()
            logger.info(f"[DRIVE] Chat movido para a pasta de arquivos: {chat['name']}")
                
    except Exception as e:
        logger.error(f"[DRIVE] Erro ao mover arquivos: {e}")

SCREENSHOT_DIR = "/app/screenshots_mentoria"

def salvar_screenshot(page, nome, tarefa_id):
    try:
        import os
        import base64
        os.makedirs(SCREENSHOT_DIR, exist_ok=True)
        path = f"{SCREENSHOT_DIR}/{nome}.png"
        page.screenshot(path=path)
        
        with open(path, "rb") as image_file:
            encoded_string = base64.b64encode(image_file.read()).decode('utf-8')
            
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("UPDATE mentoria_odysee_queue SET last_screenshot = %s, last_screenshot_time = NOW() WHERE id = %s", (encoded_string, tarefa_id))
        conn.commit()
        cursor.close()
        conn.close()
        
        logger.info(f"[SCREENSHOT] {nome} | URL: {page.url} | Título: {page.title()}")
    except Exception as e:
        logger.warning(f"[SCREENSHOT] Falhou ao salvar {nome}: {e}")

def publicar_odysee_playwright(tarefa_id, auth_token, title, file_path, slug=None):
    logger.info("Iniciando publicação de MENTORIA no Odysee via Playwright")
    
    thumbnail_path = None
    try:
        thumbnail_path = gerar_thumbnail_inteligente(file_path)
    except Exception as e:
        logger.warning(f"[THUMBNAIL] Erro: {e}")
        
    with sync_playwright() as p:
        browser = p.chromium.launch(
            headless=True,
            args=[
                '--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage',
                '--disable-gpu', '--disable-blink-features=AutomationControlled',
                '--disable-infobars', '--window-size=1920,1080',
            ]
        )
        context = browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36",
            viewport={"width": 1920, "height": 1080}, 
            locale="en-US"
        )
        page = context.new_page()
        page.set_default_timeout(14400000)
        page.set_default_navigation_timeout(14400000)
        
        logger.info("[PASSO 1] Acessando odysee.com...")
        page.goto("https://odysee.com", timeout=60000, wait_until="domcontentloaded")
        page.wait_for_timeout(3000)
        
        context.add_cookies([
            {"name": "auth_token", "value": auth_token, "domain": ".odysee.com", "path": "/"}
        ])
        page.evaluate(f"window.localStorage.setItem('auth_token', '{auth_token}')")
        page.reload(timeout=60000, wait_until="domcontentloaded")
        page.wait_for_timeout(3000)
        
        logger.info("[PASSO 2] Navegando para /$/upload...")
        page.goto("https://odysee.com/$/upload", timeout=60000, wait_until="domcontentloaded")
        page.wait_for_timeout(3000)
        if "upload" not in page.url:
            raise Exception(f"Redirecionado inesperadamente para: {page.url}")
        
        logger.info("[PASSO 3] Input de arquivo...")
        salvar_screenshot(page, "00_before_file_input", tarefa_id)
        
        # Odysee pode ter um botão de "Browse" ou a class '.file-selector' ou semelhante
        file_input = page.locator('input[type="file"]')
        try:
            file_input.wait_for(state="attached", timeout=60000)
        except Exception as e:
            salvar_screenshot(page, "00_error_file_input", tarefa_id)
            raise e
            
        file_input.set_input_files(file_path)
        salvar_screenshot(page, "01_after_file_input", tarefa_id)
        
        logger.info("[PASSO 4] Wizard...")
        
        for step in range(1, 6):
            page.wait_for_timeout(2000)
            
            # Aba Detalhes
            try:
                if page.locator('input[name="content_title"], input[placeholder*="ítulo"], input[placeholder*="Title"]').first.is_visible():
                    try:
                        page.locator('input[name="content_title"], input[placeholder*="ítulo"], input[placeholder*="Title"]').first.fill(title, timeout=5000, force=True)
                    except:
                        safe_title = title.replace('"', '\\"').replace("'", "\\'")
                        page.evaluate(f"var el = document.querySelector('input[name=\"content_title\"]') || document.querySelector('input[placeholder*=\"ítulo\"]'); if(el) {{ el.value = '{safe_title}'; el.dispatchEvent(new Event('input', {{bubbles: true}})); }}")
                    
                    if slug:
                        try:
                            url_input = page.locator('input[name="content_name"], input[placeholder*="url" i], input[placeholder*="URL"]').first
                            if url_input.is_visible():
                                url_input.click(click_count=3)
                                page.keyboard.press('Backspace')
                                url_input.fill(slug, timeout=5000, force=True)
                        except: pass
                    
                    try: page.locator('input[name="content_bid"]').first.fill("0.001", timeout=5000, force=True)
                    except: pass
            except: pass
            
            # Tentar fazer upload de thumbnail
            if page.locator('input[name="content_title"]').first.is_visible() and thumbnail_path:
                try:
                    thumb_input = page.locator('.publish__thumbnail input[type="file"], .card--thumbnail input[type="file"], input[name="thumbnail"]').first
                    if thumb_input.count() > 0:
                        thumb_input.set_input_files(thumbnail_path)
                        page.wait_for_timeout(2000)
                        confirm_btn = page.locator('.modal button, .dialog button, button').filter(has_text=re.compile(r"Upload|Enviar", re.I)).last
                        if confirm_btn.is_visible():
                            confirm_btn.click()
                            page.wait_for_timeout(3000)
                except: pass

            # Aba de Visibilidade (NÃO LISTADO)
            try:
                unlisted_option = page.locator('text="Não-listado"').first
                if not unlisted_option.is_visible():
                    unlisted_option = page.locator('text="Unlisted"').first
                if unlisted_option.is_visible():
                    unlisted_option.click()
                    logger.info("[VISIBILIDADE] Opção 'Não-listado' selecionada.")
                    salvar_screenshot(page, "03_visibility_unlisted", tarefa_id)
            except Exception as e:
                logger.warning(f"[VISIBILIDADE] Erro ao selecionar não-listado: {e}")

            # Botão Publish
            publish_btn = page.locator('.button--primary >> text="Publish", .button--primary >> text="Publicação", form button.button--primary:has-text("Publish"), .publish__actions button.button--primary').first
            next_btn = page.locator('button:has-text("Próximo"), button:has-text("Next")').first

            if publish_btn.is_visible():
                try: publish_btn.click(timeout=30000)
                except: publish_btn.evaluate("el => el.click()")
                break
            elif next_btn.is_visible():
                try:
                    page.wait_for_function("() => { const btn = document.querySelector('button[aria-label=\"Next\"], button[aria-label=\"Próximo\"]'); return btn && !btn.disabled; }", timeout=300000)
                    next_btn.click(timeout=30000)
                except: 
                    try: next_btn.evaluate("el => el.click()")
                    except: pass
            else:
                break
            
        logger.info("[PASSO 6] Aguardando upload...")
        page.wait_for_timeout(8000)
        salvar_screenshot(page, "04_after_upload_click", tarefa_id)
        
        url_inicial = page.url
        upload_ok = False
        
        for ciclo in range(480):
            page.wait_for_timeout(30000)
            try: is_uploading = page.locator(':text("Enviando"), :text("Sending"), :text("Uploading")').count() > 0
            except: is_uploading = False

            if not is_uploading:
                try: page.reload(wait_until='domcontentloaded', timeout=30000); page.wait_for_timeout(3000)
                except: pass
            
            url_atual = page.url
            if ciclo % 2 == 0: salvar_screenshot(page, "05_upload_progress", tarefa_id)
            
            if url_atual != url_inicial and "/upload" not in url_atual:
                upload_ok = True; break
                
            if "/$/uploads" in url_atual:
                try:
                    if page.locator('text=/^Published$/').count() > 0 or page.locator('text=/^Publicado$/').count() > 0:
                        upload_ok = True; break
                except: pass
            
            if ciclo > 2 and ciclo % 5 == 0:
                pass
                    
        salvar_screenshot(page, "06_upload_complete", tarefa_id)
        browser.close()
        return upload_ok

def escanear_drive():
    print("Escaneando Drive MENTORIA por novos vídeos...", flush=True)
    if not DRIVE_MENTORIA_FOLDER_ID:
        logger.error("DRIVE_MENTORIA_FOLDER_ID não configurado.")
        return
        
    try:
        drive_service = init_drive_service()
        results = drive_service.files().list(
            q=f"'{DRIVE_MENTORIA_FOLDER_ID}' in parents and mimeType contains 'video/' and (name contains 'Mentorship Class' or name contains 'Mentoria')",
            fields="files(id, name)"
        ).execute()
        arquivos = results.get('files', [])
        
        if not arquivos: return
            
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        for arquivo in arquivos:
            file_id = arquivo['id']
            file_name = arquivo['name']
            
            cursor.execute("SELECT id FROM mentoria_odysee_queue WHERE drive_file_id = %s", (file_id,))
            if cursor.fetchone(): continue
                
            # Ex: Mentorship Class - 2026/07/01 13:06 GMT-03:00 - Recording.mp4
            titulo_limpo = re.sub(r'\s+\d{2}:\d{2}\s+GMT.*', '', file_name)
            titulo_limpo = titulo_limpo.replace(' - Recording', '').replace('.mp4', '').strip()
            
            date_match = re.search(r'(\d{4})[/\-](\d{2})[/\-](\d{2})\s+(\d{2})', file_name)
            if date_match:
                slug = f"mentorship_{date_match.group(1)}_{date_match.group(2)}_{date_match.group(3)}_{date_match.group(4)}h"
            else:
                slug = normalize_text(titulo_limpo).replace(" ", "_")
                
            cursor.execute("""
                INSERT INTO mentoria_odysee_queue 
                (drive_file_id, drive_file_name, titulo_final, odysee_slug, status) 
                VALUES (%s, %s, %s, %s, 'pending')
            """, (file_id, file_name, titulo_limpo, slug))
            conn.commit()
            logger.info(f"Novo vídeo da Mentoria na fila: {file_name} | Slug: {slug}")
            
        cursor.close()
        conn.close()
    except Exception as e:
        logger.error(f"Erro ao escanear Drive MENTORIA: {e}")

def encurtar_url(url_longa):
    try:
        res = requests.post("https://clck.ru/--", data={'url': url_longa}, timeout=10)
        if res.status_code == 200 and res.text.startswith('http'):
            return res.text.strip()
    except: pass
    return url_longa

def notificar_whatsapp(titulo, url_curta, thumbnail_b64=None):
    mensagem = f"🎓 *Mentorship Class:* {titulo}\n\n🔗 {url_curta}"
    
    grupos_alvo = []
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT group_id FROM meetup_whatsapp_groups WHERE ativo = 1 AND categoria = 'mentoria'")
        grupos_alvo = [r['group_id'] for r in cursor.fetchall()]
        cursor.close()
        conn.close()
    except Exception as e:
        logger.error(f"Erro ao buscar grupos de mentoria: {e}")
        
    link_preview_data = {
        "title": titulo,
        "body": "Disponível agora no Odysee (Não-listado)",
        "url": url_curta
    }
    if thumbnail_b64:
        link_preview_data["thumbnailBase64"] = thumbnail_b64
        
    for grupo_id in grupos_alvo:
        try:
            requests.post("http://host.docker.internal:3000/send", json={
                "to": grupo_id,
                "message": mensagem,
                "source": "mentoria_pipeline",
                "linkPreview": link_preview_data
            }, headers={"apikey": "SenhaMeetups2026"}, timeout=15)
            logger.info(f"[WHATSAPP] Notificação enviada para {grupo_id}")
        except Exception as e:
            logger.warning(f"[WHATSAPP] Erro ao notificar {grupo_id}: {e}")
            
    return mensagem

def processar_fila():
    escanear_drive()
    
    tarefa = buscar_proxima_tarefa()
    if not tarefa: return
        
    logger.info(f"Processando mentoria: {tarefa['titulo_final']} (Status: {tarefa['status']})")
    atualizar_status(tarefa['id'], 'processing')
    
    temp_path = None
    try:
        drive_service = init_drive_service()
        temp_path = baixar_video_drive(drive_service, tarefa['drive_file_id'], tarefa['drive_file_name'])
        
        creds = get_odysee_credentials()
        if not creds or not creds.get('odysee_auth_token'):
            raise Exception("Credenciais Odysee (Token) não encontradas para o idioma de Mentoria.")
            
        auth_token = creds['odysee_auth_token']
        channel_name = creds['odysee_channel_name']
        
        title = tarefa.get('titulo_final')
        upload_ok = publicar_odysee_playwright(tarefa['id'], auth_token, title, temp_path, slug=tarefa.get('odysee_slug'))
        
        if not upload_ok:
            raise Exception("Falha no processo de publicação (Timeout)")
            
        odysee_url = f"https://odysee.com/{channel_name}/{tarefa['odysee_slug']}"
        url_curta = encurtar_url(odysee_url)
        
        mover_arquivos_mentoria(drive_service, tarefa['drive_file_id'], tarefa['drive_file_name'])
        
        thumbnail_b64 = None
        thumb_path = "/app/screenshots_mentoria/thumbnail_selected.jpg"
        if os.path.exists(thumb_path):
            try:
                import cv2
                import base64
                img = cv2.imread(thumb_path)
                if img is not None:
                    img = cv2.resize(img, (854, 480), interpolation=cv2.INTER_AREA)
                    result, encimg = cv2.imencode('.jpg', img, [int(cv2.IMWRITE_JPEG_QUALITY), 75])
                    if result: thumbnail_b64 = base64.b64encode(encimg).decode('utf-8')
            except: pass
            
        msg_wpp = notificar_whatsapp(title, url_curta, thumbnail_b64)
        atualizar_status(tarefa['id'], 'done', odysee_url=url_curta, whatsapp_message=msg_wpp)
        
    except Exception as e:
        logger.exception("Erro processando fila mentoria")
        retry = tarefa['retry_count'] + 1
        novo_status = 'error' if retry >= 3 else 'pending'
        atualizar_status(tarefa['id'], novo_status, error_msg=str(e), retry_count=retry)
    finally:
        if temp_path and os.path.exists(temp_path):
            os.remove(temp_path)

def cleanup_zombies():
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("UPDATE mentoria_odysee_queue SET status='pending' WHERE status='processing'")
        conn.commit()
        cursor.close()
        conn.close()
    except: pass

if __name__ == "__main__":
    print("Iniciando Worker de Mentoria...", flush=True)
    cleanup_zombies()
    while True:
        try:
            processar_fila()
        except Exception as e:
            logger.error(f"Erro no loop: {e}")
        time.sleep(60)
