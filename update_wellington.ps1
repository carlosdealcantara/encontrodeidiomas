# Script PowerShell para atualizar o site
# Este script adiciona, commita e envia apenas as alterações específicas do Wellington para o GitHub

# Navegar para o diretório do projeto
Set-Location -Path "C:\encontrodeidiomas"

# Adicionar apenas o arquivo equipe.html
git add equipe.html

# Fazer o commit com uma mensagem descritiva
git commit -m "Revertida descrição do Wellington para especialista em Libras conforme solicitado"

# Enviar as alterações para o GitHub
git push origin main

Write-Host "Alterações enviadas com sucesso!" 