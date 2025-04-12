# Script PowerShell para atualizar o site
# Este script adiciona, commita e envia apenas os arquivos necessários para o GitHub

# Navegar para o diretório do projeto
Set-Location -Path "C:\encontrodeidiomas"

# Adicionar apenas os arquivos necessários
git add equipe.html online.html

# Fazer o commit com uma mensagem descritiva
git commit -m "Corrigido o rodapé nas páginas de equipe e online para um design consistente"

# Enviar as alterações para o GitHub
git push origin main

Write-Host "Alterações enviadas com sucesso!" 