# KI: Pipeline de Conversão Pod (Áudio) para Vídeo do YouTube

Esta base de conhecimento documenta o pipeline completo de reaproveitamento de áudios do Pod (Pill of the Day) para o canal do YouTube do professor Carlos de Alcantara.

---

## 1. Origem dos Áudios
- Os áudios são gravados no WhatsApp (grupo *The Lounge*), salvos na tabela `pilulas_conteudo` no MySQL e armazenados na VPS Oracle em:
  `/home/ubuntu/encontrodeidiomas/baileys-server/data/audios_pilulas/`
- O arquivo de áudio do episódio N pode ser baixado via SCP para a máquina local em `scratch/pod_test/pod_N.ogg`.

---

## 2. Execução da Automação (Script Local)
O script Python centralizado está versionado em:
`scripts/gerar_video_pod.py`

### Como rodar:
```bash
python scripts/gerar_video_pod.py --pod <NUMERO> --url "<URL_YOUTUBE_CREATIVE_COMMONS>"
```

### O que o script faz sozinho:
1. **Duração:** Mede o tempo exato do áudio via FFprobe.
2. **Transcrição Offline (Zero custo de API):** Usa o modelo Faster-Whisper local.
3. **Legendas Dinâmicas:** Gera arquivo de legendas fatiadas em blocos curtos (4 a 5 palavras por linha) para evitar spoilers e maximizar a retenção visual.
4. **Branding no Vídeo:** Adiciona no canto superior esquerdo (Top-Left): `POD #<N>   |   CARLOS DE ALCANTARA`.
5. **Estilo das Legendas:** Letras brancas em Arial Bold, com contorno preto (`outline=3`) e sombra suave (`shadow=2`), centralizadas na parte inferior.
6. **Download do Vídeo de Fundo:** Baixa o vídeo do YouTube em até 1080p (Creative Commons) via `yt-dlp`.
7. **Renderização FFmpeg:** Substitui o som original pelo áudio do Pod e exporta diretamente para:
   `~/Downloads/pod<N>.mp4`

---

## 3. O que a IA DEVE Entregar ao Usuário ao Final da Geração:

Ao concluir a geração do vídeo de qualquer episódio, a IA deve **obrigatoriamente** entregar três coisas formatadas:

### A. Título do Vídeo
- **Estrutura:** `[Gancho Curioso / Anti-erro] | Pod #<N>`
- **Exemplo:** `NUNCA diga "Did you understand?" em inglês! Use isso | Pod #1`

### B. Descrição Completa do YouTube (Pronta para copiar e colar)
Seguir rigorosamente este template com quebras de linha limpas:
```text
[Breve resumo de 2 linhas do erro e da dica abordada no áudio].

Neste episódio do Pill of the Day (Pod #<N>), o professor Carlos de Alcantara mostra [benefício/solução da aula].

💬 Deixe seu exemplo nos comentários para praticar!

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

🌍 QUER PRATICAR INGLÊS DE GRAÇA TODA SEMANA?
Participe das nossas conversações gratuitas online com pessoas de vários níveis:
👉 https://viaEi.com

📱 COMUNIDADE NO WHATSAPP & CONTEÚDOS DIÁRIOS:
Receba dicas diárias, áudios exclusivos e o link das chamadas gratuitas:
👉 Acesse nossos links: https://viaEi.com/links
👉 Siga no Instagram: https://instagram.com/ei.ingles

🎯 MENTORIA & ACELERAÇÃO:
Quer destravar a sua fala e ter acompanhamento direto comigo?
👉 Conheça a Mentoria: https://viaEi.com/mentoria

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Créditos de imagem: Vídeo de fundo em [Local do Vídeo] licenciado sob Creative Commons (CC BY).

#Ingles #CarlosDeAlcantara #AprenderIngles #DicasDeIngles #InglesDoDiaADia #EnglishTips
```

### C. Prompt Otimizado para Geração de Thumbnail no Gemini
O usuário gera a capa no Gemini para ter estética cinematográfica 4K e letreiros chamativos. A IA deve gerar um prompt sob medida seguindo esta estrutura:
```text
Gere uma thumbnail de alto impacto para o YouTube, proporção 16:9 widescreen (1280x720).

Cenário e Fundo:
- Vista cinematográfica em ângulo aberto de [Cidade/Cenário do vídeo de fundo].
- Iluminação vibrante, realista, fotografia urbana em 4K, sem pessoas em primeiro plano.

Textos Integrados na Imagem (Extremamente chamativos, grandes e legíveis):
- No centro-esquerda, texto gigante em 3D ou estilo pôster moderno com cores contrastantes (amarelo vibrante e branco com contorno preto forte e sombra profunda):
  "[FRASE CLICKBAIT REAL / GANCHO PRINCIPAL]"
- Logo abaixo em uma faixa contrastante com tipografia moderna e nítida:
  "[TERMO CHAVE OU ERRO EM DESTAQUE]"
- No canto superior: um selo discreto e elegante: "POD #<N> • DICA RÁPIDA"
- No canto inferior: a tag sutil: "Carlos de Alcantara | @ei.ingles"

Estilo geral: Design gráfico arrojado e moderno de capa de vídeo viral de YouTube, cores saturadas e vibrantes, alto contraste para leitura perfeita mesmo no feed do celular.
```

---

## 4. Dados e Identidade do Projeto
- **Nome do Autor:** Carlos de Alcantara
- **Instagram:** `@ei.ingles` (`https://instagram.com/ei.ingles`)
- **Site:** `https://viaEi.com`
- **Página de Links / Grupos:** `https://viaEi.com/links`
- **Página da Mentoria:** `https://viaEi.com/mentoria`
