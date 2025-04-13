# Script PowerShell para atualizar o site
# Este script adiciona, commita e envia as alterações do perfil do Daniel para o GitHub

# Navegar para o diretório do projeto
Set-Location -Path "C:\encontrodeidiomas"

# Adicionar apenas o arquivo equipe.html
git add equipe.html

# Fazer o commit com uma mensagem descritiva
git commit -m "Adicionado idioma sérvio ao perfil do Daniel"

# Enviar as alterações para o GitHub
git push origin master

Write-Host "Alterações enviadas com sucesso!" 