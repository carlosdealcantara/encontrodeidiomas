# Script PowerShell para atualizar o site com melhorias de responsividade
# Este script adiciona, commita e envia as alterações para o GitHub

# Navegar para o diretório do projeto
Set-Location -Path "C:\encontrodeidiomas"

# Adicionar os arquivos modificados
git add index.html links.html contato.html online.html

# Fazer o commit com uma mensagem descritiva
git commit -m "Melhorias na responsividade para dispositivos móveis"

# Enviar as alterações para o GitHub
git push origin main

Write-Host "Alterações enviadas com sucesso!" 