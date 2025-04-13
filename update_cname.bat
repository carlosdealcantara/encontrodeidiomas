@echo off
echo === VERIFICAÇÃO E ATUALIZAÇÃO DO ARQUIVO CNAME ===
echo.

REM Verificar se o arquivo CNAME existe
if exist CNAME (
    echo Arquivo CNAME encontrado.
    type CNAME
) else (
    echo Arquivo CNAME não encontrado. Criando...
    echo encontrodeidiomas.com.br > CNAME
    echo Arquivo CNAME criado com o domínio: encontrodeidiomas.com.br
)

REM Adicionar o CNAME ao Git
echo Adicionando o arquivo CNAME ao Git...
git add CNAME

REM Fazer commit
echo Fazendo commit das alterações...
git commit -m "Atualizando arquivo CNAME para o domínio encontrodeidiomas.com.br"

REM Verificar branch atual
for /f "tokens=*" %%a in ('git branch --show-current') do set current_branch=%%a
echo Branch atual: %current_branch%

REM Enviar para o branch master
if "%current_branch%"=="master" (
    echo Enviando para o GitHub (branch master)...
    git push -u origin master
) else (
    echo Mudando para o branch master...
    git checkout master || git checkout -b master
    
    echo Atualizando o arquivo CNAME no branch master...
    git add CNAME
    git commit -m "Atualizando arquivo CNAME para o domínio encontrodeidiomas.com.br"
    
    echo Enviando para o GitHub (branch master)...
    git push -u origin master
)

echo.
echo === VERIFICAÇÃO CONCLUÍDA ===
echo Certifique-se de que nas configurações do GitHub Pages:
echo 1. O branch correto (master) está selecionado
echo 2. O domínio personalizado está configurado como encontrodeidiomas.com.br
echo 3. A opção "Enforce HTTPS" está marcada (se possível)
echo.
echo Depois dessas configurações, aguarde alguns minutos para o site ser atualizado.

pause 