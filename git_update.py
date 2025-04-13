import subprocess
import os
import sys

def run_command(command):
    print(f"Executando: {command}")
    try:
        result = subprocess.run(command, shell=True, check=True, capture_output=True, text=True)
        print(f"Saída: {result.stdout}")
        return True
    except subprocess.CalledProcessError as e:
        print(f"Erro: {e}")
        print(f"Saída de erro: {e.stderr}")
        return False

# Obter o diretório atual do script
current_dir = os.path.dirname(os.path.abspath(__file__))
os.chdir(current_dir)

# Verificar se o repositório já está configurado
remote_config = subprocess.run("git remote -v", shell=True, capture_output=True, text=True)
if not remote_config.stdout.strip():
    print("Configurando repositório remoto...")
    # Substitua a URL abaixo pela URL correta do seu repositório GitHub
    remote_url = "https://github.com/username/encontrodeidiomas.git"
    user_input = input(f"Digite a URL do repositório GitHub (ou pressione Enter para usar {remote_url}): ")
    if user_input.strip():
        remote_url = user_input
    
    if not run_command(f"git remote add origin {remote_url}"):
        print("Falha ao configurar repositório remoto.")
        exit(1)

# Adicionar todos os arquivos
if run_command("git add -A"):
    print("Arquivos adicionados com sucesso.")
else:
    print("Falha ao adicionar arquivos.")
    exit(1)

# Fazer commit
commit_message = "Atualização do site com correções e adição do diretório dlaurojoias"
if run_command(f'git commit -m "{commit_message}"'):
    print("Commit realizado com sucesso.")
else:
    print("Falha ao realizar commit.")
    exit(1)

# Enviar para o GitHub
if run_command("git push -u origin main"):
    print("Push realizado com sucesso.")
else:
    print("Tentando enviar para o branch master...")
    if run_command("git push -u origin master"):
        print("Push realizado com sucesso para o branch master.")
    else:
        print("Falha ao realizar push. Verifique suas credenciais e tente novamente.")
        exit(1)

print("Processo concluído com sucesso. Todos os arquivos foram enviados para o GitHub.") 