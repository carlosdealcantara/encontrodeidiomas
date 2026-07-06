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
            if ciclo > 2 and ciclo % 5 == 0:
                try:
                    # Busca o channel_name do banco de dados já que tarefa não está no escopo
                    conn_check = get_db_connection()
                    cursor_check = conn_check.cursor(dictionary=True)
                    cursor_check.execute("SELECT odysee_slug FROM mentoria_odysee_queue WHERE id = %s", (tarefa_id,))
                    row_check = cursor_check.fetchone()
                    cursor_check.execute("SELECT odysee_channel_name FROM languages WHERE id = %s", (os.getenv('MENTORIA_ODYSEE_LANGUAGE_ID', '10'),))
                    lang_row = cursor_check.fetchone()
                    row_check = cursor_check.fetchone()
                    cursor_check.close()
                    conn_check.close()
                    
                    if row_check:
                        channel_name = lang_row['odysee_channel_name'].lstrip('@') if lang_row else ''
                        video_slug = row_check['odysee_slug'] or slug
                        lbry_url = f"lbry://@{channel_name}/{video_slug}"
                        api_url = "https://api.na-backend.odysee.com/api/v1/proxy?m=resolve"
                        payload = {"jsonrpc": "2.0", "method": "resolve", "params": {"urls": [lbry_url]}}
                        res = requests.post(api_url, json=payload, timeout=15)
                        logger.info(f"[PASSO 6] API LBRY check | URL: {lbry_url} | HTTP: {res.status_code}")
                        if res.status_code == 200:
                            data = res.json()
                            result = data.get("result", {})
                            entry = result.get(lbry_url, {})
                            if entry and "error" not in entry:
                                logger.info(f"[PASSO 6] Vídeo confirmado pela API LBRY — concluído!")
                                salvar_screenshot(page, "06_lbry_confirmed", tarefa_id)
                                upload_ok = True
                                break
                            else:
                                logger.info(f"[PASSO 6] API LBRY: claim ainda não encontrado. Resposta: {str(entry)[:200]}")
                except Exception as e:
                    logger.warning(f"[PASSO 6] Erro ao checar API LBRY: {e}")
                    
        salvar_screenshot(page, "07_upload_complete", tarefa_id)
        if upload_ok:
            logger.info("[PASSO 6] Upload concluído com sucesso!")
        else:
            logger.warning("[PASSO 6] Timeout de 4h atingido. O upload pode ter sido concluído mesmo assim.")
        
        # PASSO 7: Capturar o link ody.sh de compartilhamento
        share_link = None
        if upload_ok and channel_name and slug:
            try:
                video_url = f"https://odysee.com/{channel_name}/{slug}"
                logger.info(f"[PASSO 7] Navegando para a página do vídeo: {video_url}")
                page.goto(video_url, timeout=60000, wait_until="domcontentloaded")
                page.wait_for_timeout(4000)
                salvar_screenshot(page, "07_video_page", tarefa_id)
                
                share_btn = page.locator('button:has-text("Compartilhar"), button:has-text("Share")').first
                if share_btn.is_visible():
                    share_btn.click(timeout=15000)
                    page.wait_for_timeout(2000)
                    salvar_screenshot(page, "08_share_modal", tarefa_id)
                    
                    share_input = page.locator('input[value*="ody.sh"]').first
                    if not share_input.is_visible():
                        share_input = page.locator('.modal input[type="text"], .dialog input[type="text"]').first
                    
                    share_link = share_input.input_value(timeout=10000)
                    if share_link and "ody.sh" in share_link:
                        logger.info(f"[PASSO 7] Link ody.sh capturado: {share_link}")
                    else:
                        logger.warning(f"[PASSO 7] Link extraído não parece ser ody.sh: {share_link}")
                        share_link = None
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
        upload_ok, share_link = publicar_odysee_playwright(tarefa['id'], auth_token, title, temp_path, slug=tarefa.get('odysee_slug'), channel_name=channel_name)
        
        if not upload_ok:
            raise Exception("Falha no processo de publicação (Timeout)")
            
        if share_link:
            url_curta = share_link
            logger.info(f"[SHARE] Usando link ody.sh: {url_curta}")
        else:
            odysee_url = f"https://odysee.com/{channel_name}/{tarefa['odysee_slug']}"
            url_curta = encurtar_url(odysee_url)
            logger.warning(f"[SHARE] Fallback para URL canônica encurtada: {url_curta}")
        
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
