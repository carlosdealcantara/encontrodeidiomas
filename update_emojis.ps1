# Script PowerShell para atualizar o site
# Este script adiciona, commita e envia as alterações dos emojis para o GitHub

# Navegar para o diretório do projeto
Set-Location -Path "C:\encontrodeidiomas"

# Adicionar apenas o arquivo equipe.html
git add equipe.html

# Fazer o commit com uma mensagem descritiva
git commit -m "Alterados emojis para melhor diferenciação entre botões de filtro"

# Enviar as alterações para o GitHub
git push origin main

Write-Host "Alterações enviadas com sucesso!" 