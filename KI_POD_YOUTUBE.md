# KI: Pipeline de Conversão Pod (Áudio) para Vídeo do YouTube

Esta base de conhecimento documenta o pipeline completo de reaproveitamento de áudios do Pod (Pill of the Day) para o canal do YouTube e redes verticais (Reels / Shorts / TikTok) do professor Carlos de Alcantara.

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

### O que o script faz sozinho (DOIS vídeos por episódio):
1. **Duração:** Mede o tempo exato do áudio via FFprobe.
2. **Transcrição Offline (Zero custo de API):** Usa o modelo Faster-Whisper local (`device=cpu`, `compute_type=int8`).
3. **Dois arquivos ASS gerados:**
   - `subtitles_pod<N>_h.ass` → horizontal (PlayRes 1920x1080, branding top-left `Alignment=7`)
   - `subtitles_pod<N>_v.ass` → vertical (PlayRes 1080x1920, branding top-center `Alignment=8`, legendas centro-tela `Alignment=5`)
4. **Legendas Dinâmicas:** 5 palavras por bloco, sem spoilers, loop de dopamina visual.
5. **Branding:** `POD #<N>   |   CARLOS DE ALCANTARA` em fonte Arial, pequeno e discreto.
6. **Estilo das Legendas:** Arial Bold branco, Outline=3.5, Shadow=2, **sem caixa de fundo**.
   - **⚠️ FontSize CORRETO no ASS: 68** (em espaço de coordenadas 1920x1080; equivale visualmente ao SRT force_style FontSize=28 aprovado pelo usuário). Nunca usar valores abaixo de 60.
7. **Download do Vídeo de Fundo:** Baixa o vídeo YouTube em até 1080p (Creative Commons) via `yt-dlp`.
8. **Renderização FFmpeg — dois outputs:**
   - **Horizontal 16:9** → `~/Downloads/pod<N>.mp4` (YouTube)
   - **Vertical 9:16** → `~/Downloads/pod<N>_vertical.mp4` (Reels / Shorts / TikTok)
   - O vertical usa `crop=ih*9/16:ih,scale=1080:1920` para cortar o centro do vídeo de fundo.

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

