@echo off
echo === CORRIGINDO CONFIGURACAO DE BRANCH ===
echo.

REM 1. Mudar para o branch master
echo Mudando para o branch master...
git checkout master

REM 2. Remover associacao com o branch upstream
echo Removendo associacao com branch upstream...
git branch --unset-upstream

REM 3. Configurar o branch master como upstream
echo Configurando o branch master como upstream...
git push -u origin master

REM 4. Verificar configuracao atual
echo Verificando configuracao atual...
git branch -vv

REM 5. Remover o branch main local (se existir)
echo Tentando remover o branch main local...
git branch -D main

REM 6. Adicionar a pasta dlaurojoias (se nao estiver adicionada)
echo Adicionando a pasta dlaurojoias...
git add dlaurojoias/ -A

REM 7. Fazer commit das alteracoes
echo Fazendo commit das alteracoes...
git commit -m "Adicionando pasta dlaurojoias e corrigindo configuracao de branch"

REM 8. Enviar alteracoes para o branch master
echo Enviando alteracoes para o branch master...
git push -u origin master

echo.
echo === PROCESSO CONCLUIDO ===
echo Por favor, verifique se o site agora exibe a versao mais recente.

pause 