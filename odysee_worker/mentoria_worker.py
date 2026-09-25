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
from google.oauth2.credentials import Credentials
from google.auth.transport.requests import Request
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
GOOGLE_OAUTH_TOKEN = 'google_oauth_token_mentoria.json'  # OAuth da conta carlosdealcantarajr@gmail.com
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
    """Inicializa o serviço do Drive da Mentoria.
    Prioridade: OAuth token da conta carlosdealcantarajr@gmail.com (google_oauth_token_mentoria.json).
    Fallback: Service Account (google_service_account.json).
    """
    scopes = ['https://www.googleapis.com/auth/drive']

    if os.path.exists(GOOGLE_OAUTH_TOKEN):
        import json
        with open(GOOGLE_OAUTH_TOKEN) as f:
            token_data = json.load(f)
        creds = Credentials(
            token=token_data.get('token'),
            refresh_token=token_data.get('refresh_token'),
            token_uri=token_data.get('token_uri', 'https://oauth2.googleapis.com/token'),
            client_id=token_data.get('client_id'),
            client_secret=token_data.get('client_secret'),
            scopes=token_data.get('scopes', scopes),
        )
        if not creds.valid:
            creds.refresh(Request())
            token_data['token'] = creds.token
            with open(GOOGLE_OAUTH_TOKEN, 'w') as f:
                json.dump(token_data, f, indent=2)
        logger.info("[DRIVE] Autenticado via OAuth (conta mentoria).")
        return build('drive', 'v3', credentials=creds)
    else:
        logger.warning("[DRIVE] google_oauth_token_mentoria.json nao encontrado. Usando Service Account como fallback.")
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
            ORDER BY titulo_final ASC LIMIT 1
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



def verificar_video_publicado(channel_name, slug):
    """Verifica se o vídeo já foi publicado na LBRY.
    Tenta primeiro com o canal, depois sem canal (para uploads Anonymous).
    Retorna True se encontrado, False caso contrário.
    """
    api_url = "https://api.na-backend.odysee.com/api/v1/proxy?m=resolve"
    # Testa com canal
    urls_para_testar = [f"lbry://@{channel_name}/{slug}"]
    # Fallback: sem canal (caso o upload tenha ficado como Anonymous)
    urls_para_testar.append(f"lbry://{slug}")
    try:
        payload = {"jsonrpc": "2.0", "method": "resolve", "params": {"urls": urls_para_testar}}
        res = requests.post(api_url, json=payload, timeout=15)
        if res.status_code == 200:
            data = res.json()
            result = data.get("result", {})
            for lbry_url in urls_para_testar:
                entry = result.get(lbry_url, {})
                if entry and "error" not in entry:
                    logger.info(f"[LBRY] Vídeo encontrado: {lbry_url}")
                    return True
    except Exception as e:
        logger.warning(f"Erro ao checar API LBRY para {slug}: {e}")
    return False

def capturar_share_link_playwright(tarefa_id, auth_token, channel_name, slug):
    """
    Captura o link ody.sh navegando pela página do próprio vídeo (URL canônica).
    O owner autenticado consegue acessar a página normalmente mesmo para vídeos Unlisted.
    Em caso de lentidão pontual do Odysee, tenta até 2 vezes com 15s de espera.
    """
    share_link = None
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
        page.set_default_timeout(60000)
        page.set_default_navigation_timeout(60000)
        try:
            page.goto("https://odysee.com", timeout=60000, wait_until="domcontentloaded")
            context.add_cookies([{"name": "auth_token", "value": auth_token, "domain": ".odysee.com", "path": "/"}])
            page.evaluate(f"window.localStorage.setItem('auth_token', '{auth_token}')")

            urls_to_try = []
            if channel_name:
                urls_to_try.append(f"https://odysee.com/@{channel_name}/{slug}")
            urls_to_try.append(f"https://odysee.com/{slug}")

            logger.info(f"[PASSO 7] URLs candidatas para navegação: {urls_to_try}")

            for video_url in urls_to_try:
                if share_link:
                    break
                logger.info(f"[PASSO 7] Navegando para a página do vídeo: {video_url}")
                # Tenta até 2 vezes por URL
                for tentativa in range(2):
                    try:
                        try:
                            page.goto(video_url, timeout=60000, wait_until="domcontentloaded")
                        except Exception as nav_e:
                            logger.warning(f"[PASSO 7] Erro ao navegar para {video_url}: {nav_e}")
                            raise  # propaga para o except externo da tentativa
                        try:
                            page.wait_for_selector('h1, .video-js, video', timeout=30000)
                        except:
                            pass
                        page.wait_for_timeout(8000)

                        try:
                            page.screenshot(path="/app/screenshots_mentoria/07_video_page.png", timeout=15000)
                        except Exception as e:
                            logger.warning(f"[PASSO 7] Screenshot opcional falhou (não crítico): {e}")

                        clicked = page.evaluate("""
                            () => {
                                const btn = document.querySelector('button[aria-label="Share"], button[aria-label="Compartilhar"]');
                                if (btn) { btn.click(); return true; }
                                return false;
                            }
                        """)
                        if not clicked:
                            share_btn = page.locator('button[aria-label="Share"], button[aria-label="Compartilhar"]').first
                            share_btn.click(force=True, no_wait_after=True)
                        page.wait_for_timeout(2000)

                        share_input = page.locator('input[value*="ody.sh"]').first
                        if not share_input.is_visible():
                            share_input = page.locator('.modal input[type="text"], .dialog input[type="text"]').first

                        val = share_input.input_value(timeout=15000)
                        if val and "ody.sh" in val:
                            share_link = val
                            logger.info(f"[PASSO 7] Link ody.sh capturado (tentativa {tentativa+1}): {share_link}")
                            break  # sucesso
                        else:
                            logger.warning(f"[PASSO 7] Valor extraído não parece ody.sh: {val}")
                            share_link = None

                    except Exception as e:
                        logger.warning(f"[PASSO 7] Tentativa {tentativa+1} falhou: {e}")
                        if tentativa == 0:
                            logger.info("[PASSO 7] Aguardando 15s antes de tentar novamente...")
                            page.wait_for_timeout(15000)


        except Exception as e:
            logger.warning(f"[PASSO 7] Erro ao capturar link de compartilhamento: {e}")
        finally:
            try:
                browser.close()
            except:
                pass
    return share_link

def publicar_odysee_playwright(tarefa_id, auth_token, title, file_path, slug=None, channel_name=None):
    logger.info("Iniciando publicação no Odysee via Playwright")
    
    # Gera thumbnail inteligente antes de abrir o browser (independe de GPU)
    thumbnail_path = None
    try:
        thumbnail_path = gerar_thumbnail_inteligente(file_path)
        if thumbnail_path:
            logger.info(f"[THUMBNAIL] Thumbnail gerada com sucesso: {thumbnail_path}")
        else:
            logger.warning("[THUMBNAIL] Não foi possível gerar thumbnail. Prosseguindo sem ela.")
    except Exception as e:
        logger.warning(f"[THUMBNAIL] Erro inesperado na geração: {e}. Prosseguindo sem thumbnail.")
        
    with sync_playwright() as p:
        # Modo otimizado para VPS de 1GB
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
            locale="en-US"
        )
        page = context.new_page()
        
        # Aumenta timeout para conexões lentas ou uploads grandes (4 horas = 14400000ms)
        page.set_default_timeout(14400000)
        page.set_default_navigation_timeout(14400000)
        
        # PASSO 1: Acessar home e injetar token
        logger.info("[PASSO 1] Acessando odysee.com para injetar token...")
        page.goto("https://odysee.com", timeout=60000, wait_until="domcontentloaded")
        page.wait_for_timeout(3000)
        salvar_screenshot(page, "01_home", tarefa_id)
        
        # Injeta o token como Cookie e no localStorage
        context.add_cookies([
            {
                "name": "auth_token",
                "value": auth_token,
                "domain": ".odysee.com",
                "path": "/"
            }
        ])
        page.evaluate(f"window.localStorage.setItem('auth_token', '{auth_token}')")
        logger.info("[PASSO 1] Token injetado no Cookie e localStorage.")
        
        # Fazemos um reload para garantir que o cookie e o localStorage entrem em vigor na Home
        page.reload(timeout=60000, wait_until="domcontentloaded")
        page.wait_for_timeout(3000)
        
        # PASSO 2: Ir para página de upload
        logger.info("[PASSO 2] Navegando para /$/upload...")
        page.goto("https://odysee.com/$/upload", timeout=60000, wait_until="domcontentloaded")
        page.wait_for_timeout(3000)
        salvar_screenshot(page, "02_upload_page", tarefa_id)
        logger.info(f"[PASSO 2] Página carregada. URL: {page.url}")
        
        # Verificar se estamos na página certa (pode ter redirecionado para login)
        if "upload" not in page.url:
            salvar_screenshot(page, "02_redirect_detected", tarefa_id)
            raise Exception(f"Redirecionado inesperadamente para: {page.url}. Possível bloqueio ou sessão inválida.")
        
        # PASSO 3: Localizar e preencher o input de arquivo
        logger.info("[PASSO 3] Localizando input de arquivo...")
        file_input = page.locator('input[type="file"]')
        file_input.wait_for(state="attached", timeout=60000)
        salvar_screenshot(page, "03_before_file_input", tarefa_id)
        file_input.set_input_files(file_path)
        logger.info(f"[PASSO 3] Arquivo '{file_path}' selecionado.")
        salvar_screenshot(page, "04_after_file_input", tarefa_id)
        
        # PASSO 4: Navegar pelo Wizard do Odysee
        logger.info("[PASSO 4] Navegando pelo Wizard de publicação...")
        
        # Odysee usa um Wizard de 4 etapas: 1. Arquivo, 2. Detalhes, 3. Tags, 4. Publicação
        # O botão final "Publicação" está na parte inferior da página (não na barra de navegação do topo)
        for step in range(1, 6):
            logger.info(f"Tentando preencher a etapa {step} do Wizard...")
            page.wait_for_timeout(2000)
            
            # Tenta preencher o título se ele estiver visível (somente na etapa de Detalhes)
            try:
                if page.locator('input[name="content_title"], input[placeholder*="ítulo"], input[placeholder*="Title"]').first.is_visible():
                    try:
                        page.locator('input[name="content_title"], input[placeholder*="ítulo"], input[placeholder*="Title"]').first.fill(title, timeout=5000, force=True)
                    except Exception as e:
                        logger.warning(f"Aviso: Não conseguiu preencher título com fill: {e}")
                        safe_title = title.replace('"', '\\"').replace("'", "\\'")
                        page.evaluate(f"""
                            var el = document.querySelector('input[name="content_title"]') || document.querySelector('input[placeholder*="ítulo"]');
                            if(el) {{ el.value = "{safe_title}"; el.dispatchEvent(new Event('input', {{bubbles: true}})); }}
                        """)
                    
                    if slug:
                        try:
                            url_input = page.locator('input[name="content_name"], input[placeholder*="url" i], input[placeholder*="URL"]').first
                            if url_input.is_visible():
                                url_input.click(click_count=3)
                                page.keyboard.press('Backspace')
                                url_input.fill(slug, timeout=5000, force=True)
                                logger.info(f"URL preenchida com o slug: {slug}")
                        except Exception as e:
                            logger.warning(f"Erro ao preencher a URL: {e}")
                    
                    try:
                        page.locator('input[name="content_bid"]').first.fill("0.001", timeout=5000, force=True)
                    except:
                        pass
                    logger.info("Título preenchido.")
                    salvar_screenshot(page, f"05_wizard_step_{step}_title_filled", tarefa_id)
            except Exception as outer_e:
                logger.warning(f"Erro ao tentar preencher campos no passo {step}: {outer_e}")
            
            # Verifica o botão de Publicação FINAL (botão no rodapé, não na barra de navegação do topo)
            publish_btn = page.locator('.button--primary >> text="Publish"').first
            if not publish_btn.is_visible():
                publish_btn = page.locator('.button--primary >> text="Publicação"').first
            if not publish_btn.is_visible():
                publish_btn = page.locator('form button.button--primary:has-text("Publish"), form button.button--primary:has-text("Publicação"), .publish__actions button.button--primary').first
            
            if publish_btn.is_visible():
                logger.info("Botão FINAL de Publicação encontrado no rodapé! Clicando...")
                try:
                    publish_btn.click(timeout=30000)
                except Exception as e:
                    logger.warning(f"Erro ao clicar Publicação: {e}")
                    publish_btn.evaluate("el => el.click()")
                break
            
            # UPLOAD DE THUMBNAIL PRÉ-GERADA
            # Em vez de esperar o browser gerar thumbnails (requer GPU), fazemos
            # o upload do arquivo que já geramos com OpenCV antes de abrir o browser.
            if page.locator('input[name="content_title"]').first.is_visible() and thumbnail_path:
                logger.info("[THUMBNAIL] Aba de Detalhes detectada. Fazendo upload da thumbnail pré-gerada...")
                try:
                    # O wizard tem um input[type="file"] específico para thumbnail
                    thumb_input = page.locator(
                        '.publish__thumbnail input[type="file"], '
                        '.card--thumbnail input[type="file"], '
                        'input[name="thumbnail"], '
                        '.thumbnail-picker input[type="file"]'
                    ).first

                    if thumb_input.count() > 0:
                        thumb_input.set_input_files(thumbnail_path)
                        logger.info("[THUMBNAIL] Arquivo enviado via input[type=file].")
                        page.wait_for_timeout(2000)  # aguarda modal aparecer
                        
                        # NOVO: Odysee agora pede confirmação no modal "Enviar Thumbnail"
                        confirm_btn = page.locator('button:has-text("Upload")').last
                        if not confirm_btn.is_visible():
                            confirm_btn = page.locator('button:has-text("Enviar")').last
                        if not confirm_btn.is_visible():
                            confirm_btn = page.locator('.modal button, .dialog button').filter(has_text="Upload").first
                        if not confirm_btn.is_visible():
                            confirm_btn = page.locator('.modal button, .dialog button').filter(has_text="Enviar").first
                        
                        if confirm_btn.is_visible():
                            logger.info("[THUMBNAIL] Modal de confirmação detectado. Clicando em Enviar...")
                            confirm_btn.click()
                            page.wait_for_timeout(3000) # aguarda upload da thumbnail finalizar
                        else:
                            page.wait_for_timeout(2000)
                            
                        salvar_screenshot(page, "05c_thumbnail_uploaded", tarefa_id)
                    else:
                        # Fallback: tentar via botão "Enviar" visível na tela
                        enviar_btn = page.locator('button:has-text("Upload")').first
                        if not enviar_btn.is_visible():
                            enviar_btn = page.locator('button:has-text("Enviar")').first
                        if enviar_btn.is_visible():
                            enviar_btn.click()
                            page.wait_for_timeout(1000)
                            thumb_input2 = page.locator('input[type="file"]').last
                            thumb_input2.set_input_files(thumbnail_path)
                            page.wait_for_timeout(3000)
                            salvar_screenshot(page, "05c_thumbnail_uploaded_fallback", tarefa_id)
                        else:
                            logger.warning("[THUMBNAIL] Input de thumbnail não encontrado na página.")
                except Exception as e:
                    logger.warning(f"[THUMBNAIL] Erro ao fazer upload da thumbnail: {e}. Continuando sem ela.")

            try:
                # Aguarda até 5 minutos o botão "Próximo" sair do estado disabled
                # (Odysee analisa o arquivo antes de liberar o botão — para vídeos grandes isso leva minutos)
                page.wait_for_function(
                    """
                    () => {
                        const pub = document.querySelector('button[aria-label="Publish"], button[aria-label="Publicação"]');
                        if (pub && !pub.disabled) return true;
                        const nxt = document.querySelector('button[aria-label="Next"], button[aria-label="Próximo"]');
                        return nxt && !nxt.disabled;
                    }
                    """,
                    timeout=300000  # 5 minutos para vídeos grandes
                )
                logger.info("[PASSO 4] Botão habilitado após análise do arquivo.")
            except Exception as e:
                logger.warning(f"[PASSO 4] Timeout aguardando botão ser habilitado: {e}")

            # Atualiza referências após a espera
            next_btn = page.locator('button:has-text("Próximo"), button:has-text("Next")').first
                        # Aba de Visibilidade (NÃO LISTADO) - ESPECÍFICO DA MENTORIA
            try:
                unlisted_option = page.locator('text="Não-listado"').first
                if not unlisted_option.is_visible():
                    unlisted_option = page.locator('text="Unlisted"').first
                if unlisted_option.is_visible():
                    unlisted_option.click()
                    logger.info("[VISIBILIDADE] Opção 'Não-listado' selecionada.")
                    salvar_screenshot(page, "05e_visibility_unlisted", tarefa_id)
            except Exception as e:
                logger.warning(f"[VISIBILIDADE] Erro ao selecionar não-listado: {e}")
            publish_btn = page.locator('.button--primary >> text="Publicação", .button--primary >> text="Publish"').first
            if not publish_btn.is_visible():
                publish_btn = page.locator('form button.button--primary:has-text("Publicação"), form button.button--primary:has-text("Publish"), .publish__actions button.button--primary').first

            if publish_btn.is_visible():
                logger.info("Botão FINAL de Publicação apareceu após espera! Clicando...")
                try:
                    publish_btn.click(timeout=30000)
                except Exception as e:
                    logger.warning(f"Erro ao clicar Publicação: {e}")
                    publish_btn.evaluate("el => el.click()")
                break
            elif next_btn.is_visible():
                logger.info("Clicando em Próximo...")
                try:
                    # Espera o botão sair do disabled via evaluate, já que o wait_for(state="enabled") não existe
                    page.wait_for_function(
                        """
                        () => {
                            const btn = document.querySelector('button[aria-label="Next"], button[aria-label="Próximo"]');
                            return btn && !btn.disabled;
                        }
                        """,
                        timeout=300000
                    )
                    next_btn.click(timeout=30000)
                except Exception as e:
                    logger.warning(f"Erro ao clicar Próximo (ainda disabled?): {e}")
                    try:
                        next_btn.evaluate("el => el.click()")
                    except:
                        pass
            else:
                logger.info("Nem botão de Publicação nem botão de Próximo visíveis. Wizard pode ter terminado ou travou.")
                salvar_screenshot(page, f"05d_wizard_step_{step}_stuck", tarefa_id)
                break
            
        # PASSO 6: Aguardar conclusão
        logger.info("[PASSO 6] Aguardando upload terminar (máx 4h)...")
        # Aguarda 8 segundos para a interface React do Odysee renderizar a barra de progresso
        page.wait_for_timeout(8000)
        salvar_screenshot(page, "06_after_upload_click", tarefa_id)
        
        url_inicial = page.url
        upload_ok = False
        
        for ciclo in range(480):  # 480 x 30s = 4h
            page.wait_for_timeout(30000)
            
            # IMPORTANTE: Recarrega a página se o upload NÃO estiver em andamento.
            # O React do Odysee não atualiza o badge "Published" no DOM sem reload,
            # mas recarregar durante o upload cancela a transferência.
            try:
                is_uploading = page.locator(':text("Enviando"), :text("Sending"), :text("Uploading")').count() > 0
            except:
                is_uploading = False

            if not is_uploading:
                try:
                    page.reload(wait_until='domcontentloaded', timeout=30000)
                    page.wait_for_timeout(3000)  # Aguarda o React renderizar após o reload
                except Exception as e:
                    logger.warning(f"[PASSO 6] Erro ao recarregar página no ciclo {ciclo}: {e}")
            else:
                logger.info(f"[PASSO 6] Upload em andamento, skip reload para não interromper.")
                page.wait_for_timeout(5000)
            
            url_atual = page.url
            logger.info(f"[PASSO 6] Ciclo {ciclo}/480 | URL: {url_atual}")
            
            # Atualiza o screenshot a cada 2 ciclos (1 minuto) para mostrar progresso
            if ciclo % 2 == 0:
                salvar_screenshot(page, "06_upload_progress", tarefa_id)
            
            # Estratégia 1: Redirecionamento para a página final do vídeo
            if url_atual != url_inicial and "/upload" not in url_atual:
                logger.info(f"[PASSO 6] Redirecionado para {url_atual} — upload concluído!")
                upload_ok = True
                break
                
            # Estratégia 2: Detecta o badge "Published" via DOM após reload
            if "/$/uploads" in url_atual:
                try:
                    # Regex de texto exato para o badge vermelho
                    published_exact = page.locator('text=/^Published$/').count() + page.locator('text=/^Publicado$/').count()
                    # Também tenta variantes com letra inicial maiúscula ou minúscula
                    published_broad = page.locator(':text("Published"), :text("Publicado")').count()
                    logger.info(f"[PASSO 6] Diagnóstico Published: exact={published_exact}, broad={published_broad}")
                    
                    if published_exact > 0 and ciclo > 0:
                        logger.info(f"[PASSO 6] Badge 'Published' detectado (exato: {published_exact}x) — upload concluído!")
                        salvar_screenshot(page, "06_published_detected", tarefa_id)
                        upload_ok = True
                        break
                    elif published_broad > 0 and ciclo > 0:
                        logger.info(f"[PASSO 6] Badge 'Published' detectado (broad: {published_broad}x) — upload concluído!")
                        salvar_screenshot(page, "06_published_detected", tarefa_id)
                        upload_ok = True
                        break
                except Exception as e:
                    logger.warning(f"[PASSO 6] Erro ao buscar badge Published: {e}")
            
            # Estratégia 3: API LBRY — mais confiável que o DOM, verifica a cada 2.5 min
            # Começa no ciclo 0 para detectar publicações que aconteceram antes do Passo 6
            if ciclo % 5 == 0:
                try:
                    # Busca o channel_name do banco de dados já que tarefa não está no escopo
                    conn_check = get_db_connection()
                    cursor_check = conn_check.cursor(dictionary=True)
                    cursor_check.execute("SELECT odysee_slug FROM mentoria_odysee_queue WHERE id = %s", (tarefa_id,))
                    row_check = cursor_check.fetchone()
                    cursor_check.execute("SELECT odysee_channel_name FROM languages WHERE id = %s", (os.getenv('MENTORIA_ODYSEE_LANGUAGE_ID', '10'),))
                    lang_row = cursor_check.fetchone()
                    cursor_check.close()
                    conn_check.close()
                    
                    if row_check:
                        channel_name_lbry = lang_row['odysee_channel_name'].lstrip('@') if lang_row else ''
                        video_slug = row_check['odysee_slug'] or slug
                        # Testa com canal e sem canal (fallback para uploads Anonymous)
                        lbry_urls_check = [
                            f"lbry://@{channel_name_lbry}/{video_slug}",
                            f"lbry://{video_slug}",
                        ]
                        api_url = "https://api.na-backend.odysee.com/api/v1/proxy?m=resolve"
                        payload = {"jsonrpc": "2.0", "method": "resolve", "params": {"urls": lbry_urls_check}}
                        res = requests.post(api_url, json=payload, timeout=15)
                        logger.info(f"[PASSO 6] API LBRY check | URLs: {lbry_urls_check} | HTTP: {res.status_code}")
                        if res.status_code == 200:
                            data = res.json()
                            result = data.get("result", {})
                            found = False
                            for lbry_url_c in lbry_urls_check:
                                entry = result.get(lbry_url_c, {})
                                if entry and "error" not in entry:
                                    logger.info(f"[PASSO 6] Vídeo confirmado pela API LBRY ({lbry_url_c}) — concluído!")
                                    salvar_screenshot(page, "06_lbry_confirmed", tarefa_id)
                                    upload_ok = True
                                    found = True
                                    break
                            if upload_ok:
                                break
                            if not found:
                                logger.info(f"[PASSO 6] API LBRY: claim ainda não encontrado. Verificado: {lbry_urls_check}")
                except Exception as e:
                    logger.warning(f"[PASSO 6] Erro ao checar API LBRY: {e}")
                    
        salvar_screenshot(page, "07_upload_complete", tarefa_id)
        if upload_ok:
            logger.info("[PASSO 6] Upload concluído com sucesso!")
        else:
            logger.warning("[PASSO 6] Timeout de 4h atingido. O upload pode ter sido concluído mesmo assim.")
        
        # PASSO 7: Capturar o link ody.sh de compartilhamento
        # Navega pela URL canônica do vídeo (funciona para o owner autenticado, inclusive Unlisted).
        # Tenta até 2 vezes com 15s de espera para absorver lentidão pontual do Odysee.
        share_link = None
        if upload_ok and slug:
            try:
                page.set_default_timeout(60000)
                page.set_default_navigation_timeout(60000)

                urls_to_try = []
                if channel_name:
                    urls_to_try.append(f"https://odysee.com/@{channel_name.lstrip('@')}/{slug}")
                urls_to_try.append(f"https://odysee.com/{slug}")

                logger.info(f"[PASSO 7] URLs candidatas para navegação: {urls_to_try}")

                for video_url in urls_to_try:
                    if share_link:
                        break
                    logger.info(f"[PASSO 7] Navegando para a página do vídeo: {video_url}")

                    for tentativa in range(2):
                        try:
                            page.goto(video_url, timeout=60000, wait_until="domcontentloaded")
                            try:
                                page.wait_for_selector('h1, .video-js, video', timeout=30000)
                            except:
                                pass
                            page.wait_for_timeout(8000)

                            try:
                                page.screenshot(path="/app/screenshots_mentoria/07_video_page.png", timeout=15000)
                            except Exception as e:
                                logger.warning(f"[PASSO 7] Screenshot opcional falhou (não crítico): {e}")

                            clicked = page.evaluate("""
                                () => {
                                    const btn = document.querySelector('button[aria-label="Share"], button[aria-label="Compartilhar"]');
                                    if (btn) { btn.click(); return true; }
                                    return false;
                                }
                            """)
                            if not clicked:
                                share_btn = page.locator('button[aria-label="Share"], button[aria-label="Compartilhar"]').first
                                share_btn.click(force=True, no_wait_after=True)
                            page.wait_for_timeout(2000)

                            share_input = page.locator('input[value*="ody.sh"]').first
                            if not share_input.is_visible():
                                share_input = page.locator('.modal input[type="text"], .dialog input[type="text"]').first

                            val = share_input.input_value(timeout=15000)
                            if val and "ody.sh" in val:
                                share_link = val
                                logger.info(f"[PASSO 7] Link ody.sh capturado (tentativa {tentativa+1}): {share_link}")
                                break  # sucesso
                            else:
                                logger.warning(f"[PASSO 7] Valor extraído não parece ody.sh: {val}")
                                share_link = None

                        except Exception as e:
                            logger.warning(f"[PASSO 7] Tentativa {tentativa+1} falhou: {e}")
                            if tentativa == 0:
                                logger.info("[PASSO 7] Aguardando 15s antes de tentar novamente...")
                                page.wait_for_timeout(15000)


            except Exception as e:
                logger.warning(f"[PASSO 7] Erro ao capturar link de compartilhamento: {e}")

        try:
            browser.close()
        except Exception as e:
            logger.warning(f"[PASSO 6] Ignorando erro ao fechar browser: {e}")
            
        return upload_ok, share_link

def escanear_drive():
    print("Escaneando Drive MENTORIA por novos vídeos...", flush=True)
    if not DRIVE_MENTORIA_FOLDER_ID:
        logger.error("DRIVE_MENTORIA_FOLDER_ID não configurado.")
        return

    try:
        drive_service = init_drive_service()

        # 1. Descobrir todas as pastas de origem dos vídeos dinamicamente.
        # O Google Meet agora cria subpastas "Google Meet" > "<evento> (recurring)".
        # Buscamos essas subpastas dentro da pasta raiz da Mentoria e também
        # globalmente (para capturar pastas criadas fora da hierarquia esperada).
        # A pasta raiz também é incluída como fallback para vídeos antigos.
        folder_ids = [DRIVE_MENTORIA_FOLDER_ID]

        try:
            logger.info("[SCAN] Buscando subpastas 'Google Meet' dentro da pasta da Mentoria...")

            # Busca pastas "Google Meet" filhas diretas da pasta raiz da Mentoria
            meet_folders = drive_service.files().list(
                q=f"'{DRIVE_MENTORIA_FOLDER_ID}' in parents and mimeType='application/vnd.google-apps.folder' and name='Google Meet' and trashed=false",
                fields='files(id, name)'
            ).execute().get('files', [])

            # Também busca globalmente por "Google Meet" (caso o Google crie fora da hierarquia)
            meet_folders_global = drive_service.files().list(
                q="mimeType='application/vnd.google-apps.folder' and name='Google Meet' and trashed=false",
                fields='files(id, name)'
            ).execute().get('files', [])

            # Une e remove duplicatas pelo ID
            seen_ids = {mf['id'] for mf in meet_folders}
            for mf in meet_folders_global:
                if mf['id'] not in seen_ids:
                    meet_folders.append(mf)
                    seen_ids.add(mf['id'])

            for mf in meet_folders:
                logger.info(f"[SCAN] Pasta 'Google Meet' encontrada: {mf['id']}")
                # Busca subpastas "recurring" (padrão do Google Meet para eventos recorrentes)
                sub_results = drive_service.files().list(
                    q=f"'{mf['id']}' in parents and mimeType='application/vnd.google-apps.folder' and name contains 'recurring' and trashed=false",
                    fields='files(id, name)'
                ).execute()
                for sub in sub_results.get('files', []):
                    logger.info(f"[SCAN] Subpasta recurring encontrada: {sub['name']} ({sub['id']})")
                    folder_ids.append(sub['id'])

        except Exception as e:
            logger.warning(f"[SCAN] Erro ao buscar subpastas Google Meet dinamicamente: {e}")

        # 2. Buscar arquivos de vídeo nessas pastas com filtro de nome da Mentoria
        arquivos = []
        for i in range(0, len(folder_ids), 10):
            lote = folder_ids[i:i+10]
            parents_q = " or ".join([f"'{fid}' in parents" for fid in lote])
            query = (
                f"({parents_q}) and mimeType contains 'video/' "
                f"and (name contains 'Mentorship Class' or name contains 'Mentoria') "
                f"and trashed=false"
            )
            results = drive_service.files().list(
                q=query,
                fields="files(id, name, size)"
            ).execute()
            arquivos.extend(results.get('files', []))

        print(f"Arquivos MENTORIA encontrados no Drive: {len(arquivos)}", flush=True)

        if not arquivos:
            return

        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)

        for arquivo in arquivos:
            file_id = arquivo['id']
            file_name = arquivo['name']

            cursor.execute("SELECT id FROM mentoria_odysee_queue WHERE drive_file_id = %s", (file_id,))
            if cursor.fetchone():
                continue

            if 'feedback' in file_name.lower():
                logger.info(f"Arquivo ignorado (Feedback): {file_name}")
                continue

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
    import requests
    
    api_url = "https://clck.ru/--"
    
    tentativas = 3
    for t in range(tentativas):
        try:
            res = requests.post(api_url, data={'url': url_longa}, timeout=10)
            if res.status_code == 200 and res.text.startswith('http'):
                short_url = res.text.strip()
                logger.info(f"URL encurtada com sucesso via clck.ru: {short_url}")
                return short_url
            else:
                logger.warning(f"clck.ru falhou na tentativa {t+1}: {res.text[:200]}")
        except Exception as e:
            logger.warning(f"Erro de conexão com clck.ru na tentativa {t+1}: {e}")
            
        if t < tentativas - 1:
            espera = 3
            logger.info(f"Aguardando {espera} segundos antes da próxima tentativa...")
            time.sleep(espera)
            
    logger.warning("clck.ru falhou 3 vezes. Usando URL canônica do Odysee.")
    return url_longa

def notificar_whatsapp(titulo, url_curta, thumbnail_b64=None):
    """
    Envia a notificação de novo vídeo para o grupo Our Classes.
    Retorna uma tupla (mensagem_str, sucesso_bool).
    sucesso_bool = True apenas se ao menos um envio foi disparado sem exceção.
    """
    # --- Busca o template editável do banco (settings table) ---
    template = "🎓 *{titulo}*\n\n🔗 {url}"
    try:
        conn_t = get_db_connection()
        cursor_t = conn_t.cursor(dictionary=True)
        cursor_t.execute("SELECT setting_value FROM settings WHERE setting_key = 'mentoria_odysee_wpp_template' LIMIT 1")
        row = cursor_t.fetchone()
        if row and row['setting_value']:
            template = row['setting_value']
        cursor_t.close()
        conn_t.close()
    except Exception as e:
        logger.warning(f"[WHATSAPP] Não foi possível ler template do banco, usando padrão: {e}")

    mensagem = template.replace('{titulo}', titulo).replace('{url}', url_curta)

    # --- Busca o JID do Our Classes direto do Baileys (fonte de verdade única) ---
    grupos_alvo = []
    try:
        resp = requests.get(
            "http://host.docker.internal:3000/mentoria-config",
            headers={"apikey": "SenhaMeetups2026"},
            timeout=5
        )
        if resp.status_code == 200:
            conf = resp.json()
            jid = conf.get("groups", {}).get("our_classes", {}).get("jid")
            if jid and jid.strip():
                grupos_alvo = [jid]
                logger.info(f"[WHATSAPP] Grupo alvo Our Classes: {jid}")
            else:
                # ERRO EXPLÍCITO: JID ausente é um problema de configuração, não um aviso
                logger.error(
                    "[WHATSAPP] FALHA: our_classes.jid está vazio ou ausente no mentoria-config. "
                    "A mensagem NÃO será enviada. Verifique o painel Mentoria > Configurações."
                )
        else:
            logger.error(f"[WHATSAPP] FALHA: Baileys retornou HTTP {resp.status_code} ao buscar mentoria-config. Mensagem NÃO enviada.")
    except Exception as e:
        logger.error(f"[WHATSAPP] FALHA CRÍTICA ao buscar mentoria-config do Baileys: {e}. Mensagem NÃO será enviada.")

    if not grupos_alvo:
        logger.error("[WHATSAPP] Nenhum grupo alvo encontrado. Abortando envio da notificação.")
        return mensagem, False

    link_preview_data = {
        "title": titulo,
        "body": "Disponível agora no Odysee (Não-listado)",
        "url": url_curta
    }
    if thumbnail_b64:
        link_preview_data["thumbnailBase64"] = thumbnail_b64

    wpp_ok = False
    for grupo_id in grupos_alvo:
        try:
            requests.post("http://host.docker.internal:3000/send", json={
                "to": grupo_id,
                "message": mensagem,
                "source": "mentoria_pipeline",
                "linkPreview": link_preview_data
            }, headers={"apikey": "SenhaMeetups2026"}, timeout=15)
            logger.info(f"[WHATSAPP] Notificação enviada para {grupo_id}")
            wpp_ok = True
        except Exception as e:
            logger.error(f"[WHATSAPP] Erro ao notificar {grupo_id}: {e}")

    return mensagem, wpp_ok



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
        clean_channel_name = channel_name.lstrip('@')
        is_published = verificar_video_publicado(clean_channel_name, tarefa['odysee_slug'])
        
        if is_published:
            logger.info(f"[{tarefa['id']}] Vídeo já publicado na LBRY. Pulando upload.")
            upload_ok = True
            share_link = capturar_share_link_playwright(tarefa['id'], auth_token, clean_channel_name, tarefa['odysee_slug'])
        else:
            upload_ok, share_link = publicar_odysee_playwright(tarefa['id'], auth_token, title, temp_path, slug=tarefa.get('odysee_slug'), channel_name=channel_name)
        
        if not upload_ok:
            raise Exception("Falha no processo de publicação (Timeout)")
            
        if share_link:
            url_curta = share_link
            logger.info(f"[SHARE] Usando link ody.sh: {url_curta}")
        else:
            # IMPORTANTE: Para a Mentoria, o link ody.sh é OBRIGATÓRIO pois vídeos
            # Unlisted não são acessíveis pela URL canônica pelos alunos.
            # Usar o encurtador russo como fallback geraria um link quebrado.
            # Forçamos uma Exception aqui para que o sistema de retry automático
            # tente novamente em 60s — na próxima tentativa o upload já estará
            # publicado e o worker vai pular direto para capturar o link.
            raise Exception("[SHARE] Link ody.sh não obtido. Tarefa voltará para pending e será reprocessada automaticamente.")
        # Re-inicializa a conexão do Drive pois uploads longos causam timeout/Broken Pipe
        drive_service = init_drive_service()
        mover_arquivos_mentoria(drive_service, tarefa['drive_file_id'], tarefa['drive_file_name'])
        
        thumbnail_b64 = None
        thumb_path = "/app/screenshots_mentoria/thumbnail_selected.jpg"
        if os.path.exists(thumb_path):
            try:
                import cv2
                import base64
                img = cv2.imread(thumb_path)
                if img is not None:
                    img = cv2.resize(img, (256, 144), interpolation=cv2.INTER_AREA)
                    result, encimg = cv2.imencode('.jpg', img, [int(cv2.IMWRITE_JPEG_QUALITY), 50])
                    if result: thumbnail_b64 = base64.b64encode(encimg).decode('utf-8')
            except: pass
            
        msg_wpp, wpp_ok = notificar_whatsapp(title, url_curta, thumbnail_b64)
        if not wpp_ok:
            # Grava aviso visível no painel: tarefa concluída mas WPP não foi enviado
            logger.error(f"[WHATSAPP] Notificação NÃO enviada para a tarefa {tarefa['id']}. Verifique os logs e o painel Mentoria > Configurações.")
            msg_wpp = f"[WPP FALHOU — verificar config] {msg_wpp}"
        atualizar_status(tarefa['id'], 'done', odysee_url=url_curta, whatsapp_message=msg_wpp)
        
    except Exception as e:
        logger.exception("Erro processando fila mentoria")
        retry = tarefa['retry_count'] + 1
        novo_status = 'error' if retry >= 5 else 'pending'  # 5 tentativas antes de marcar como error
        atualizar_status(tarefa['id'], novo_status, error_msg=str(e), retry_count=retry)
    finally:
        if temp_path and os.path.exists(temp_path):
            os.remove(temp_path)

def cleanup_zombies():
    """Ao iniciar, reverte tarefas 'processing' para 'pending'.
    Isso evita que tarefas fiquem presas se o worker foi reiniciado no meio do processo."""
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("UPDATE mentoria_odysee_queue SET status='pending', error_message='[RESTART] Worker reiniciado no meio do processo — retry automático' WHERE status='processing'")
        affected = cursor.rowcount
        conn.commit()
        cursor.close()
        conn.close()
        if affected > 0:
            logger.info(f"[STARTUP] {affected} tarefa(s) zombie revertida(s) para 'pending'.")
        else:
            logger.info("[STARTUP] Nenhuma tarefa zombie encontrada.")
    except Exception as e:
        logger.error(f"[STARTUP] Erro ao limpar tarefas zombie: {e}")

if __name__ == "__main__":
    print("Iniciando Worker de Mentoria...", flush=True)
    cleanup_zombies()
    while True:
        try:
            processar_fila()
        except Exception as e:
            logger.error(f"Erro no loop: {e}")
        time.sleep(60)
