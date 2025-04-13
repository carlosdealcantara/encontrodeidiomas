@echo off
echo === LIMPANDO REPOSITORIO ===
echo.

REM 1. Garantir que estamos no branch master
echo Mudando para o branch master...
git checkout master

REM 2. Remover o branch temporario
echo Removendo branch temporario...
git branch -D temp_branch

REM 3. Remover arquivos temporarios e redundantes
echo Removendo arquivos temporarios e redundantes...
del remover_main.bat
del fix_branch_config.bat
del fix_git_config.ps1
del corrigir_config_git.ps1
del corrigir_config_git.bat
del tatus
del "s=nav-links index.html"
del fix_github_pages.bat
del fix_github_pages.py
del push_to_master.bat
del push_to_master.py

REM 4. Adicionar as alteracoes
echo Adicionando alteracoes...
git add -A

REM 5. Fazer commit das alteracoes
echo Fazendo commit das alteracoes...
git commit -m "Limpeza do repositorio: removendo arquivos temporarios e redundantes"

REM 6. Enviar alteracoes para o repositorio remoto
echo Enviando alteracoes para o repositorio remoto...
git push origin master

REM 7. Verificar status final
echo.
echo === VERIFICACAO FINAL ===
echo.
echo Branches atuais:
git branch

echo.
echo Status do repositorio:
git status

echo.
echo === PROCESSO CONCLUIDO ===
echo O repositorio foi limpo com sucesso.

pause 