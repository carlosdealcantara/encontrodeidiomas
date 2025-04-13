@echo off
echo === REMOVENDO ARQUIVOS DESNECESSARIOS ===
echo.

REM Remover arquivos temporarios ou com proposito similar a outros já existentes
echo Removendo arquivos temporarios e redundantes...

REM Scripts redundantes de manipulação Git (temos o atualizar_site.bat que faz isso)
del remover_main.bat
del fix_branch_config.bat
del fix_git_config.ps1
del corrigir_config_git.ps1
del corrigir_config_git.bat
del fix_github_pages.bat
del fix_github_pages.py
del push_to_master.bat
del push_to_master.py
del git_update.py
del git_push.ps1
del push_dlaurojoias.bat
del passo_a_passo.md

REM Arquivos temporários ou vazios
del tatus
del "s=nav-links index.html"
del deploy.py
del deploy_to_vps.py
del flask_app.py

REM Scripts de update redundantes (mantendo o update_site.ps1 como referência)
del update_analytics_libras.ps1
del update_analytics_links.ps1
del update_daniel_servio.ps1
del update_emojis.ps1
del update_equipe_online.ps1
del update_imagem_og.ps1
del update_isaac.ps1
del update_links_background.ps1
del update_online_language_filter.ps1
del update_responsivo.ps1
del update_seo.ps1
del update_social_icons_e_links.ps1
del update_wellington.ps1
del update_imagem_og_final.ps1

REM Arquivos de teste ou ferramentas temporárias
del fix_html.py
del fix_encoding.py
del simple_test.py
del test_app.py

REM Manter os seguintes arquivos importantes:
REM - atualizar_site.bat (script principal de atualização)
REM - CNAME (necessário para configuração de domínio personalizado)
REM - README.md (documentação do projeto)
REM - instrucoes_manuais.md e instrucoes_manuais_git.md (documentação útil)
REM - verify_site.py (útil para verificar o site)
REM - arquivos HTML, CSS e JS (conteúdo do site)
REM - requirements.txt (dependências do projeto)

REM Adicionar as alteracoes
echo Adicionando alteracoes ao Git...
git add -A

REM Fazer commit das alteracoes
echo Fazendo commit das alteracoes...
git commit -m "Limpeza do repositorio: removendo arquivos temporarios e redundantes"

REM Enviar alteracoes para o repositorio remoto
echo Enviando alteracoes para o repositorio remoto...
git push origin master

echo.
echo === PROCESSO CONCLUIDO ===
echo O repositorio foi limpo com sucesso!

pause 