# Script para corrigir as configurações do Git e remover referências ao branch "main"

Write-Host "=== CORRIGINDO CONFIGURAÇÕES DO GIT ===" -ForegroundColor Yellow
Write-Host ""

# 1. Fazer backup do arquivo de configuração original
$configPath = ".git/config"
$backupPath = ".git/config.backup"
Copy-Item -Path $configPath -Destination $backupPath -Force
Write-Host "Backup do arquivo de configuração salvo em: $backupPath" -ForegroundColor Cyan

# 2. Ler o conteúdo do arquivo de configuração
$configContent = Get-Content $configPath

# 3. Filtrar para remover seções relacionadas ao branch "main"
$newContent = @()
$skipSection = $false

foreach ($line in $configContent) {
    if ($line -match '\[branch "main"\]') {
        $skipSection = $true
        continue
    }
    
    if ($skipSection -and $line -match '^\[') {
        $skipSection = $false
    }
    
    if (-not $skipSection) {
        $newContent += $line
    }
}

# 4. Escrever o novo conteúdo no arquivo de configuração
$newContent | Set-Content $configPath

# 5. Definir defaultBranch como master
Write-Host "Configurando defaultBranch como master..." -ForegroundColor Cyan
git config --local init.defaultBranch master

# 6. Tentar excluir o branch main local (se existir)
Write-Host "Tentando remover o branch main local..." -ForegroundColor Cyan
git branch -D main 2>$null

# 7. Mudar para o branch master (se ainda não estiver nele)
Write-Host "Mudando para o branch master..." -ForegroundColor Cyan
git checkout master

# 8. Mostrar a nova configuração
Write-Host "`nNova configuração do Git:" -ForegroundColor Green
Get-Content $configPath

# 9. Verificação final
Write-Host "`n=== VERIFICAÇÃO FINAL ===" -ForegroundColor Yellow

Write-Host "`nVerificando configuração do Git..." -ForegroundColor Cyan
git config --list | Select-String -Pattern "branch"

Write-Host "`nVerificando branches locais..." -ForegroundColor Cyan
git branch

Write-Host "`n=== PROCESSO CONCLUÍDO ===" -ForegroundColor Yellow
Write-Host "As configurações do Git foram corrigidas! O branch main foi removido da configuração."
Write-Host "Execute novamente o script 'atualizar_site.bat' para enviar as alterações para o repositório remoto." 