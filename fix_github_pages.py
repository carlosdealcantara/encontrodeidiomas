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
        print(f"Saída de erro: {e.stderr}")
        return False, e.stderr

def main():
    # Obter o diretório atual do script
    current_dir = os.path.dirname(os.path.abspath(__file__))
    os.chdir(current_dir)
    
    print("=== Verificando e corrigindo o repositório do GitHub Pages ===")
    
    # 1. Verificar se a pasta dlaurojoias existe localmente
    if not os.path.exists("dlaurojoias"):
        print("ERRO: A pasta dlaurojoias não existe no diretório atual!")
        return
    
    # 2. Verificar quais branches existem
    success, output = run_command("git branch")
    if not success:
        print("Falha ao verificar os branches.")
        return
    
    # 3. Verificar qual branch está ativo
    current_branch = None
    for line in output.splitlines():
        if line.startswith("*"):
            current_branch = line.strip("* ")
            break
    
    print(f"Branch atual: {current_branch}")
    
    # 4. Verificar se o repositório remoto está configurado
    success, remote_output = run_command("git remote -v")
    if not success or not remote_output.strip():
        print("Configurando repositório remoto...")
        remote_url = input("Digite a URL do repositório GitHub: ")
        if not remote_url:
            print("URL não fornecida. Abortando.")
            return
        
        success, _ = run_command(f"git remote add origin {remote_url}")
        if not success:
            print("Falha ao configurar repositório remoto.")
            return
    
    # 5. Garantir que a pasta dlaurojoias esteja adicionada ao Git
    success, _ = run_command("git add dlaurojoias/ -A")
    if not success:
        print("Falha ao adicionar a pasta dlaurojoias ao Git.")
        return
    
    # 6. Fazer commit das alterações
    success, _ = run_command('git commit -m "Adicionando pasta dlaurojoias e corrigindo estrutura"')
    if not success:
        print("Sem mudanças para commit ou falha ao fazer commit.")
    
    # 7. Verificar se o branch master existe localmente
    success, branches = run_command("git branch")
    if "master" not in branches:
        print("Criando branch master...")
        success, _ = run_command("git branch master")
        if not success:
            print("Falha ao criar branch master.")
            return
    
    # 8. Garantir que estamos no branch master
    if current_branch != "master":
        print(f"Mudando do branch {current_branch} para master...")
        success, _ = run_command("git checkout master")
        if not success:
            print("Falha ao mudar para o branch master.")
            return
    
    # 9. Mesclar as alterações do branch main para master
    if "main" in branches:
        print("Mesclando alterações do branch main para master...")
        success, _ = run_command("git merge main -m 'Mesclando alterações do main para master'")
        if not success:
            print("Falha ao mesclar alterações do main para master.")
            return
    
    # 10. Verificar novamente se a pasta dlaurojoias está incluída
    success, _ = run_command("git add dlaurojoias/ -A")
    
    # 11. Fazer novo commit se necessário
    run_command('git commit -m "Garantindo que dlaurojoias esteja incluído" || true', show_output=False)
    
    # 12. Enviar para o GitHub, forçando a atualização
    print("Enviando para o GitHub (branch master)...")
    success, _ = run_command("git push -u origin master --force")
    if not success:
        print("Falha ao enviar para o GitHub. Verifique suas credenciais.")
        return
    
    # 13. Instruções para configurar o GitHub Pages
    print("\n=== AÇÕES NECESSÁRIAS NO GITHUB ===")
    print("1. Acesse as configurações do seu repositório no GitHub")
    print("2. Navegue até a seção 'Pages'")
    print("3. Em 'Source', selecione o branch 'master'")
    print("4. Clique em 'Save'")
    print("5. Aguarde alguns minutos para o site ser publicado")
    print("\nDepois que estas ações forem concluídas, o site estará disponível em encontrodeidiomas.com.br")
    print("e a pasta dlaurojoias estará acessível em encontrodeidiomas.com.br/dlaurojoias")

if __name__ == "__main__":
    main() 