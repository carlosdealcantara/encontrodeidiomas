import os
import sys
import mysql.connector

# Adiciona o diretório atual ao path para importar o worker
sys.path.append('/app')
import worker

def run_organize():
    drive_service = worker.init_drive_service()
    
    # Busca arquivos na raiz do Drive
    results = drive_service.files().list(
        q=f"'{worker.PASTA_RAIZ_DRIVE}' in parents",
        fields="files(id, name, mimeType)"
    ).execute()
    arquivos = results.get('files', [])
    
    if not arquivos:
        print("Nenhum arquivo na raiz.")
        return

    conn = worker.get_db_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT id, name, odysee_auth_token, odysee_channel_name, odysee_auto_enabled FROM languages")
    idiomas = cursor.fetchall()
    
    for arquivo in arquivos:
        file_id = arquivo['id']
        file_name = arquivo['name']
        mime = arquivo['mimeType']
        
        # Ignora pastas
        if 'folder' in mime:
            continue
            
        # Pega o idioma
        idioma_escolhido = None
        file_name_norm = worker.normalize_text(file_name)
        for idioma in idiomas:
            if worker.normalize_text(idioma['name']) in file_name_norm:
                idioma_escolhido = idioma
                break
                
        if not idioma_escolhido:
            print(f"Idioma não identificado para o arquivo solto: {file_name}")
            continue
            
        print(f"Processando arquivo esquecido: {file_name} ({idioma_escolhido['name']})")
        
        # Se for vídeo, movemos o vídeo para a pasta do idioma e o chat para transcrições.
        if 'video' in mime and 'Recording' in file_name:
            # Verifica se o idioma tem canal para sabermos se movemos o vídeo ou só o chat
            has_odysee = bool(idioma_escolhido.get('odysee_auth_token')) and bool(idioma_escolhido.get('odysee_channel_name'))
            # Move o vídeo (se tiver canal) e move o chat (sempre)
            worker.mover_video_e_apagar_chat(drive_service, file_id, file_name, idioma_escolhido['name'], move_video=has_odysee)

if __name__ == "__main__":
    run_organize()
    print("Concluído!")
