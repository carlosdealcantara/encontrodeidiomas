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

## Autonomia Operacional e Git Workflow

1. **Commit e Push Automáticos em Dev:** Sempre que a IA **executar** alterações de código (criar, editar ou excluir arquivos do projeto), ela deve OBRIGATORIAMENTE, ao final da tarefa, realizar `git add`, `git commit` com mensagem semântica em português e `git push origin dev`. É PROIBIDO encerrar o turno pedindo para o usuário subir as alterações ou rodar comandos git manualmente.
2. **Exceção Única — Modo Análise:** A regra acima NÃO se aplica quando o usuário solicitar **explicitamente** apenas uma avaliação, análise, revisão ou planejamento sem execução. Nesses casos, nenhum commit ou push deve ser feito.
3. **Verificação de Branch Obrigatória:** Antes de qualquer `git commit`, execute `git branch --show-current` e confirme que está na branch `dev`. Se não estiver, execute `git checkout dev` antes de prosseguir. NUNCA faça commit direto na `main`.
4. **Granularidade:** Se a tarefa envolver múltiplos arquivos ou etapas, um único commit ao final é o suficiente, a menos que o usuário peça commits separados.

## Autonomia de Infraestrutura Remota (VPS / Docker / Baileys)

1. **Permissão Permanente:** A IA possui autorização permanente para acessar a VPS via SSH e executar comandos de manutenção (pull, restart de containers Docker, verificação de logs) quando a tarefa em andamento exigir. NÃO é necessário pedir permissão ao usuário para essas operações de rotina.
2. **Quando Atuar:** Sempre que uma alteração de código afetar o bot do WhatsApp (Baileys) ou serviços hospedados na VPS, a IA deve, após o `git push origin dev`, realizar autonomamente o `git pull` na VPS e reiniciar o serviço/container afetado conforme documentado no KI operacional do Baileys.
3. **Princípio da Necessidade:** Essa autonomia existe para eliminar passos manuais, NÃO para executar operações desnecessárias. Só acione a VPS/Docker se a tarefa concretamente demandar. Alterações puramente no site (PHP/HTML/CSS/JS servidos pela Hostinger) não requerem ação na VPS.
4. **Registro de Ações:** Ao executar operações na VPS, informe brevemente ao usuário o que foi feito (ex: "Fiz pull e restart do container baileys-bot na VPS").

## Restrições de Ferramentas e Otimização de Cota

1. **Proibição do Browser Subagent:** É expressamente proibido o uso da ferramenta de automação visual de navegador (`browser_subagent`) para qualquer finalidade — login, navegação em painéis, leitura de telas, testes visuais ou validação de deploy. Essa ferramenta consome tokens massivamente (15.000–40.000 por sessão), é propensa a falhas de autenticação e gera loops improdutivos.
2. **Alternativas Obrigatórias:**
   - Para validar se uma página está respondendo: use `curl`, `Invoke-WebRequest` ou a ferramenta `read_url_content` via terminal.
   - Para rodar scripts PHP de migração, alimentação de tabelas ou endpoints administrativos: forneça ao usuário a URL completa e pronta para clicar, ou dispare via requisição HTTP direta de terminal quando não exigir interação visual.
   - Para verificar conteúdo de páginas: use `read_url_content` (fetch HTTP com conversão para markdown).

## Fluxo de Trabalho e Segurança de Ambientes

1. **Ambientes e URLs:**
   - **Produção:** [viaEi.com](https://viaEi.com) (Branch `main`)
   - **Desenvolvimento:** [dev.viaEi.com](https://dev.viaEi.com) (Branch `dev`)
   - **Legado (redirecionador):** [encontrodeidiomas.com.br](https://encontrodeidiomas.com.br) → redireciona 301 para viaEi.com
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
2. **Validação Pós-Deploy:** Após qualquer push ou deploy (especialmente em produção), o modelo deve verificar a URL correspondente usando `read_url_content` ou requisição HTTP via terminal (`curl` / `Invoke-WebRequest`) para confirmar que a página responde com HTTP 200. NUNCA utilize a ferramenta de browser para essa verificação.

## Knowledge Items

Leia as KIs (Knowledge Items) antes de iniciar para seguir as regras de negócio.

