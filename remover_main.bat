@echo off
echo === REMOVENDO COMPLETAMENTE O BRANCH MAIN ===
echo.

REM 1. Garantir que estamos no branch master
echo Mudando para o branch master...
git checkout master

REM 2. Remover a seção [branch "main"] do arquivo de configuração
echo Removendo configuracoes do branch main...
git config --remove-section branch.main

REM 3. Configurar defaultBranch como master
echo Configurando defaultBranch como master...
git config --local init.defaultBranch master

REM 4. Remover o branch main local
echo Tentando remover o branch main local...
git branch -D main

REM 5. Verificar o resultado
echo.
echo === VERIFICACAO FINAL ===
echo.
echo Configuracao do Git:
git config --list | findstr "branch"

echo.
echo Branches locais:
git branch

echo.
echo === PROCESSO CONCLUIDO ===
echo O branch main foi completamente removido da configuracao.
echo Execute o script 'atualizar_site.bat' para atualizar o repositorio remoto.

pause 