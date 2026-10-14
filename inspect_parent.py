import sys
sys.path.append('/app')
from mentoria_worker import init_drive_service

drive = init_drive_service()
folder = drive.files().get(fileId='1h5PO2Sj5ZYuEo9VUmTfngs0EZWdvt9rT', fields='id, name, parents').execute()
print("Pasta do arquivo:", folder)

if 'parents' in folder:
    for p in folder['parents']:
        pf = drive.files().get(fileId=p, fields='id, name, parents').execute()
        print("Pai da pasta:", pf)
