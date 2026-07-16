import os
import time
import urllib.parse
import logging
import requests
import mysql.connector
import datetime
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

GOOGLE_SA_JSON = 'google_service_account.json' # montado no docker
PASTA_RAIZ_DRIVE = os.getenv('DRIVE_RECORDINGS_FOLDER_ID')

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
            SELECT q.*, l.odysee_auth_token, l.odysee_channel_name, l.whatsapp_group_id, l.name as language_name, l.flag_emoji
            FROM odysee_publish_queue q
            JOIN languages l ON q.language_id = l.id
            WHERE q.status = "pending" OR q.status = "skip_publish"
            ORDER BY q.id ASC LIMIT 1
        ''')
        return cursor.fetchone()
    finally:
        cursor.close()
        conn.close()

def atualizar_status(tarefa_id, status, error_msg=None, odysee_url=None, retry_count=None):
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
            
            # Também atualiza o registro do replay para esta linguagem
            cursor.execute("SELECT language_id FROM odysee_publish_queue WHERE id = %s", (tarefa_id,))
            row = cursor.fetchone()
            if row:
                lang_id = row[0]
                cursor.execute("UPDATE meetup_replays SET link = %s WHERE language_id = %s AND (link IS NULL OR link = '') ORDER BY semana DESC LIMIT 1", (odysee_url, lang_id))
            
        if retry_count is not None:
            update_cols.append("retry_count = %s")
            params.append(retry_count)
            
        if status in ['done', 'error']:
            update_cols.append("processed_at = CURRENT_TIMESTAMP")
            
        params.append(tarefa_id)
        
        sql = f"UPDATE odysee_publish_queue SET {', '.join(update_cols)} WHERE id = %s"
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

def mover_video_e_apagar_chat(drive_service, file_id, file_name, language_name, move_video=True):
    """
    Move o vídeo para a subpasta do idioma e move o chat para a pasta de Transcrições.
    Usa files().update() (mover) pois a service account tem apenas permissão de Editor
    nos arquivos criados pelo Google Meet — não pode deletar, apenas mover.
    Implementa retry automático para [Errno 32] Broken pipe.
    """
    import time as _time
    PASTA_TRANSCRICOES_ID = '12WW-A0wwZVvR2-Rj1Y7vO04Rhkzi9bwI'
    
    for tentativa in range(2):
        try:
            drive = init_drive_service()
            
            # 1. Move o vídeo para a pasta do idioma
            if move_video:
                results = drive.files().list(
                    q=f"'{PASTA_RAIZ_DRIVE}' in parents and mimeType='application/vnd.google-apps.folder' and name = '{language_name}'",
                    fields="files(id, name)"
                ).execute()
                pastas = results.get('files', [])
                if pastas:
                    pasta_idioma_id = pastas[0]['id']
                    try:
                        file_meta = drive.files().get(fileId=file_id, fields='parents').execute()
                        current_parents = file_meta.get('parents', [])
                        if pasta_idioma_id not in current_parents:
                            drive.files().update(
                                fileId=file_id,
                                addParents=pasta_idioma_id,
                                removeParents=",".join(current_parents)
                            ).execute()
                            logger.info(f"[DRIVE] Vídeo movido para a pasta '{language_name}'")
                        else:
                            logger.info(f"[DRIVE] Vídeo já está na pasta '{language_name}', nada a fazer.")
                    except Exception as e_vid:
                        logger.error(f"[DRIVE] Erro ao mover vídeo: {e_vid}")
                else:
                    logger.warning(f"[DRIVE] Pasta do idioma '{language_name}' não encontrada. Vídeo mantido na raiz.")
            
            # 2. Move o arquivo de Chat (.txt) para a pasta de Transcrições
            base_name = file_name.replace(' - Recording.mp4', '').replace(' - Recording', '')
            chat_results = drive.files().list(
                q=f"'{PASTA_RAIZ_DRIVE}' in parents and mimeType='text/plain' and name contains '{base_name}'",
                fields="files(id, name, parents)"
            ).execute()
            
            for chat in chat_results.get('files', []):
                try:
                    drive.files().update(
                        fileId=chat['id'],
                        addParents=PASTA_TRANSCRICOES_ID,
                        removeParents=",".join(chat.get('parents', []))
                    ).execute()
                    logger.info(f"[DRIVE] Chat movido para Transcrições: {chat['name']}")
                except Exception as e_chat:
                    logger.error(f"[DRIVE] Erro ao mover chat '{chat['name']}': {e_chat}")
            
            return  # Sucesso
            
        except Exception as e:
            if tentativa == 0 and 'Broken pipe' in str(e):
                logger.warning(f"[DRIVE] Broken pipe detectado, aguardando 5s e tentando novamente...")
                _time.sleep(5)
            else:
                logger.error(f"[DRIVE] Erro geral na operação Drive (tentativa {tentativa+1}): {e}")
                return


SCREENSHOT_DIR = "/app/screenshots"

def salvar_screenshot(page, nome, tarefa_id):
    """Salva screenshot no disco e no banco de dados em Base64."""
    try:
        import os
        import base64
        os.makedirs(SCREENSHOT_DIR, exist_ok=True)
        path = f"{SCREENSHOT_DIR}/{nome}.png"
        page.screenshot(path=path)
        
        # Converte para base64 para o painel de admin
        with open(path, "rb") as image_file:
            encoded_string = base64.b64encode(image_file.read()).decode('utf-8')
            
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("UPDATE odysee_publish_queue SET last_screenshot = %s, last_screenshot_time = NOW() WHERE id = %s", (encoded_string, tarefa_id))
        conn.commit()
        cursor.close()
        conn.close()
        
        logger.info(f"[SCREENSHOT] {nome} | URL: {page.url} | Título: {page.title()}")
    except Exception as e:
        logger.warning(f"[SCREENSHOT] Falhou ao salvar {nome}: {e}")

def publicar_odysee_playwright(tarefa_id, auth_token, title, file_path, slug=None):
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
                # Aguarda até 5 minutos (300s) o botão sair do estado disabled
                # Fazemos isso em um loop Python para poder atualizar o screenshot no painel
                logger.info("[PASSO 4] Aguardando botão habilitar (pode demorar se o vídeo for grande)...")
                for w in range(30):
                    btn_pronto = page.evaluate(
                        """
                        () => {
                            const pub = document.querySelector('button[aria-label="Publish"], button[aria-label="Publicação"]');
                            if (pub && !pub.disabled) return true;
                            const nxt = document.querySelector('button[aria-label="Next"], button[aria-label="Próximo"]');
                            return nxt && !nxt.disabled;
                        }
                        """
                    )
                    if btn_pronto:
                        logger.info("[PASSO 4] Botão habilitado após análise do arquivo.")
                        break
                    
                    # Atualiza o screenshot a cada 20 segundos para não parecer travado
                    if w > 0 and w % 2 == 0:
                        salvar_screenshot(page, f"05_wizard_step_{step}_waiting", tarefa_id)
                    
                    page.wait_for_timeout(10000) # espera 10s
                    
            except Exception as e:
                logger.warning(f"[PASSO 4] Erro ao aguardar botão: {e}")

            # Atualiza referências após a espera
            next_btn = page.locator('button:has-text("Próximo"), button:has-text("Next")').first
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
                
            # Estratégia 3: API LBRY — mais confiável que o DOM, verifica a cada 1.5 min
            if ciclo > 1 and ciclo % 3 == 0:
                try:
                    # Busca o channel_name do banco de dados já que tarefa não está no escopo
                    conn_check = get_db_connection()
                    cursor_check = conn_check.cursor(dictionary=True)
                    cursor_check.execute("""
                        SELECT l.odysee_channel_name, q.odysee_slug 
                        FROM odysee_publish_queue q 
                        JOIN languages l ON q.language_id = l.id 
                        WHERE q.id = %s
                    """, (tarefa_id,))
                    row_check = cursor_check.fetchone()
                    cursor_check.close()
                    conn_check.close()
                    
                    if row_check:
                        channel_name = row_check['odysee_channel_name'].lstrip('@')
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
        
        try:
            browser.close()
        except Exception as e:
            logger.warning(f"[PASSO 6] Ignorando erro ao fechar browser: {e}")
            
        return upload_ok

def escanear_drive():
    print("Escaneando Drive por novos vídeos...", flush=True)
    try:
        drive_service = init_drive_service()
        results = drive_service.files().list(
            q=f"'{PASTA_RAIZ_DRIVE}' in parents and mimeType contains 'video/' and name contains ' - Recording'",
            fields="files(id, name)"
        ).execute()
        arquivos = results.get('files', [])
        print(f"Arquivos encontrados no Drive: {len(arquivos)}", flush=True)
        
        if not arquivos:
            return
            
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        # Busca TODOS os idiomas, independente de ter odysee configurado.
        cursor.execute("SELECT id, name, odysee_auth_token, odysee_channel_name, odysee_auto_enabled FROM languages")
        idiomas = cursor.fetchall()
        
        for arquivo in arquivos:
            file_id = arquivo['id']
            file_name = arquivo['name']
            
            # Verifica se já está na fila
            cursor.execute("SELECT id FROM odysee_publish_queue WHERE drive_file_id = %s", (file_id,))
            if cursor.fetchone():
                continue
                
            # Identifica o idioma
            language_id = None
            idioma_escolhido = None
            file_name_norm = normalize_text(file_name)
            for idioma in idiomas:
                if normalize_text(idioma['name']) in file_name_norm:
                    language_id = idioma['id']
                    idioma_escolhido = idioma
                    break
                    
            if not language_id:
                logger.warning(f"Idioma não identificado no arquivo: {file_name}")
                continue
                
            # Formata o título e o slug
            # Original: 🇺🇲 Encontro Online de Inglês - 2026/06/16 20:06 GMT-03:00 - Recording.mp4
            # Título limpo: 🇺🇲 Encontro Online de Inglês - 2026/06/16  (sem hora e sem "- Recording")
            import re as _re
            # Remove " HH:MM GMT..." e " - Recording" e extensão do arquivo
            titulo_limpo = file_name
            titulo_limpo = _re.sub(r'\s+\d{2}:\d{2}\s+GMT.*', '', titulo_limpo)  # Remove horário e GMT
            titulo_limpo = titulo_limpo.replace(' - Recording', '').replace('.mp4', '').strip()
            
            # Gera o slug de URL com apenas a data (YYYY_MM_DD)
            # Aceita: YYYY/MM/DD, YYYY-MM-DD e YYYY_MM_DD (nomes de arquivo com underscores)
            date_match = _re.search(r'(\d{4})[/\-_](\d{2})[/\-_](\d{2})', file_name)
            if date_match:
                slug = f"{date_match.group(1)}_{date_match.group(2)}_{date_match.group(3)}"
            else:
                # Fallback: usa apenas os dígitos da data se encontrar padrão numérico
                numbers = _re.findall(r'\d{4}', file_name)
                slug = numbers[0] if numbers else 'upload'  # Último recurso seguro
            # Verifica se o idioma está configurado para publicar no Odysee
            has_odysee = bool(idioma_escolhido.get('odysee_auth_token')) and bool(idioma_escolhido.get('odysee_channel_name')) and bool(idioma_escolhido.get('odysee_auto_enabled'))
            
            if not has_odysee:
                titulo_final = titulo_limpo
                status_inicial = 'skip_publish'
                logger.info(f"Idioma sem canal ({idioma_escolhido['name']}). Marcando para apenas organizar arquivos.")
            else:
                # Verifica se já tem título preenchido no portal (meetup_replays)
                semana_atual = datetime.datetime.now().strftime("%G-W%V")
                cursor.execute("SELECT titulo FROM meetup_replays WHERE language_id = %s AND semana = %s AND titulo IS NOT NULL AND titulo != ''", (language_id, semana_atual))
                row_replay = cursor.fetchone()
                
                if row_replay:
                    # Host já preencheu o título antes de o robô escanear!
                    titulo_final = row_replay['titulo']
                    status_inicial = 'pending'
                    logger.info(f"Título já preenchido previamente pelo host: {titulo_final}. Indo direto para pending.")
                else:
                    titulo_final = titulo_limpo
                    status_inicial = 'waiting_host'
            
            # Insere no banco
            cursor.execute("""
                INSERT INTO odysee_publish_queue 
                (language_id, drive_file_id, drive_file_name, status, titulo_final, odysee_slug) 
                VALUES (%s, %s, %s, %s, %s, %s)
            """, (language_id, file_id, file_name, status_inicial, titulo_final, slug))
            conn.commit()
            logger.info(f"Novo vídeo adicionado à fila de triagem: {file_name} | Status Inicial: {status_inicial} | Slug: {slug}")
            
        cursor.close()
        conn.close()
    except Exception as e:
        logger.error(f"Erro ao escanear Drive: {e}")

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

def processar_fila():
    # 1. Escanear o Drive por novos arquivos
    escanear_drive()
    
    # 2. Processar a fila existente
    tarefa = buscar_proxima_tarefa()
    if not tarefa:
        return
        
    logger.info(f"Processando tarefa: {tarefa['titulo_final']} (Status: {tarefa['status']})")
    atualizar_status(tarefa['id'], 'processing')
    
    temp_path = None
    try:
        drive_service = init_drive_service()
        
        if tarefa.get('status') == 'skip_publish' or tarefa['status'] == 'processing':
            # Se for skip_publish, só foi mudado pra processing pela linha acima, então o original era skip_publish
            # Porém a query também retorna tarefas que eram pending.
            # Como saber se era skip_publish antes da atualização? O worker vai tentar verificar se tem token.
            pass
            
        # Refinando a verificação:
        is_skip_publish = (not tarefa['odysee_auth_token']) or (not tarefa['odysee_channel_name'])
        
        if is_skip_publish:
            logger.info("Tarefa não tem canal Odysee. Apenas organizando pastas.")
            mover_video_e_apagar_chat(drive_service, tarefa['drive_file_id'], tarefa['drive_file_name'], tarefa['language_name'], move_video=False)
            atualizar_status(tarefa['id'], 'done', error_msg="Organizado no Drive (sem canal para publicar).")
            return

        temp_path = baixar_video_drive(drive_service, tarefa['drive_file_id'], tarefa['drive_file_name'])
        
        if not tarefa['odysee_auth_token']:
            raise Exception("Auth token não configurado")
            
        title = tarefa.get('titulo_final') or tarefa.get('topico') or tarefa.get('drive_file_name', 'Sem Título')
        upload_ok = publicar_odysee_playwright(tarefa['id'], tarefa['odysee_auth_token'], title, temp_path, slug=tarefa.get('odysee_slug'))
        
        if not upload_ok:
            raise Exception("Falha no processo de publicação (Timeout ou Erro no Odysee)")
            
        # Odysee final URL (Canonica)
        odysee_url = f"https://odysee.com/{tarefa['odysee_channel_name']}/{tarefa['odysee_slug']}"
        
        # Encurtar a URL via TinyURL!
        url_curta = encurtar_url(odysee_url)
        
        mover_video_e_apagar_chat(drive_service, tarefa['drive_file_id'], tarefa['drive_file_name'], tarefa['language_name'])
        
        atualizar_status(tarefa['id'], 'done', odysee_url=url_curta)
        
        # Prepara a imagem em base64 para o Link Preview
        thumbnail_b64 = None
        thumb_path = "/app/screenshots/thumbnail_selected.jpg"
        if os.path.exists(thumb_path):
            try:
                import cv2
                import base64
                
                # Lê a imagem original gerada do vídeo (geralmente 1280x720)
                img = cv2.imread(thumb_path)
                if img is not None:
                    # Redimensiona para 854x480 (480p) para garantir nitidez quando o WhatsApp esticar a imagem
                    new_dim = (854, 480)
                    img = cv2.resize(img, new_dim, interpolation=cv2.INTER_AREA)
                    
                    # Codifica como JPEG em memória com 75% de qualidade para compensar o aumento da resolução e manter sob 100KB
                    encode_param = [int(cv2.IMWRITE_JPEG_QUALITY), 75]
                    result, encimg = cv2.imencode('.jpg', img, encode_param)
                    if result:
                        thumbnail_b64 = base64.b64encode(encimg).decode('utf-8')
                        logger.info(f"Thumbnail comprimido com sucesso via OpenCV ({len(thumbnail_b64)} chars base64)")
            except Exception as e:
                logger.warning(f"Erro ao converter thumbnail para base64: {e}")
                
        template = "🎬 *Replay:* {bandeira} {titulo}\n\n🔗 {link}"
        grupos_alvo = []
        try:
            conn_wpp = get_db_connection()
            cursor_wpp = conn_wpp.cursor(dictionary=True)
            
            # Tenta buscar o template configurado no painel
            cursor_wpp.execute("SELECT setting_value FROM settings WHERE setting_key = 'odysee_whatsapp_template'")
            row = cursor_wpp.fetchone()
            if row and row['setting_value']:
                template = row['setting_value']
                
            # Verifica o Modo de Contenção do Odysee
            cursor_wpp.execute("SELECT valor FROM system_settings WHERE chave = 'wpp_odysee_ativo'")
            row_modo = cursor_wpp.fetchone()
            odysee_modo = '0'
            if row_modo and row_modo['valor']:
                odysee_modo = row_modo['valor']
                
            if odysee_modo == '1':
                odysee_modo = 'full'
                
            if odysee_modo == '0':
                logger.info("[WHATSAPP] Modo de contenção ativado (0). Disparo cancelado.")
                grupos_alvo = []
            elif odysee_modo == 'hosts':
                logger.info("[WHATSAPP] Modo Apenas Hosts ativado. Disparando apenas para os hosts.")
                grupos_alvo = ['120363164732845564@g.us']
            else:
                logger.warning("[WHATSAPP] MODO DISPARO TOTAL ATIVADO. Enviando para todos os grupos!")
                cursor_wpp.execute("""
                    SELECT group_id FROM meetup_whatsapp_groups 
                    WHERE ativo = 1 AND (categoria = 'multi_idioma' OR (categoria = 'especifico' AND language_id = %s))
                """, (tarefa['language_id'],))
                grupos_alvo = [r['group_id'] for r in cursor_wpp.fetchall()]
                
            cursor_wpp.close()
            conn_wpp.close()
        except Exception as e:
            logger.error(f"Erro ao buscar configurações: {e}")
            
        if grupos_alvo:
            title_msg = tarefa.get('titulo_final') or tarefa.get('topico') or tarefa.get('drive_file_name', '')
            idioma_nome = tarefa.get('language_name', '')
            bandeira = tarefa.get('flag_emoji', '')
            if not bandeira:
                bandeira = ''
            mensagem = template.replace('{titulo}', title_msg).replace('{link}', url_curta).replace('{link_canonico}', odysee_url).replace('{idioma}', idioma_nome).replace('{bandeira}', bandeira)
            link_preview_data = {
                "title": title_msg,
                "body": "Disponível agora no Odysee",
                "url": url_curta
            }
            if thumbnail_b64:
                link_preview_data["thumbnailBase64"] = thumbnail_b64
                
            for grupo_id in grupos_alvo:
                try:
                    # Usa 127.0.0.1 já que o container usa --network host
                    requests.post("http://127.0.0.1:3000/send", json={
                        "to": grupo_id,
                        "message": mensagem,
                        "source": "odysee_pipeline",
                        "linkPreview": link_preview_data
                    }, headers={"apikey": "SenhaMeetups2026"}, timeout=15)
                    logger.info(f"[WHATSAPP] Mensagem enviada para o grupo {grupo_id}")
                except Exception as e:
                    logger.warning(f"[WHATSAPP] Falhou ao enviar mensagem para {grupo_id}: {e}")
            
        # Notifica os hosts via webhook integrado para garantir o mesmo padrão do portal
        # Dispara independente do modo de contenção (grupos_alvo vazio ou não), pois é uma notificação interna
        try:
            webhook_url = "https://dev.encontrodeidiomas.com.br/ajax/webhook_odysee_success.php"
            res_webhook = requests.post(webhook_url, json={
                "apikey": "SenhaMeetups2026",
                "lang_id": tarefa.get('language_id')
            }, timeout=15)
            logger.info(f"[WHATSAPP] Webhook de notificação dos hosts acionado: {res_webhook.status_code}")
        except Exception as e:
            logger.warning(f"[WHATSAPP] Falhou ao acionar webhook de notificação dos hosts: {e}")
            
    except Exception as e:
        erro_str = str(e).lower()
        # Se o erro for de arquivo não disponível no Drive ainda (host preencheu antes do Drive processar),
        # volta para 'pending' SEM incrementar retry_count — o worker vai retentar sem penalidade
        if any(termo in erro_str for termo in ['not found', 'file not found', '404', 'httplib2', 'invalid file id', 'does not exist', 'no such file']):
            logger.warning(f"[DRIVE] Arquivo ainda não disponível no Drive: {e}. Voltando para 'pending' para retry automático.")
            atualizar_status(tarefa['id'], 'pending', error_msg=f"[AGUARDANDO DRIVE] {str(e)}", retry_count=tarefa['retry_count'])
        else:
            logger.exception("Erro durante o processamento da fila")
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
        cursor.execute("UPDATE odysee_publish_queue SET status='pending', error_message='[RESTART] Worker reiniciado no meio do processo — retry automático' WHERE status='processing'")
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
    print("Iniciando Worker...", flush=True)
    cleanup_zombies()
    while True:
        try:
            print("Iniciando iteração...", flush=True)
            processar_fila()
            print("Iteração concluída.", flush=True)
        except Exception as e:
            logger.error(f"Erro no loop principal: {e}")
            print(f"Erro no loop principal: {e}", flush=True)
        print("Dormindo por 60s...", flush=True)
        time.sleep(60)
