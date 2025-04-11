# Script para atualizar as tags SEO no GitHub
Set-Location -Path "C:\encontrodeidiomas"

# Adicionar o arquivo modificado ao staging
git add anfitrioes-modelo3.html

# Commit das alterações com mensagem descritiva
git commit -m "Otimização das meta tags SEO e Open Graph na página de anfitriões (modelo3)"

# Enviar as alterações para o GitHub
git push origin main

# Confirmar sucesso
Write-Host "Alterações enviadas com sucesso!" 