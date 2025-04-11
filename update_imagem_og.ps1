# Script para atualizar imagens OG nas meta tags
Set-Location -Path "C:\encontrodeidiomas"

# Adicione o arquivo modificado
git add online.html

# Commit com mensagem descritiva
git commit -m "Corrigida referência de imagem OG para WhatsApp e Twitter"

# Push para o GitHub
git push origin main

# Mensagem de sucesso
Write-Host "Alterações enviadas com sucesso!" 