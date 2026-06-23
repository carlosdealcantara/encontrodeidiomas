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
            SELECT q.*, l.odysee_auth_token, l.odysee_channel_name, l.whatsapp_group_id, l.name as language_name
            FROM odysee_publish_queue q
            JOIN languages l ON q.language_id = l.id
            WHERE q.status = "pending"
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
                cursor.execute("UPDATE meetup_replays SET link = %s WHERE language_id = %s AND (link IS NULL OR link = '') ORDER BY id DESC LIMIT 1", (odysee_url, lang_id))
            
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
    results = drive_service.files().list(
        q=f"'{PASTA_RAIZ_DRIVE}' in parents and mimeType='application/vnd.google-apps.folder' and name contains '{language_name}'",
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
            # O seletor '.publish-form__actions button' aponta especificamente para o botão de submit
            publish_btn = page.locator('.button--primary >> text="Publicação"').first
            if not publish_btn.is_visible():
                # Fallback: botão primário no fim do formulário que não seja de navegação
                publish_btn = page.locator('form button.button--primary:has-text("Publicação"), .publish__actions button.button--primary').first
            
            if publish_btn.is_visible():
                logger.info("Botão FINAL de Publicação encontrado no rodapé! Clicando...")
                try:
                    publish_btn.click(timeout=300000)
                except Exception as e:
                    logger.warning(f"Erro ao clicar Publicação: {e}")
                    publish_btn.evaluate("el => el.click()")
                break
            
            # Se não achou o Publicação, clica em Próximo (aguarda até 5 min por ele estar habilitado)
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
            
        salvar_screenshot(page, "06_after_upload_click", tarefa_id)
        
        # PASSO 6: Aguardar conclusão — detecta mudança de URL (Odysee redireciona ao terminar)
        logger.info("[PASSO 6] Aguardando upload terminar (máx 4h)...")
        url_inicial = page.url
        upload_ok = False
        for _ in range(480):  # 480 x 30s = 4h
            page.wait_for_timeout(30000)
            url_atual = page.url
            titulo_atual = page.title()
            logger.info(f"[PASSO 6] URL: {url_atual} | Título: {titulo_atual}")
            
            # Odysee redireciona para a página do vídeo ao concluir
            if url_atual != url_inicial and "/upload" not in url_atual:
                logger.info(f"[PASSO 6] Redirecionado para {url_atual} — upload concluído!")
                upload_ok = True
                break
            
            # Textos de sucesso em português e inglês
            for texto in ["Upload complete", "concluído", "publicado", "published", "Seu vídeo", "foi publicado"]:
                if page.locator(f'text={texto}').count() > 0:
                    logger.info(f"[PASSO 6] Texto de sucesso detectado: '{texto}'")
                    upload_ok = True
                    break
            if upload_ok:
                break
        
        salvar_screenshot(page, "07_upload_complete", tarefa_id)
        if upload_ok:
            logger.info("[PASSO 6] Upload concluído com sucesso!")
        else:
            logger.warning("[PASSO 6] Timeout de 4h atingido. O upload pode ter sido concluído mesmo assim.")
        
        browser.close()
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
        cursor.execute("SELECT id, name FROM languages WHERE odysee_auth_token IS NOT NULL AND odysee_auth_token != '' AND odysee_channel_name IS NOT NULL AND odysee_channel_name != ''")
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
            
            # Insere no banco como waiting_host
            cursor.execute("""
                INSERT INTO odysee_publish_queue 
                (language_id, drive_file_id, drive_file_name, status, titulo_final, odysee_slug) 
                VALUES (%s, %s, %s, 'waiting_host', %s, %s)
            """, (language_id, file_id, file_name, titulo_limpo, slug))
            conn.commit()
            logger.info(f"Novo vídeo adicionado à fila de triagem: {file_name} | Título: {titulo_limpo} | Slug: {slug}")
            
        cursor.close()
        conn.close()
    except Exception as e:
        logger.error(f"Erro ao escanear Drive: {e}")

def encurtar_url(url_longa):
    api_url = f"http://tinyurl.com/api-create.php?url={urllib.parse.quote(url_longa)}"
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
        publicar_odysee_playwright(tarefa['id'], tarefa['odysee_auth_token'], title, temp_path, slug=tarefa.get('odysee_slug'))
        
        # Odysee final URL (Canonica)
        odysee_url = f"https://odysee.com/{tarefa['odysee_channel_name']}/{tarefa['odysee_slug']}"
        
        # Encurtar a URL via TinyURL!
        url_curta = encurtar_url(odysee_url)
        
        mover_video_e_apagar_chat(drive_service, tarefa['drive_file_id'], tarefa['drive_file_name'], tarefa['language_name'])
        
        atualizar_status(tarefa['id'], 'done', odysee_url=url_curta)
        
        if tarefa.get('whatsapp_group_id'):
            title_msg = tarefa.get('titulo_final') or tarefa.get('topico') or tarefa.get('drive_file_name', '')
            mensagem = f"⚬️ *Gravação do Encontro publicada!*\n\n📌 {title_msg}\n\n🔗 {url_curta}"
            try:
                requests.post("http://baileys-server:3000/send", json={
                    "to": tarefa['whatsapp_group_id'],
                    "message": mensagem,
                    "source": "odysee_pipeline"
                }, headers={"apikey": "SenhaMeetups2026"}, timeout=15)
                logger.info(f"[WHATSAPP] Mensagem enviada para o grupo {tarefa['whatsapp_group_id']}")
            except Exception as e:
                logger.warning(f"[WHATSAPP] Falhou ao enviar mensagem: {e}")
            
            # Também notifica o grupo dos hosts com o resumo da semana
            try:
                requests.post("http://baileys-server:3000/send", json={
                    "to": "120363164732845564@g.us",
                    "message": f"🤖 *Worker Odysee publicou automaticamente!*\n\n{mensagem}\n\n✅ URL canonica: https://odysee.com/{tarefa.get('odysee_channel_name', '')}/{tarefa.get('odysee_slug', '')}",
                    "source": "odysee_pipeline"
                }, headers={"apikey": "SenhaMeetups2026"}, timeout=15)
                logger.info("[WHATSAPP] Notificação enviada ao grupo dos hosts")
            except Exception as e:
                logger.warning(f"[WHATSAPP] Falhou ao notificar hosts: {e}")
            
    except Exception as e:
        logger.error(f"Erro: {e}")
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
