# Script para corrigir referências ao branch main nos arquivos de configuração Git

Write-Host "=== VERIFICANDO E CORRIGINDO CONFIGURAÇÕES DO GIT ===" -ForegroundColor Yellow

# 1. Verificar configuração do Git
Write-Host "Configuração atual do Git:" -ForegroundColor Cyan
git config --list | Select-String -Pattern "branch|remote"

# 2. Verificar branches
Write-Host "`nBranches atuais:" -ForegroundColor Cyan
git branch -a

# 3. Verificar o arquivo .git/config
Write-Host "`nVerificando arquivo .git/config..." -ForegroundColor Cyan
$configPath = ".git/config"
if (Test-Path $configPath) {
    $configContent = Get-Content $configPath -Raw
    if ($configContent -match "main") {
        Write-Host "Encontradas referências ao branch 'main' no arquivo .git/config" -ForegroundColor Red
        
        # Exibir o conteúdo atual
        Write-Host "Conteúdo atual:" -ForegroundColor Magenta
        Get-Content $configPath
        
        # Substituir main por master
        $newContent = $configContent -replace "main", "master"
        $newContent | Set-Content $configPath
        
        Write-Host "Arquivo .git/config atualizado." -ForegroundColor Green
        
        # Exibir o novo conteúdo
        Write-Host "Novo conteúdo:" -ForegroundColor Magenta
        Get-Content $configPath
    } else {
        Write-Host "Nenhuma referência ao branch 'main' encontrada no arquivo .git/config" -ForegroundColor Green
    }
} else {
    Write-Host "Arquivo .git/config não encontrado." -ForegroundColor Red
}

# 4. Mudar para o branch master e configurar upstream
Write-Host "`nMudando para o branch master..." -ForegroundColor Cyan
git checkout master

Write-Host "`nRemovendo associação com branch upstream..." -ForegroundColor Cyan
git branch --unset-upstream

Write-Host "`nConfigurando o branch master como upstream..." -ForegroundColor Cyan
git push -u origin master

# 5. Remover o branch main local (se existir)
Write-Host "`nTentando remover o branch main local..." -ForegroundColor Cyan
git branch -D main

# 6. Verificar branches novamente
Write-Host "`nVerificando branches após correções:" -ForegroundColor Cyan
git branch -a

Write-Host "`n=== PROCESSO CONCLUÍDO ===" -ForegroundColor Yellow
Write-Host "Por favor, verifique se as configurações foram corrigidas corretamente." 