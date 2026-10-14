import sys
sys.path.append('/app')
from mentoria_worker import init_drive_service

drive = init_drive_service()

# Search across all Drive files accessible
print("Buscando gravações de 29 ou 30 de setembro de 2026...")
query = "mimeType contains 'video/' and trashed=false and (name contains '2026/09/30' or name contains '2026-09-30' or name contains '2026/09/29' or name contains '2026-09-29')"
results = drive.files().list(
    q=query,
    pageSize=30,
    fields="files(id, name, createdTime, modifiedTime, parents)"
).execute()

files = results.get('files', [])
print(f"Total encontrados: {len(files)}")
for f in files:
    print(f"Nome: {f['name']} | ID: {f['id']} | Criado: {f.get('createdTime')} | Parents: {f.get('parents')}")
