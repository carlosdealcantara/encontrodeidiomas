import os
import time
import urllib.parse
import logging
import requests
import mysql.connector
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
    temp_path = f"/tmp/{file_name}"
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

def publicar_odysee_playwright(auth_token, title, file_path):
    logger.info("Iniciando publicação no Odysee via Playwright")
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context()
        page = context.new_page()
        
        # Injeta o auth_token no localStorage acessando o site primeiro
        page.goto("https://odysee.com")
        page.evaluate(f"window.localStorage.setItem('auth_token', '{auth_token}')")
        
        # Agora vamos para a página de upload
        page.goto("https://odysee.com/$/upload")
        page.wait_for_load_state("networkidle")
        
        # Preenche o arquivo
        page.locator('input[type="file"]').set_input_files(file_path)
        
        # Preenche o título
        page.fill('input[name="content_title"]', title)
        
        # Define um bid minúsculo
        try:
            page.fill('input[name="content_bid"]', "0.001")
        except:
            pass # Pode estar escondido nas opções avançadas
            
        # Clica em Upload / Publish
        page.click('button:has-text("Upload")')
        
        # Espera a confirmação e a URL
        page.wait_for_selector('text=Upload complete', timeout=600000) # 10 min timeout para upload
        
        # Pega a URL gerada (frequentemente aparece após o upload)
        # Se não for possível pegar facilmente do popup, nós deduzimos a URL
        browser.close()
        return True

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
        cursor.execute("SELECT id, name FROM languages")
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
                
            # Formata o título preliminar
            # Original: 🇺🇲 Encontro Online de Inglês - 2026/06/16 20:06 GMT-03:00 - Recording.mp4
            titulo_limpo = file_name.split(" GMT")[0] if " GMT" in file_name else file_name.replace(" - Recording", "").replace(".mp4", "")
            
            # Insere no banco como waiting_host
            cursor.execute("""
                INSERT INTO odysee_publish_queue 
                (language_id, drive_file_id, drive_file_name, status, titulo_final) 
                VALUES (%s, %s, %s, 'waiting_host', %s)
            """, (language_id, file_id, file_name, titulo_limpo))
            conn.commit()
            logger.info(f"Novo vídeo adicionado à fila de triagem: {file_name}")
            
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
            
        publicar_odysee_playwright(tarefa['odysee_auth_token'], tarefa['titulo_final'], temp_path)
        
        # Odysee final URL (Canonica)
        odysee_url = f"https://odysee.com/{tarefa['odysee_channel_name']}/{tarefa['odysee_slug']}"
        
        # Encurtar a URL via TinyURL!
        url_curta = encurtar_url(odysee_url)
        
        mover_video_e_apagar_chat(drive_service, tarefa['drive_file_id'], tarefa['drive_file_name'], tarefa['language_name'])
        
        atualizar_status(tarefa['id'], 'done', odysee_url=url_curta)
        
        if tarefa['whatsapp_group_id']:
            mensagem = f"🎬 *Gravação do Encontro publicada!*\n\n📌 {tarefa['titulo_final']}\n\n🔗 {url_curta}"
            requests.post("http://baileys-server:3000/send", json={
                "to": tarefa['whatsapp_group_id'],
                "message": mensagem,
                "source": "odysee_pipeline"
            }, headers={"apikey": "SenhaMeetups2026"})
            
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
