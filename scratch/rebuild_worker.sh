#!/bin/bash
# Rebuilds the odysee-worker image and recreates the container
set -e

echo "=== Parando container antigo ==="
docker stop odysee-worker 2>/dev/null || true
docker rm odysee-worker 2>/dev/null || true

echo "=== Reconstruindo a imagem com o código atual ==="
cd /home/ubuntu/encontrodeidiomas/odysee_worker
docker build -t odysee-worker-img .

echo "=== Recriando o container ==="
docker run -d \
  --name odysee-worker \
  --restart unless-stopped \
  --network host \
  -v /home/ubuntu/encontrodeidiomas/google_service_account.json:/app/google_service_account.json \
  -v /home/ubuntu/encontrodeidiomas/baileys-server/data/screenshots:/app/screenshots \
  -e DB_HOST=77.37.127.146 \
  -e DB_NAME=u879045076_central \
  -e DB_USER=u879045076_carlos \
  -e "DB_PASS=#Nadier38" \
  -e DRIVE_RECORDINGS_FOLDER_ID=1386OoJWGrfh9rQTrf6w0hiuDD3XcBsjI \
  odysee-worker-img

echo "=== Aguardando inicialização... ==="
sleep 8
echo "=== Logs iniciais: ==="
docker logs --tail 30 odysee-worker
