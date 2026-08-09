FROM python:3.11-slim

WORKDIR /app

# Instala dependências do sistema para o Playwright e ffmpeg (processamento de áudio)
RUN apt-get update && apt-get install -y \
    wget gnupg ca-certificates \
    libglib2.0-0 libnss3 libnspr4 libdbus-1-3 libatk1.0-0 \
    libatk-bridge2.0-0 libcups2 libdrm2 libxkbcommon0 libxcomposite1 \
    libxdamage1 libxfixes3 libxrandr2 libgbm1 libasound2 \
    ffmpeg \
    libgl1 libglib2.0-0 \
    --no-install-recommends && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Instala só o Chromium (mais leve que o Chrome completo)
RUN playwright install chromium

COPY . .

# Healthcheck: falha se o arquivo de heartbeat não foi modificado nos últimos 15 minutos
HEALTHCHECK --interval=5m --timeout=10s --start-period=60s --retries=3 \
  CMD find /tmp/worker_heartbeat.txt -mmin -15 | grep -q . || exit 1

CMD ["python", "-u", "worker.py"]
