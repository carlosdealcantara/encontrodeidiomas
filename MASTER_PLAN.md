# MASTER PLAN: Refatoração Encontro de Idiomas 🚀

Este documento serve como a "memória central" do projeto, consolidando o diagnóstico realizado e o plano de ação para a reconstrução do site, unindo a estabilidade da produção com a inteligência do desenvolvimento.

## 1. Panorama Atual das Branches
- **`master` (Produção):** Versão 100% estática (HTML). Design estável e funcional, mas sem inteligência. Os dados (anfitriões/eventos) estão "chumbados" no código.
- **`dev` (Desenvolvimento):** Versão inteligente em PHP. Contém a lógica de Banco de Dados, Inclusões de Header/Footer e Painel Administrativo. **Status atual: Instável (com bugs de layout e lógica).**
- **`staging`:** Idêntica à master.

## 2. Diagnóstico de Bugs Identificados
1. **Layout Inconsistente:** No galho `dev`, o cabeçalho e rodapé mudam de tamanho/comportamento dependendo da página, apesar de usarem o mesmo arquivo de inclusão.
2. **Cronograma/Timeline Quebrado:** A parte mais complexa (linha do tempo) não renderiza corretamente quando os dados vêm do banco de dados no `dev`.
3. **Filtros (Combobox):** Os filtros de idioma e dia da semana no `dev` não carregam os itens ou falham na interação.
4. **Bug do Rodapé:** Erro de sintaxe no script do ano (exibindo o código literal `document.write...`).
5. **SEO & Acessibilidade:** Hierarquia de títulos (H3 em vez de H2) e falta de tags `alt` em imagens.

## 3. Protocolo de Segurança (Urgente)
> [!IMPORTANT]
> A senha do banco de dados foi alterada na Hostinger em 29/04/2026 após detecção de exposição no GitHub público.

**Próximas ações de segurança:**
- Implementar sistema de **Variáveis de Ambiente (`.env`)**.
- Criar `config.php.example` para o repositório.
- Adicionar `config.php` e `.env` ao `.gitignore`.

## 4. Estratégia de Refatoração "O Melhor de Dois Mundos"
O objetivo não é apenas consertar o `dev`, mas reconstruir o site de forma cirúrgica:
1. **Nova Base (`refactor-branch`):** Criar uma nova branch a partir da `master` (garantindo o design estável).
2. **Injeção de Inteligência:**
   - Converter arquivos `.html` para `.php`.
   - Substituir Header/Footer manuais pelos arquivos da pasta `includes/`.
   - Configurar a conexão segura com o Banco de Dados.
3. **Correção de Lógica:**
   - Refatorar o loop do Cronograma para que o PHP gere exatamente o HTML/CSS que funciona na `master`.
   - Corrigir os endpoints AJAX dos filtros de busca.

## 5. Como iniciar a execução
1. Abra a pasta `c:\Users\sorla\encontrodeidiomas` no seu editor.
2. Ative o modelo de IA de sua preferência.
3. Comando inicial sugerido: *"Leia o MASTER_PLAN.md e vamos iniciar o Passo 1: Implementação do sistema de segurança .env e criação da branch de refatoração."*

---
**Status do Ambiente:** Git configurado para `carlosdealcantara`.
**Data do Diagnóstico:** 29/04/2026.
