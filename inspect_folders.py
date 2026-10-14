import sys
sys.path.append('/app')
from mentoria_worker import init_drive_service

drive = init_drive_service()

for fid in ['1-iW8FuL4UhyfjRQyZ1zdtCiAVVtcLLAU', '11mn7mlIJCCsnCwLP_Ucbmj56fUevjnnj', '1h5PO2Sj5ZYuEo9VUmTfngs0EZWdvt9rT', '1RTfxQKTt9ySolLWXpb8jukuu3xSrLKXn']:
    try:
        f = drive.files().get(fileId=fid, fields='id, name, parents').execute()
        print(f"ID: {fid} -> Name: '{f.get('name')}', Parents: {f.get('parents')}")
    except Exception as e:
        print(f"ID: {fid} -> Erro: {e}")
