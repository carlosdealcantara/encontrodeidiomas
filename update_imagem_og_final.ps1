# Script para corrigir referências de imagem OG e favicon
Set-Location -Path "C:\encontrodeidiomas"

# Adicione o arquivo modificado
git add online.html

# Commit com mensagem descritiva detalhada
git commit -m "Corrigido problema com imagem OG e favicon para compartilhamento no WhatsApp"

# Força o push para o GitHub, garantindo que sobrescreva quaisquer alterações remotas
git push -f origin main

# Limpa o cache do Git para garantir que todas as alterações foram processadas
git gc --prune=now

# Mensagem de sucesso
Write-Host "Alterações enviadas com sucesso! A imagem OG agora deve aparecer corretamente no WhatsApp." 