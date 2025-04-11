# Script para corrigir o fundo preto da página de links
Set-Location -Path "C:\encontrodeidiomas"

# Adicione o arquivo modificado
git add links.html

# Commit com mensagem descritiva detalhada
git commit -m "Corrigido fundo preto da página links para fundo claro padronizado"

# Push para o GitHub
git push origin main

# Mensagem de sucesso
Write-Host "Alterações enviadas com sucesso! A página de links agora tem o fundo claro padronizado." 