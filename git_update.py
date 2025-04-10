import subprocess
import os

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

# Navegar para o diretório do projeto
os.chdir("C:\\encontrodeidiomas")

# Adicionar todos os arquivos
if run_command("git add -A"):
    print("Arquivos adicionados com sucesso.")
else:
    print("Falha ao adicionar arquivos.")
    exit(1)

# Fazer commit
if run_command('git commit -m "Adicionado link para página online em todos os menus de navegação"'):
    print("Commit realizado com sucesso.")
else:
    print("Falha ao realizar commit.")
    exit(1)

# Enviar para o GitHub
if run_command("git push origin main"):
    print("Push realizado com sucesso.")
else:
    print("Falha ao realizar push.")
    exit(1)

print("Processo concluído com sucesso. Todos os arquivos foram enviados para o GitHub.") 