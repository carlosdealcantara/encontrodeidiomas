# Instruções para Atualizar o Repositório no Branch Master

Se os scripts automatizados não funcionarem, você pode seguir estas instruções manuais para garantir que todos os arquivos locais do seu projeto sejam enviados para o branch **master** do GitHub.

## Passos para atualizar o repositório

### 1. Abra o PowerShell ou o Prompt de Comando

- Clique com o botão direito na pasta do projeto enquanto pressiona a tecla Shift
- Selecione "Abrir janela do PowerShell aqui" ou "Abrir prompt de comando aqui"

### 2. Verifique o branch atual

```
git branch
```

O branch atual será marcado com um asterisco (*). Se você estiver em outro branch que não seja **master**, precisará mudar para o branch **master**.

### 3. Mudar para o branch master
Se você estiver em outro branch que não seja **master**, mude para o branch **master**:
```
git checkout master
```

### 4. Se o branch master não existir, crie-o
Se o comando anterior falhar, será necessário criar o branch master:
```
git checkout -b master
```

### 5. Adicione todos os arquivos ao Git
```
git add -A
```

### 6. Faça commit das alterações

```
git commit -m "Atualizando todos os arquivos para a versão mais recente"
```

### 7. Envie as alterações para o GitHub

```
git push -u origin master
```

Se você receber um erro ao executar este comando, pode tentar forçar o push (use com cuidado):

```
git push -u origin master --force
```

## Verificando a atualização

Após enviar os arquivos para o GitHub, pode levar alguns minutos para que as alterações sejam refletidas no GitHub Pages. Para verificar se a atualização foi bem-sucedida:

1. Acesse seu repositório no GitHub
2. Verifique se os arquivos foram atualizados na branch **master**
3. Aguarde alguns minutos e acesse seu site em `https://encontrodeidiomas.com.br`

## Solução de problemas comuns

### Erro de autenticação

Se você receber um erro de autenticação ao tentar fazer push, verifique suas credenciais do GitHub. Você pode precisar configurar o Git para usar suas credenciais:

```
git config --global user.name "Seu Nome"
git config --global user.email "seu-email@exemplo.com"
```

### Conflitos de mesclagem

Se houver conflitos ao mesclar branches, o Git informará quais arquivos estão em conflito. Você precisará resolver esses conflitos manualmente e depois continuar com o processo de mesclagem.

### Erro "Remote contains work that you do not have locally"

Se você receber este erro, significa que há alterações no repositório remoto que não estão no seu repositório local. Você pode puxar as alterações primeiro:

```
git pull origin master
```

Depois, tente fazer o push novamente. 