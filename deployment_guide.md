# Guia de Implantação - Encontro de Idiomas

## Estrutura do Projeto
- O site principal está na raiz do repositório
- A nova seção "D'Lauro Joias" está na pasta `/dlaurojoias`

## Implantação do site principal (encontrodeidiomas.com.br)
1. Todos os arquivos na raiz devem ser implantados na raiz do domínio
2. Certifique-se de que todos os arquivos HTML, CSS e JavaScript estejam corretamente enviados

## Implantação da seção D'Lauro Joias (encontrodeidiomas.com.br/dlaurojoias)
1. A pasta `/dlaurojoias` deve ser implantada como um subdiretório no domínio
2. O conteúdo deve estar acessível em: `encontrodeidiomas.com.br/dlaurojoias`
3. Este é um projeto separado e NÃO deve ter links ou referências no site principal
4. Todas as imagens e recursos da seção D'Lauro Joias estão na pasta `/dlaurojoias/images`

## Passos para atualização no servidor
1. Execute o script `git_update.py` para enviar todas as alterações para o GitHub
2. No servidor de hospedagem, atualize o site usando um dos seguintes métodos:
   - Pull do repositório Git (se o servidor suportar)
   - Upload manual dos arquivos via FTP
   - Uso do painel de controle da hospedagem para sincronizar com o GitHub

## Verificação pós-implantação
1. Verifique se o site principal está funcionando corretamente
2. Teste o acesso à seção D'Lauro Joias em: `encontrodeidiomas.com.br/dlaurojoias`
3. Verifique se todas as imagens e funcionalidades estão operando como esperado 