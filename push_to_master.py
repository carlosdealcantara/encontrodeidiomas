import subprocess
import os
import sys
import time

def run_command(command, show_output=True):
    print(f"Executando: {command}")
    try:
        result = subprocess.run(command, shell=True, check=True, capture_output=True, text=True)
        if show_output and result.stdout:
            print(f"Saída: {result.stdout}")
        return True, result.stdout
    except subprocess.CalledProcessError as e:
        print(f"Erro: {e}")
        if e.stderr:
            print(f"Saída de erro: {e.stderr}")
        return False, e.stderr

def main():
    print("=== ENVIANDO TODOS OS ARQUIVOS PARA O BRANCH MASTER ===\n")
    
    # Obter o diretório atual do script
    current_dir = os.path.dirname(os.path.abspath(__file__))
    os.chdir(current_dir)
    
    # Verificar o branch atual
    print("Verificando o branch atual...")
    success, branch_output = run_command("git branch")
    
    # Identificar o branch atual
    current_branch = None
    for line in branch_output.splitlines():
        if line.startswith('*'):
            current_branch = line.strip('*').strip()
            break
    
    print(f"Branch atual: {current_branch}")
    
    # Mudar para o branch master
    if current_branch != "master":
        print("Mudando para o branch master...")
        success, _ = run_command("git checkout master")
        if not success:
            print("Branch master não existe localmente, criando...")
            success, _ = run_command("git checkout -b master")
            if not success:
                print("Falha ao criar o branch master. Encerrando.")
                return
    
    # Verificar se existe branch main para mesclar as alterações
    if "main" in branch_output:
        print("Branch main encontrado, mesclando alterações...")
        success, _ = run_command("git merge main -m \"Mesclando alterações do main para master\"")
        if not success:
            print("Aviso: Falha ao mesclar alterações do branch main.")
    
    # Adicionar todos os arquivos
    print("Adicionando todos os arquivos...")
    success, _ = run_command("git add -A")
    if not success:
        print("Falha ao adicionar arquivos. Encerrando.")
        return
    
    # Fazer commit
    print("Fazendo commit das alterações...")
    success, commit_output = run_command("git commit -m \"Atualizando todos os arquivos para a versão mais recente\"")
    if "nothing to commit" in commit_output:
        print("Não há alterações para commit.")
    elif not success:
        print("Falha ao fazer commit. Encerrando.")
        return
    
    # Enviar para o GitHub
    print("Enviando para o GitHub (branch master)...")
    success, _ = run_command("git push -u origin master")
    if not success:
        print("Falha ao enviar para o GitHub. Verifique suas credenciais.")
        return
    
    print("\n=== PROCESSO CONCLUÍDO ===")
    print("O site deve ser atualizado em alguns minutos.")
    print("Acesse https://encontrodeidiomas.com.br para verificar.")
    
    input("\nPressione Enter para sair...")

if __name__ == "__main__":
    main() 