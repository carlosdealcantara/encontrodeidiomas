import sys
sys.path.append('/app')
from mentoria_worker import init_drive_service

drive = init_drive_service()

# List subfolders inside Google Meet (1RTfxQKTt9ySolLWXpb8jukuu3xSrLKXn)
query = "'1RTfxQKTt9ySolLWXpb8jukuu3xSrLKXn' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false"
folders = drive.files().list(q=query, fields='files(id, name)').execute().get('files', [])
print("Subpastas dentro de 'Google Meet':")
for f in folders:
    print(f"ID: {f['id']} | Nome: {f['name']}")

# And list in 1-iW8FuL4UhyfjRQyZ1zdtCiAVVtcLLAU (Meet Recordings)
query2 = "'1-iW8FuL4UhyfjRQyZ1zdtCiAVVtcLLAU' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false"
folders2 = drive.files().list(q=query2, fields='files(id, name)').execute().get('files', [])
print("\nSubpastas dentro de 'Meet Recordings':")
for f in folders2:
    print(f"ID: {f['id']} | Nome: {f['name']}")
