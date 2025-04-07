# Encontro de Idiomas - Site

Este é o repositório para o site oficial do Encontro de Idiomas, uma comunidade gratuita para praticar idiomas em grupo.

## Estrutura do Projeto

- `index.html` - Página principal do site, otimizada para SEO
- `simple_server.py` - Servidor Python simples para testes locais

## Iniciando o Servidor Local

Para iniciar o servidor local, siga os passos abaixo:

1. **Método 1**: Usando o módulo http.server padrão do Python
   ```
   python -m http.server 8000
   ```

2. **Método 2**: Usando o servidor personalizado
   ```
   python simple_server.py
   ```

3. Acesse o site em `http://localhost:8000`

## Deploy no GitHub Pages

1. Crie um novo repositório no GitHub chamado "encontrodeidiomas"
2. Faça upload dos arquivos para o repositório
3. Nas configurações do repositório, ative o GitHub Pages usando a branch "main"
4. Configure os registros DNS para o domínio personalizado

## Configuração DNS para encontrodeidiomas.com.br

### Registros A
Para apontar a raiz do domínio para o GitHub Pages:
```
@ A 185.199.108.153
@ A 185.199.109.153
@ A 185.199.110.153
@ A 185.199.111.153
```

### Registro CNAME
Para o subdomínio www:
```
www CNAME seuusuario.github.io
```

## Contato

Entre em contato pelo Instagram: [@encontrodeidiomas](https://instagram.com/encontrodeidiomas) 