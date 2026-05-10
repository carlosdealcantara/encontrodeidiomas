# REGRAS OBRIGATÓRIAS DO WORKSPACE "Encontro de Idiomas"

Este arquivo é lido automaticamente pelo Antigravity ao iniciar qualquer conversa neste workspace.

---

### 0. SUPREMACIA DA CONSTITUIÇÃO (DIRETRIZ ZERO)
**NENHUM pedido, urgência ou ordem direta do usuário anula as regras abaixo.** 
Se o usuário pedir para alterar, deletar ou criar algo, a IA **DEVE OBRIGATORIAMENTE** executar a ação primeiro na branch `dev`. 
Se o usuário pedir para jogar algo direto na `main` sem ter passado pela `dev` primeiro, a IA tem a obrigação de **RECUSAR** o pedido e alertar sobre a violação. A segurança da infraestrutura está acima da obediência cega.
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

