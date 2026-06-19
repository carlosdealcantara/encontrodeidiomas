# Acesso à VPS Oracle (Servidor Baileys)

Este documento contém os dados de acesso e procedimentos padrão para manutenção do servidor Baileys hospedado na Oracle VPS.

## Credenciais

- **IP do Servidor:** `136.248.92.126`
- **Usuário SSH:** `ubuntu`
- **Chave SSH Local:** `C:\Users\sorla\Projetos\encontrodeidiomas\ssh-key-2026-05-25.key`

## Como Conectar (Windows PowerShell)

Para acessar o terminal da Oracle diretamente do seu computador, execute:

```powershell
ssh -i C:\Users\sorla\Projetos\encontrodeidiomas\ssh-key-2026-05-25.key ubuntu@136.248.92.126
```

## Estrutura do Servidor

- **Diretório do Projeto:** `~/encontrodeidiomas/`
- **Pasta do Node.js (Baileys):** `~/encontrodeidiomas/baileys-server/`
- **Container do Docker:** O servidor Baileys roda dentro de um container Docker chamado `baileys-server`.

## Procedimento de Atualização (Deploy)

Sempre que fizermos alterações nos arquivos do Node.js (`server.js`, `mentoria.js`, etc.) e enviarmos para a branch `dev` no GitHub, o servidor precisará ser atualizado.

Para atualizar o servidor autonomamente (ou via SSH), use o seguinte comando combinado:

```bash
cd ~/encontrodeidiomas
git pull origin dev
sudo docker restart baileys-server
```

Após o restart, o container recarregará os códigos do repositório.

## Logs do Servidor

Para verificar se o Baileys está rodando corretamente ou depurar erros, verifique os logs do Docker:

```bash
sudo docker logs -f baileys-server
```
