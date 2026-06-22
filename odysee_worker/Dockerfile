FROM mcr.microsoft.com/playwright/python:v1.44.0-jammy

WORKDIR /app

# Instala dependências
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Playwright browsers já estão na imagem base, mas garantimos que as dependências do chromium estejam prontas
RUN playwright install chromium

COPY . .

CMD ["python", "-u", "worker.py"]
