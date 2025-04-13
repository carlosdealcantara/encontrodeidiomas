@echo off
echo === ENVIANDO TODOS OS ARQUIVOS PARA O BRANCH MASTER ===
echo.

REM Verificar o branch atual
echo Verificando o branch atual...
git branch

REM Mudar para o branch master
echo Mudando para o branch master...
git checkout master || git checkout -b master

REM Verificar se existe branch main para mesclar as alterações
echo Verificando se existe o branch main...
git branch | findstr "main" > nul
if %errorlevel% == 0 (
    echo Branch main encontrado, mesclando alterações...
    git merge main -m "Mesclando alterações do main para master"
)

REM Adicionar todos os arquivos
echo Adicionando todos os arquivos...
git add -A

REM Fazer commit
echo Fazendo commit das alterações...
git commit -m "Atualizando todos os arquivos para a versão mais recente"

REM Enviar para o GitHub
echo Enviando para o GitHub (branch master)...
git push -u origin master

echo.
echo === PROCESSO CONCLUÍDO ===
echo Verifique acima se houve algum erro durante o processo.
echo O site deve ser atualizado em alguns minutos.
echo Acesse https://encontrodeidiomas.com.br para verificar.
echo.

pause 