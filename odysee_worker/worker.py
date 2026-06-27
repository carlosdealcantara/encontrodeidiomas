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
            WHERE q.status = "pending" AND l.odysee_auto_enabled = 1
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

def mover_video_e_apagar_chat(drive_service, file_id, file_name, language_name):
    try:
        # 1. Move o vídeo para a pasta do idioma
        results = drive_service.files().list(
            q=f"'{PASTA_RAIZ_DRIVE}' in parents and mimeType='application/vnd.google-apps.folder' and name = '{language_name}'",
            fields="files(id, name)"
        ).execute()
        pastas = results.get('files', [])
        if pastas:
            pasta_id = pastas[0]['id']
            file = drive_service.files().get(fileId=file_id, fields='parents').execute()
            drive_service.files().update(
                fileId=file_id,
                addParents=pasta_id,
                removeParents=",".join(file.get('parents', []))
            ).execute()
            logger.info(f"[DRIVE] Vídeo movido para a pasta '{language_name}'")
        
        # 2. Apaga o arquivo de Chat (.txt) correspondente
        # O nome do vídeo original é parecido com "... - Recording" e do chat "... - Chat"
        base_name = file_name.replace(' - Recording.mp4', '').replace(' - Recording', '')
        chat_results = drive_service.files().list(
            q=f"'{PASTA_RAIZ_DRIVE}' in parents and mimeType='text/plain' and name contains '{base_name}'",
            fields="files(id, name)"
        ).execute()
        
        chats = chat_results.get('files', [])
        for chat in chats:
            # Apaga (move para lixeira)
            drive_service.files().update(fileId=chat['id'], body={'trashed': True}).execute()
            logger.info(f"[DRIVE] Chat de texto movido para lixeira: {chat['name']}")
    except Exception as e:
        logger.error(f"[DRIVE] Erro ao mover vídeo ou apagar chat: {e}")

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
            locale="pt-BR"
        )
        page = context.new_page()
        
        # Aumenta timeout para conexões lentas ou uploads grandes (4 horas = 14400000ms)
        page.set_default_timeout(14400000)
        page.set_default_navigation_timeout(14400000)
        
        # PASSO 1: Acessar home e injetar token
        logger.info("[PASSO 1] Acessando odysee.com para injetar token...")
        page.goto("https://odysee.com", timeout=60000)
        page.wait_for_load_state("networkidle")
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
        page.reload(timeout=60000)
        page.wait_for_load_state("networkidle")
        
        # PASSO 2: Ir para página de upload
        logger.info("[PASSO 2] Navegando para /$/upload...")
        page.goto("https://odysee.com/$/upload", timeout=60000)
        page.wait_for_load_state("networkidle")
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
            publish_btn = page.locator('.button--primary >> text="Publicação"').first
            if not publish_btn.is_visible():
                publish_btn = page.locator('form button.button--primary:has-text("Publicação"), .publish__actions button.button--primary').first
            
            if publish_btn.is_visible():
                logger.info("Botão FINAL de Publicação encontrado no rodapé! Clicando...")
                try:
                    publish_btn.click(timeout=300000)
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
                        confirm_btn = page.locator('button:has-text("Enviar"), button:has-text("Upload")').filter(has_text="Enviar").last
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
                        enviar_btn = page.locator('button:has-text("Enviar"), button:has-text("Upload")').first
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

            # Se não achou o Publicação, clica em Próximo
            next_btn = page.locator('button:has-text("Próximo"), button:has-text("Next")').first
            if next_btn.is_visible():
                logger.info("Clicando em Próximo...")
                try:
                    next_btn.click(timeout=300000)
                except Exception as e:
                    logger.warning(f"Erro ao clicar Próximo: {e}")
                    next_btn.evaluate("el => el.click()")
            else:
                logger.info("Nem botão de Publicação nem botão de Próximo visíveis. Wizard pode ter terminado.")
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
            
            # Atualiza o screenshot a cada 2.5 minutos (5 ciclos) para mostrar progresso
            if ciclo % 5 == 0:
                salvar_screenshot(page, "06_upload_progress", tarefa_id)
            
            # Estratégia 1: Redirecionamento para a página final do vídeo
            if url_atual != url_inicial and "/upload" not in url_atual:
                logger.info(f"[PASSO 6] Redirecionado para {url_atual} — upload concluído!")
                upload_ok = True
                break
                
            # Estratégia 2: Detecta o badge "Published" na seção "Enviando Atualmente"
            # O badge é um div/span com fundo vermelho — NÃO necessariamente um <button>
            # Usamos seletor de texto simples que cobre todos os elementos HTML possíveis
            if "/$/uploads" in url_atual:
                try:
                    # Tenta encontrar o texto "Published" em QUALQUER elemento dentro da seção de upload ativo.
                    # A seção tem o heading "Enviando Atualmente" — buscamos within dessa seção.
                    # Fallback: se não achar scoped, busca globalmente mas só na área acima da lista (primeiro match)
                    published_count = page.locator(':has-text("Published")').filter(has_text='Published').count()
                    # Garantimos que não seja apenas o título do vídeo que contenha a palavra
                    # O badge "Published" aparece como texto exato/isolado num elemento
                    published_exact = page.locator('text=/^Published$/').count()
                    if published_exact > 0 and ciclo > 0:
                        logger.info(f"[PASSO 6] Badge 'Published' detectado ({published_exact}x) — upload concluído!")
                        salvar_screenshot(page, "06_published_detected", tarefa_id)
                        upload_ok = True
                        break
                except Exception as e:
                    logger.debug(f"[PASSO 6] Erro ao buscar badge Published: {e}")
                    
                try:
                    # Estratégia 2b: Conta elementos com texto exato "Confirmando"
                    # Quando some = acabou a fase de confirmação do blockchain
                    confirmando_count = page.locator('text=/^Confirmando/').count()
                    if confirmando_count == 0 and ciclo > 5:
                        logger.info("[PASSO 6] Nenhum 'Confirmando' visível — upload concluído!")
                        salvar_screenshot(page, "06_confirmando_gone", tarefa_id)
                        upload_ok = True
                        break
                except Exception as e:
                    logger.warning(f"[PASSO 6] Erro ao buscar indicador Confirmando: {e}")
            
            # Estratégia 3: API LBRY (URL correta: lbry://@canal/slug)
            if ciclo > 5 and ciclo % 5 == 0:  # A cada 2.5 minutos
                try:
                    channel_name = tarefa.get('odysee_channel_name', '').lstrip('@')
                    video_slug = tarefa.get('odysee_slug', '')
                    # Formato correto da LBRY: lbry://@canal/slug
                    lbry_url = f"lbry://@{channel_name}/{video_slug}"
                    api_url = "https://api.na-backend.odysee.com/api/v1/proxy?m=resolve"
                    payload = {"jsonrpc": "2.0", "method": "resolve", "params": {"urls": [lbry_url]}}
                    res = requests.post(api_url, json=payload, timeout=10)
                    if res.status_code == 200:
                        data = res.json()
                        result = data.get("result", {})
                        # O resolve retorna um dict com a URL como chave; se não tiver "error" na entrada, está publicado
                        entry = result.get(lbry_url, {})
                        if entry and "error" not in entry:
                            logger.info(f"[PASSO 6] Vídeo confirmado pela API LBRY ({lbry_url}) — concluído!")
                            upload_ok = True
                            break
                        else:
                            logger.debug(f"[PASSO 6] API LBRY ainda não encontrou o claim.")
                except Exception as e:
                    logger.debug(f"Erro ao checar API LBRY: {e}")
                    
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
        # Só busca idiomas que já têm canal e token do Odysee configurados
        # Idiomas sem token são silenciosamente ignorados (sem criar fila ou erro)
        cursor.execute("SELECT id, name FROM languages WHERE odysee_auth_token IS NOT NULL AND odysee_auth_token != '' AND odysee_channel_name IS NOT NULL AND odysee_channel_name != '' AND odysee_auto_enabled = 1")
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
            for idioma in idiomas:
                if idioma['name'] in file_name:
                    language_id = idioma['id']
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
            date_match = _re.search(r'(\d{4})[/\-](\d{2})[/\-](\d{2})', file_name)
            if date_match:
                slug = f"{date_match.group(1)}_{date_match.group(2)}_{date_match.group(3)}"
            else:
                slug = titulo_limpo  # fallback
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
    api_url = f"https://is.gd/create.php?format=simple&url={urllib.parse.quote(url_longa)}"
    res = requests.get(api_url)
    if res.status_code == 200:
        return res.text
    return url_longa

def processar_fila():
    # 1. Escanear o Drive por novos arquivos
    escanear_drive()
    
    # 2. Processar a fila existente
    tarefa = buscar_proxima_tarefa()
    if not tarefa:
        return
        
    logger.info(f"Processando tarefa: {tarefa['titulo_final']}")
    atualizar_status(tarefa['id'], 'processing')
    
    temp_path = None
    try:
        drive_service = init_drive_service()
        
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
                
                # Lê a imagem
                img = cv2.imread(thumb_path)
                if img is not None:
                    # Redimensiona para 320x180 (tamanho reduzido que o WhatsApp prefere para thumbs em base64)
                    new_dim = (320, 180)
                    img = cv2.resize(img, new_dim, interpolation=cv2.INTER_AREA)
                    
                    # Codifica como JPEG em memória com 80% de qualidade
                    encode_param = [int(cv2.IMWRITE_JPEG_QUALITY), 80]
                    result, encimg = cv2.imencode('.jpg', img, encode_param)
                    if result:
                        thumbnail_b64 = base64.b64encode(encimg).decode('utf-8')
            except Exception as e:
                logger.warning(f"Não foi possível processar o thumbnail para base64: {e}")
                
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
                
            mensagem = template.replace('{titulo}', title_msg).replace('{link}', url_curta).replace('{idioma}', idioma_nome).replace('{bandeira}', bandeira)
            
            link_preview_data = {
                "title": title_msg,
                "body": "Disponível agora no Odysee",
                "url": url_curta
            }
            if thumbnail_b64:
                link_preview_data["thumbnailBase64"] = thumbnail_b64
                
            for grupo_id in grupos_alvo:
                try:
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

if __name__ == "__main__":
    print("Iniciando Worker...", flush=True)
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
