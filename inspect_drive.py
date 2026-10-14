import sys
sys.path.append('/app')
from mentoria_worker import init_drive_service, DRIVE_MENTORIA_FOLDER_ID

drive = init_drive_service()

# List recent files created/modified in Google Drive folder
query = f"'{DRIVE_MENTORIA_FOLDER_ID}' in parents and trashed = false"
print(f"Buscando arquivos na pasta {DRIVE_MENTORIA_FOLDER_ID}...")

results = drive.files().list(
    q=query,
    pageSize=30,
    orderBy='createdTime desc',
    fields="files(id, name, createdTime, modifiedTime, mimeType, trashed)"
).execute()

files = results.get('files', [])
print(f"Total encontrados: {len(files)}")
for f in files[:25]:
    print(f"Nome: {f['name']} | ID: {f['id']} | Criado: {f.get('createdTime')} | Modificado: {f.get('modifiedTime')} | Mime: {f.get('mimeType')}")
