@echo off
echo === ENVIANDO PASTA DLAUROJOIAS PARA O REPOSITORIO REMOTO ===
echo.

REM 1. Verificar se estamos no branch master
git checkout master

REM 2. Adicionar a pasta dlaurojoias
echo Adicionando a pasta dlaurojoias...
git add dlaurojoias/ -A

REM 3. Fazer commit
echo Fazendo commit das alteracoes...
git commit -m "Adicionando a pasta dlaurojoias ao site"

REM 4. Enviar para o GitHub
echo Enviando para o GitHub (branch master)...
git push -u origin master

echo.
echo === VERIFICACAO ===
echo Lista de arquivos enviados na pasta dlaurojoias:
git ls-tree -r master:dlaurojoias --name-only

echo.
echo === PROCESSO CONCLUIDO ===
echo Verifique se a pasta dlaurojoias esta disponivel no repositorio remoto.
echo Apos alguns minutos, tente acessar: encontrodeidiomas.com.br/dlaurojoias

pause 