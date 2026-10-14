import sys
sys.path.append('/app')
from worker import init_drive_service

drive = init_drive_service()
folder = drive.files().get(fileId='1386OoJWGrfh9rQTrf6w0hiuDD3XcBsjI', fields='id, name, parents').execute()
print("DRIVE_RECORDINGS_FOLDER_ID:", folder)
