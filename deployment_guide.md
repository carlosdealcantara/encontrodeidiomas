# Guia de Implantação - Encontro de Idiomas

## Estrutura do Projeto
- O site principal está na raiz do repositório
- A nova seção "D'Lauro Joias" está na pasta `/dlaurojoias`

## Configuração de Branch para GitHub Pages
- O site está configurado para ser publicado a partir do branch **master** (não main)
- Certifique-se de que suas alterações estejam sempre no branch master para serem publicadas
- Se estiver trabalhando no branch main, sempre mescle as alterações para o master

## Implantação do site principal (encontrodeidiomas.com.br)
1. Todos os arquivos na raiz devem ser implantados na raiz do domínio
2. Certifique-se de que todos os arquivos HTML, CSS e JavaScript estejam corretamente enviados
3. O arquivo CNAME deve existir e conter apenas: `encontrodeidiomas.com.br`

## Implantação da seção D'Lauro Joias (encontrodeidiomas.com.br/dlaurojoias)
1. A pasta `/dlaurojoias` deve ser implantada como um subdiretório no domínio
2. O conteúdo deve estar acessível em: `encontrodeidiomas.com.br/dlaurojoias`
3. Este é um projeto separado e NÃO deve ter links ou referências no site principal
4. Todas as imagens e recursos da seção D'Lauro Joias estão na pasta `/dlaurojoias/images`

## Passos para atualização no servidor
1. Execute o script `fix_github_pages.bat` para garantir que a pasta dlaurojoias seja corretamente enviada
2. Execute o script `update_cname.bat` para verificar e atualizar o arquivo CNAME
3. Acesse as configurações do GitHub Pages no repositório:
   - Certifique-se de que o branch **master** está selecionado como fonte
   - Verifique se o domínio personalizado está configurado como `encontrodeidiomas.com.br`
   - Ative a opção "Enforce HTTPS" se disponível

## Solução de problemas comuns
1. **Site não acessível:**
   - Verifique se o branch master está selecionado no GitHub Pages
   - Verifique se o arquivo CNAME existe e contém o domínio correto
   - Verifique a configuração DNS do domínio

2. **Pasta dlaurojoias não acessível:**
   - Verifique se a pasta está presente no branch master
   - Execute `fix_github_pages.bat` para corrigir problemas

3. **Mudança de branch não efetivada:**
   - Se você alterou o branch padrão no GitHub, também precisa alterar nas configurações do GitHub Pages

## Verificação pós-implantação
1. Verifique se o site principal está funcionando corretamente em `encontrodeidiomas.com.br`
2. Teste o acesso à seção D'Lauro Joias em `encontrodeidiomas.com.br/dlaurojoias`
3. Verifique se todas as imagens e funcionalidades estão operando como esperado 