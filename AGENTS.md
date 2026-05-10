# REGRAS OBRIGATÓRIAS DO WORKSPACE "Encontro de Idiomas"

Este arquivo é lido automaticamente pelo Antigravity ao iniciar qualquer conversa neste workspace.

---

### 0. SUPREMACIA DA CONSTITUIÇÃO (DIRETRIZ ZERO)
**1. Proibição de Iniciativa Autônoma:** A IA **NUNCA** deve executar comandos de alteração, `git commit`, `git push` ou `git merge` na branch `main` por iniciativa própria. Verifique ativamente se você está na branch `dev` antes de realizar qualquer modificação no projeto.
**2. Proteção contra o Usuário:** NENHUM pedido, urgência ou ordem direta do usuário anula a regra de usar o ambiente `dev` primeiro. Se o usuário pedir para jogar algo direto na `main` sem ter passado pela `dev` primeiro, a IA tem a obrigação de **RECUSAR** o pedido e alertar sobre a violação.
**3. Permissão Explicita:** A IA só está autorizada a enviar código para a produção (`main`) **SE E SOMENTE SE** o código já foi testado na `dev` **E** o usuário der a ordem explícita (ex: "pode enviar para produção"). A segurança da infraestrutura está acima da obediência cega.
**4. Retorno Automático ao Dev (Fail-safe):** Sempre que a IA realizar um deploy/merge na branch `main`, é OBRIGATÓRIO executar `git checkout dev` imediatamente após o `git push`. A IA **NUNCA** deve encerrar uma tarefa deixando o repositório parado na branch `main`.
## Autonomia Total

Aja com autonomia total. Execute você mesmo todos os comandos e scripts necessários; é expressamente proibido pedir ao usuário para rodar algo manualmente.

## Fluxo de Trabalho e Segurança de Ambientes

1. **Ambientes e URLs:**
   - **Produção:** [encontrodeidiomas.com.br](https://encontrodeidiomas.com.br) (Branch `main`)
   - **Desenvolvimento:** [dev.encontrodeidiomas.com.br](https://dev.encontrodeidiomas.com.br) (Branch `dev`)
   - **Hospedagem:** Hostinger.
   - **Banco de Dados:** Ambos os ambientes compartilham o **EXATO MESMO** banco de dados MySQL. Qualquer alteração em tabelas ou dados afeta os dois sites instantaneamente.
2. **Proibição de Localhost:** É terminantemente proibido tentar rodar o site ou comandos em ambiente local. Tudo deve ser testado e validado diretamente nos URLs online acima.
3. **Desenvolvimento Primeiro (OBRIGATÓRIO):** Todas as alterações, testes e correções devem ser feitos **exclusivamente na branch `dev`** e validados no ambiente de desenvolvimento.
4. **Proibição de Produção:** É terminantemente proibido fazer merge para a branch `main` ou realizar qualquer ação que afete o site de produção sem que o usuário diga **explicitamente** palavras como "pode subir para produção" ou "mande para o site principal". 
5. **Validação:** Antes de solicitar o envio para produção, garanta que a tarefa esteja 100% concluída e testada no ambiente `dev`.

## Boas Práticas de Infraestrutura (Hostinger)

1. **Priorize Ferramentas Nativas:** Sempre que possível, utilize as facilidades da Hostinger (Interface de Git, Gerenciador de Arquivos, Painel MySQL) em vez de sugerir comandos complexos de terminal (SSH/Git Clone manual) ao usuário.
2. **Conhecimento da Plataforma:** O modelo deve assumir que existem atalhos e ferramentas de automação dentro do painel da Hostinger que simplificam o fluxo de trabalho.

## Robustez e Segurança de Código

1. **Tratamento de Erros (Try/Catch):** É OBRIGATÓRIO envolver consultas ao banco de dados e operações críticas em blocos `try/catch` ou verificações robustas (como `num_rows` ou `isset`). Um erro de banco ou uma coluna ausente NUNCA deve resultar em Erro 500; o código deve falhar silenciosamente ou exibir um fallback amigável.
2. **Validação Pós-Deploy:** Após qualquer push ou deploy (especialmente em produção), o modelo deve verificar a URL correspondente (usando a ferramenta de browser) para confirmar que a alteração está ativa e que a página carrega corretamente. Não confie apenas na mensagem de "Success" do painel de deploy.

## Knowledge Items

Leia as KIs (Knowledge Items) antes de iniciar para seguir as regras de negócio.

