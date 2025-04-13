@echo off
echo === ATUALIZANDO O SITE NO REPOSITORIO REMOTO ===
echo.

REM 1. Garantir que estamos no branch master
echo Verificando o branch atual...
for /f "tokens=*" %%a in ('git branch --show-current') do set current_branch=%%a
echo Branch atual: %current_branch%

if NOT "%current_branch%"=="master" (
    echo Mudando para o branch master...
    git checkout master
    if ERRORLEVEL 1 (
        echo Branch master nao existe, criando...
        git checkout -b master
    )
)

REM 2. Adicionar todas as alteracoes
echo Adicionando todas as alteracoes...
git add -A

REM 3. Fazer commit
echo Fazendo commit das alteracoes...
git commit -m "Atualizando o site com as ultimas alteracoes e removendo referencias ao branch main"

REM 4. Enviar para o repositorio remoto
echo Enviando alteracoes para o GitHub (branch master)...
git push -u origin master

echo.
echo === VERIFICACAO FINAL ===
echo.

REM 5. Verificar configuracao do Git
echo Verificando configuracao do Git...
git config --list | findstr "branch"

echo.
echo Verificando branches locais...
git branch

echo.
echo === PROCESSO CONCLUIDO ===
echo O site foi atualizado com sucesso! Aguarde alguns minutos para que as alteracoes sejam propagadas.
echo Verifique o site em: encontrodeidiomas.com.br

pause 