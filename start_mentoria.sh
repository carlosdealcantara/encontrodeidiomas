#!/bin/bash
sudo docker stop mentoria-worker || true
sudo docker rm mentoria-worker || true
sudo docker run -d --name mentoria-worker \
  --add-host=host.docker.internal:host-gateway \
  -v /home/ubuntu/encontrodeidiomas/google_service_account.json:/app/google_service_account.json \
  -e DB_HOST=77.37.127.146 \
  -e DB_NAME=u879045076_central \
  -e DB_USER=u879045076_carlos \
  -e DB_PASS="#Nadier38" \
  -e DRIVE_MENTORIA_FOLDER_ID=1-iW8FuL4UhyfjRQyZ1zdtCiAVVtcLLAU \
  -e DRIVE_MENTORIA_ARCHIVE_FOLDER_ID=11mn7mlIJCCsnCwLP_Ucbmj56fUevjnnj \
  -e MENTORIA_ODYSEE_LANGUAGE_ID=1 \
  --entrypoint python \
  odysee-worker -u mentoria_worker.py
