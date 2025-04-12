# Script PowerShell para atualizar o site
# Este script adiciona, commita e envia as alterações do perfil do Isaac para o GitHub

# Navegar para o diretório do projeto
Set-Location -Path "C:\encontrodeidiomas"

# Adicionar apenas o arquivo equipe.html
git add equipe.html

# Fazer o commit com uma mensagem descritiva
git commit -m "Atualizada descrição do Isaac para entusiasta conforme feedback"

# Enviar as alterações para o GitHub
git push origin main

Write-Host "Alterações enviadas com sucesso!" 