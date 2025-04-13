# Passo a Passo para Atualizar o Repositório

Siga estes comandos exatos na ordem correta para garantir que todos os arquivos locais do seu projeto sejam enviados para o branch **master** do GitHub.

## Comandos para executar no PowerShell ou Prompt de Comando

Abra o PowerShell ou o Prompt de Comando na pasta do projeto e execute estes comandos um por um:

```
git checkout master
git add -A
git commit -m "Atualizando todos os arquivos para a versão mais recente"
git push -u origin master
```

Se o primeiro comando resultar em erro, execute:

```
git checkout -b master
git add -A
git commit -m "Atualizando todos os arquivos para a versão mais recente"
git push -u origin master
```

Se você receber um erro ao fazer push, pode ser necessário mesclar as alterações do branch remoto primeiro:

```
git pull origin master
git push -u origin master
```

Se ainda houver problemas, você pode tentar forçar o push (use com cuidado):

```
git push -u origin master --force
```

## Verificação

Depois de executar esses comandos, aguarde alguns minutos e acesse seu site em `https://encontrodeidiomas.com.br` para verificar se ele foi atualizado com a versão mais recente do seu projeto. 