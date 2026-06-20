FROM python:3.11-slim

WORKDIR /app

# Instala dependências de SO necessárias para compilações se precisar
RUN apt-get update && apt-get install -y --no-install-recommends \
    gcc \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

# Roda o worker (no worker.py teremos o loop de polling)
CMD ["python", "-u", "worker.py"]
