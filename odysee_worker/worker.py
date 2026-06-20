import os
import time
import json
import logging
import requests
import mysql.connector
from dotenv import load_dotenv
from google.oauth2 import service_account
from googleapiclient.discovery import build
from googleapiclient.http import MediaIoBaseDownload

# Configuração de Logs
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

# Carrega Variáveis
load_dotenv()

DB_HOST = os.getenv('DB_HOST', 'localhost')
DB_USER = os.getenv('DB_USER', '')
DB_PASS = os.getenv('DB_PASS', '')
DB_NAME = os.getenv('DB_NAME', '')

GOOGLE_SA_JSON = os.getenv('GOOGLE_SERVICE_ACCOUNT_JSON')
PASTA_RAIZ_DRIVE = os.getenv('DRIVE_RECORDINGS_FOLDER_ID') # Onde o Odysee pega e onde caem as gravações

LBRY_RPC_URL = os.getenv('LBRY_RPC_URL', 'http://localhost:5279')

def get_db_connection():
    return mysql.connector.connect(
        host=DB_HOST,
        user=DB_USER,
        password=DB_PASS,
        database=DB_NAME
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
            SELECT q.*, l.odysee_channel_id, l.odysee_channel_name, l.whatsapp_group_id, l.name as language_name
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
    logger.info(f"Baixando vídeo do Drive: {file_id}")
    request = drive_service.files().get_media(fileId=file_id)
    temp_path = f"/tmp/{file_name}"
    
    with open(temp_path, "wb") as f:
        downloader = MediaIoBaseDownload(f, request)
        done = False
        while done is False:
            status, done = downloader.next_chunk()
            if status:
                logger.info(f"Download progresso: {int(status.progress() * 100)}%")
                
    return temp_path

def mover_video_e_apagar_chat(drive_service, file_id, file_name, language_name):
    # Encontrar a subpasta do idioma dentro da raiz
    results = drive_service.files().list(
        q=f"'{PASTA_RAIZ_DRIVE}' in parents and mimeType='application/vnd.google-apps.folder' and name contains '{language_name}'",
        fields="files(id, name)"
    ).execute()
    
    pastas = results.get('files', [])
    if pastas:
        pasta_idioma_id = pastas[0]['id']
        logger.info(f"Movendo {file_id} para a pasta {pasta_idioma_id} ({language_name})")
        # Move o arquivo
        file = drive_service.files().get(fileId=file_id, fields='parents').execute()
        previous_parents = ",".join(file.get('parents'))
        drive_service.files().update(
            fileId=file_id,
            addParents=pasta_idioma_id,
            removeParents=previous_parents
        ).execute()
    else:
        logger.warning(f"Pasta para o idioma '{language_name}' não encontrada no Drive. Arquivo mantido na raiz.")
        
    # Apagar o chat (buscando arquivo com mesmo prefixo mas terminando em - Chat)
    prefixo_busca = file_name.replace(" - Recording", "").replace(".mp4", "")
    chats = drive_service.files().list(
        q=f"'{PASTA_RAIZ_DRIVE}' in parents and name contains '{prefixo_busca}' and name contains '- Chat'",
        fields="files(id, name)"
    ).execute()
    
    for chat in chats.get('files', []):
        logger.info(f"Apagando chat: {chat['name']}")
        drive_service.files().delete(fileId=chat['id']).execute()

def publicar_odysee(slug, title, channel_id, temp_path):
    logger.info(f"Iniciando publicação LBRY: {slug}")
    payload = {
        "method": "publish",
        "params": {
            "name": slug,
            "title": title,
            "description": "Encontro Online de Idiomas\nhttps://encontrodeidiomas.com.br",
            "bid": "0.001",
            "file_path": temp_path,
            "channel_id": channel_id,
            "tags": ["idiomas", "educação"],
            "languages": ["pt"]
        }
    }
    
    response = requests.post(LBRY_RPC_URL, json=payload)
    data = response.json()
    
    if "error" in data:
        raise Exception(f"Erro LBRY RPC: {data['error']}")
        
    return data['result']

def notificar_baileys(group_id, titulo, odysee_url):
    logger.info("Enviando notificação no WhatsApp via Baileys")
    mensagem = f"🎬 *Gravação do Encontro publicada!*\n\n📌 {titulo}\n\n🔗 {odysee_url}"
    try:
        # A API do Baileys está no mesmo localhost, porta 3000
        requests.post("http://baileys-server:3000/send", json={
            "to": group_id,
            "message": mensagem,
            "source": "odysee_pipeline"
        }, headers={"apikey": "SenhaMeetups2026"})
    except Exception as e:
        logger.error(f"Erro ao notificar Baileys: {e}")

def processar_fila():
    tarefa = buscar_proxima_tarefa()
    if not tarefa:
        return
        
    logger.info(f"Processando tarefa ID: {tarefa['id']}")
    atualizar_status(tarefa['id'], 'processing')
    
    temp_path = None
    try:
        drive_service = init_drive_service()
        
        # 1. Download
        temp_path = baixar_video_drive(drive_service, tarefa['drive_file_id'], tarefa['drive_file_name'])
        
        # 2. Upload Odysee
        if not tarefa['odysee_channel_id']:
            raise Exception("Canal do Odysee não configurado para este idioma")
            
        publicacao = publicar_odysee(
            tarefa['odysee_slug'], 
            tarefa['titulo_final'], 
            tarefa['odysee_channel_id'], 
            temp_path
        )
        
        # Odysee URL final (claim URL / Canonical URL)
        odysee_url = f"https://odysee.com/{tarefa['odysee_channel_name']}/{tarefa['odysee_slug']}"
        
        # 3. Limpeza Drive
        mover_video_e_apagar_chat(drive_service, tarefa['drive_file_id'], tarefa['drive_file_name'], tarefa['language_name'])
        
        # 4. Finalização
        atualizar_status(tarefa['id'], 'done', odysee_url=odysee_url)
        
        if tarefa['whatsapp_group_id']:
            notificar_baileys(tarefa['whatsapp_group_id'], tarefa['titulo_final'], odysee_url)
            
        logger.info(f"Tarefa {tarefa['id']} concluída com sucesso!")
        
    except Exception as e:
        logger.error(f"Erro na tarefa {tarefa['id']}: {str(e)}")
        retry = tarefa['retry_count'] + 1
        novo_status = 'error' if retry >= 3 else 'pending'
        atualizar_status(tarefa['id'], novo_status, error_msg=str(e), retry_count=retry)
        
    finally:
        if temp_path and os.path.exists(temp_path):
            os.remove(temp_path)
            logger.info(f"Arquivo temporário {temp_path} removido.")

if __name__ == "__main__":
    logger.info("Worker Odysee Pipeline iniciado. Aguardando tarefas...")
    while True:
        try:
            processar_fila()
        except Exception as e:
            logger.error(f"Erro crítico no loop principal: {e}")
        time.sleep(60) # Verifica a fila a cada 1 minuto
