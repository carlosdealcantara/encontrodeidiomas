@echo off
echo === CORREÇÃO DO GITHUB PAGES E DA PASTA DLAUROJOIAS ===
echo.

REM Verificar se a pasta dlaurojoias existe
if not exist dlaurojoias\ (
    echo ERRO: A pasta dlaurojoias não existe no diretório atual!
    exit /b 1
)

REM Adicionar a pasta dlaurojoias ao Git
echo Adicionando a pasta dlaurojoias ao Git...
git add dlaurojoias/ -A

REM Fazer commit
echo Fazendo commit das alterações...
git commit -m "Adicionando pasta dlaurojoias e corrigindo estrutura"

REM Mudar para o branch master (ou criar se não existir)
echo Verificando se estamos no branch master...
for /f "tokens=*" %%a in ('git branch --show-current') do set current_branch=%%a
echo Branch atual: %current_branch%

if not "%current_branch%"=="master" (
    echo Mudando para o branch master...
    git checkout master || git checkout -b master
)

REM Garantir que o conteúdo do branch main seja mesclado com master
echo Mesclando alterações do branch main para master...
git merge main -m "Mesclando alterações do main para master" || echo Sem alterações para mesclar

REM Verificar novamente a pasta dlaurojoias
echo Garantindo que a pasta dlaurojoias está incluída...
git add dlaurojoias/ -A
git commit -m "Garantindo que dlaurojoias esteja incluído" || echo Sem novas alterações

REM Forçar o push para o branch master
echo Enviando para o GitHub (branch master)...
git push -u origin master --force

echo.
echo === INSTRUÇÕES ADICIONAIS ===
echo 1. Acesse as configurações do seu repositório no GitHub
echo 2. Navegue até a seção 'Pages'
echo 3. Em 'Source', selecione o branch 'master'
echo 4. Clique em 'Save'
echo 5. Aguarde alguns minutos para o site ser publicado
echo.
echo Depois que estas ações forem concluídas, o site estará disponível em encontrodeidiomas.com.br
echo e a pasta dlaurojoias estará acessível em encontrodeidiomas.com.br/dlaurojoias

pause 