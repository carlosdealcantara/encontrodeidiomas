# Script PowerShell para atualizar o site
# Este script adiciona, commita e envia as alterações para o GitHub

# Navegar para o diretório do projeto
Set-Location -Path "C:\encontrodeidiomas"

# Adicionar os arquivos modificados
git add contato.html index.html links.html online.html

# Fazer o commit com uma mensagem descritiva
git commit -m "Adicionado link para página online em todos os menus de navegação"

# Enviar as alterações para o GitHub
git push origin main

Write-Host "Alterações enviadas com sucesso!" 