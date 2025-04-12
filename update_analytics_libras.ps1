# Script PowerShell para atualizar o site
# Este script adiciona, commita e envia as alterações do Google Analytics e correção do filtro de Libras para o GitHub

# Navegar para o diretório do projeto
Set-Location -Path "C:\encontrodeidiomas"

# Adicionar os arquivos atualizados
git add index.html online.html equipe.html links.html contato.html

# Fazer o commit com uma mensagem descritiva
git commit -m "Atualizado ID do Google Analytics e corrigido filtro de Libras"

# Enviar as alterações para o GitHub
git push origin main

Write-Host "Alterações enviadas com sucesso!" 