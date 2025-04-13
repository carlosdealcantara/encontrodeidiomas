@echo off
echo === ATUALIZANDO O SITE NO REPOSITORIO REMOTO ===
echo.

REM 0. Remover configuração do branch main (se existir)
echo Removendo configuracoes do branch main...
git config --remove-section branch.main 2>nul

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

REM 2. Definir configuração padrão para master
echo Configurando defaultBranch como master...
git config --local init.defaultBranch master

REM 3. Remover branch main local (se existir)
echo Tentando remover o branch main local...
git branch -D main 2>nul

REM 4. Adicionar todas as alteracoes
echo Adicionando todas as alteracoes...
git add -A

REM 5. Fazer commit
echo Fazendo commit das alteracoes...
git commit -m "Atualizando o site com as ultimas alteracoes e removendo referencias ao branch main"

REM 6. Enviar para o repositorio remoto
echo Enviando alteracoes para o GitHub (branch master)...
git push -u origin master

echo.
echo === VERIFICACAO FINAL ===
echo.

REM 7. Verificar configuracao do Git
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