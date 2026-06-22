FROM mcr.microsoft.com/playwright/python:v1.44.0-jammy

WORKDIR /app

# Instala dependências
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Patchright e instalação do Chrome real
RUN pip install patchright
RUN patchright install chrome

COPY . .

CMD ["python", "-u", "worker.py"]
