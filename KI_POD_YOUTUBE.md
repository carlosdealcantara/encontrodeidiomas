# KI: Pipeline de Conversão Pod (Áudio) para Vídeo do YouTube

Esta base de conhecimento documenta o pipeline completo de reaproveitamento de áudios do Pod (Pill of the Day) para o canal do YouTube e redes verticais (Reels / Shorts / TikTok) do professor Carlos de Alcântara.

---

## 1. Origem e Download dos Áudios
- Os áudios são gravados no WhatsApp (grupo *The Lounge*), salvos como rascunhos no MySQL e armazenados na VPS Oracle em:
  `/home/ubuntu/encontrodeidiomas/baileys-server/data/audios_pilulas/`
- **Acesso SSH / SCP à Oracle VPS:**
  - **Host / IP:** `136.248.92.126` (usuário: `ubuntu`)
  - **Chave SSH Local Obrigatória:** `C:\Users\sorla\Projetos\encontrodeidiomas\ssh-key-2026-05-25.key`
- **Como a IA localiza e baixa o áudio do episódio N:**
  1. Conectar via SSH na VPS usando a chave para listar os arquivos ordenados cronologicamente:
     ```powershell
     ssh -i C:\Users\sorla\Projetos\encontrodeidiomas\ssh-key-2026-05-25.key ubuntu@136.248.92.126 "ls -1tr /home/ubuntu/encontrodeidiomas/baileys-server/data/audios_pilulas/"
     ```
  2. O Pod #1 corresponde ao 1º áudio da ordem cronológica (`pilula_1784860410211.ogg`), Pod #2 ao 2º, Pod #3 ao 3º, e assim sucessivamente:
     - Pod #1: `pilula_1784860410211.ogg`
     - Pod #2: `pilula_1785029252319.ogg`
     - Pod #3: `pilula_1785029273241.ogg`
     - Pod #4: `pilula_1785075422474.ogg`
     - Pod #5: `pilula_1785165963239.ogg`
     - Pod #6: `pilula_1785332191057.ogg`
  3. Baixar via SCP com a chave privada diretamente para a pasta local:
     ```powershell
     scp -i C:\Users\sorla\Projetos\encontrodeidiomas\ssh-key-2026-05-25.key ubuntu@136.248.92.126:/home/ubuntu/encontrodeidiomas/baileys-server/data/audios_pilulas/<ARQUIVO_PILULA>.ogg scratch/pod_test/pod_<N>.ogg
     ```
  4. Medir a duração imediatamente via FFprobe e informar ao usuário para aguardar o link do YouTube.

---

## 2. Execução da Automação (Script Local)
O script Python centralizado está versionado em:
`scripts/gerar_video_pod.py`

### Como rodar:
```bash
python scripts/gerar_video_pod.py --pod <NUMERO> --url "<URL_YOUTUBE_CREATIVE_COMMONS>"
```

### O que o script faz sozinho (DOIS vídeos por episódio):
1. **Duração:** Mede o tempo exato do áudio via FFprobe e calcula o corte final correto com base no fator de aceleração (`target_duration = audio_duration / speed`). O vídeo NUNCA fica com sobras mudas no final.
2. **Transcrição Offline Robusta (Zero custo de API):** Usa o modelo Faster-Whisper **`medium`** local (`device=cpu`, `compute_type=int8`) com `condition_on_previous_text=False`, `vad_filter=True` e `no_speech_threshold=0.6` para alta precisão bilíngue (inglês/português) e garantia total contra omissão de blocos falados.
3. **Dois arquivos ASS gerados:**
   - `subtitles_pod<N>_h.ass` → horizontal 16:9 (PlayRes 1920x1080, branding top-left `Alignment=7`, legendas centro-tela `Alignment=5`)
   - `subtitles_pod<N>_v.ass` → vertical 9:16 (PlayRes 1080x1920, branding top-center `Alignment=8`, legendas centro-tela `Alignment=5`)
4. **Legendas Dinâmicas Protagonistas:** 5 palavras por bloco, sem spoilers, alto impacto visual no centro da tela em ambos os formatos.
5. **Branding no Topo:** `POD #<N>   |   CARLOS DE ALCÂNTARA` em fonte Arial com destaque visual ampliado:
   - **Horizontal (16:9):** `FontSize=44`, top-left (`Alignment=7`), contorno escuro nítido.
   - **Vertical (9:16):** `FontSize=56`, top-center (`Alignment=8`), margem superior vertical ampliada (`MarginV=140`) para safe zone total das interfaces e barras de busca de Reels, Shorts e TikTok.
6. **Estilo das Legendas:** Arial Bold branco, Outline=3.5, Shadow=2, **sem caixa de fundo**.
   - **⚠️ FontSize DEFINITIVO no ASS: 90** (legenda com protagonismo visual e tamanho ideal para visualização rápida no celular e desktop).
   - **Posicionamento:** Centralizado na tela (`Alignment=5`) em horizontal e vertical.
7. **Velocidade Padrão:** Acelerado em **1.2x** por padrão (`--speed 1.2`), queimando a legenda primeiro e acelerando os frames e áudio em conjunto para retenção ideal no YouTube, Reels e TikTok. O parâmetro `-t` do FFmpeg é ajustado para a duração final acelerada (`dur / speed`) para cortar no segundo exato onde termina o áudio.
8. **Download do Vídeo de Fundo:** Baixa o vídeo YouTube em até 1080p (Creative Commons) via `yt-dlp`.
9. **Renderização FFmpeg — dois outputs (Padrão de Nomenclatura):**
   - **Horizontal 16:9** → `~/Downloads/pod<N>.mp4` (YouTube)
   - **Vertical 9:16** → `~/Downloads/pod<N>v.mp4` (Reels / Shorts / TikTok)
   - O vertical usa `crop=ih*9/16:ih,scale=1080:1920` para cortar o centro do vídeo de fundo.

---

## 2.1. Regra de Fluxo de Trabalho (Importante)
- **Vídeo de Fundo Padrão:** Todo link do YouTube fornecido pelo usuário para o episódio é **sempre** o vídeo a ser usado como imagem de fundo na renderização.
- A IA **NUNCA** deve iniciar a renderização antes que o usuário forneça a URL do vídeo do YouTube.
- Quando o usuário solicitar a criação de um episódio:
  1. A IA deve primeiro baixar o áudio do pod (se já não estiver local) e obter a duração original e acelerada (1.2x).
  2. A IA informa imediatamente ao usuário a duração necessária para o vídeo de fundo e aguarda o link do YouTube.
  3. Somente após receber a URL, a IA executa o pipeline de download e renderização.
- **Duração do Próximo Episódio:** A IA deve SEMPRE informar ao final da mensagem a duração (original e acelerada 1.2x) do Pod seguinte (ex: ao entregar o Pod 3, já informar a duração do Pod 4), permitindo que o usuário já saiba quanto tempo de vídeo de fundo precisará buscar.

---

## 3. O que a IA DEVE Entregar ao Usuário ao Final da Geração:

Ao concluir a geração do vídeo de qualquer episódio, a IA deve **obrigatoriamente** entregar três coisas formatadas:

### A. Título do Vídeo
- **Estrutura:** `[Gancho Curioso / Anti-erro / Dúvida Real] | Pod #<N>`
- **Regra de Não-Repetição com a Capa:** O título deve focar mais no contexto ou na consequência do erro (ex: `CUIDADO ao falar isso em inglês! Você pode arrumar briga | Pod #2`), enquanto a thumbnail ataca por outro ângulo emocional ou direto (ex: `ISSO NÃO É BRIGA?!`). **Nunca repita as mesmas palavras exatas no texto da thumbnail e no título.**
- **Exemplo:** `NUNCA diga "Did you understand?" em inglês! Use isso | Pod #1`

### B. Descrição Completa do YouTube (Pronta para copiar e colar)
Seguir rigorosamente este template com quebras de linha limpas:
```text
[Breve resumo de 2 linhas do erro e da dica abordada no áudio].

Neste episódio do Pill of the Day (Pod #<N>), o professor Carlos de Alcântara mostra [benefício/solução da aula].

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
O usuário gera a capa no Gemini para ter estética cinematográfica 4K e letreiros chamativos. A IA deve gerar um prompt sob medida combinando uma **Estrutura Fixa (Branding)** com **Variáveis Dinâmicas por Episódio** para evitar "Cegueira de Padrão" no feed do YouTube:

> **⚠️ Regra de Complementaridade (Anti-Repetição):**
> O texto da thumbnail **NÃO DEVE** repetir as frases ou palavras exatas do título. Eles atuam em dupla para ativar o clique por ângulos diferentes:
> - Se o título foca no termo técnico/cognato (ex: *CUIDADO com "Discuss" e "Argue"*), a thumbnail deve focar no susto/reação humana (ex: *ARRUMOU BRIGA SEM QUERER?* ou *VOCÊ SOOU MUITO GROSSO!*).
> - Se o título foca na dor/situação (ex: *NUNCA diga isso para o seu chefe*), a thumbnail foca no erro visual ou contraste direto (ex: *PARE DE DIZER ISSO!*).

#### 1. Estrutura Fixa (Manter em 100% dos Prompts)
- **Formato:** Proporção 16:9 widescreen (1280x720), alto contraste e saturação para leitura mobile perfeita.
- **Texto Principal (Centro-Esquerda):** Tipografia 3D gigante, amarelo vibrante ou branco com contorno escuro forte e sombra projetada.
- **Branding Institucional:** 
  - Selo no canto superior: `"POD #<N> • DICA RÁPIDA"`
  - Tag no canto inferior: `"Carlos de Alcântara | @ei.ingles"`

#### 2. Matriz de Variáveis Dinâmicas (A IA DEVE Alternar a Cada Pod)

1. **Variável A — Cor da Tarja / Destaque Secundário (Conforme a intenção do conteúdo):**
   - **Alerta / Erros graves / Cuidado / Falsos amigos:** Tarja Vermelha vibrante com texto em branco ou amarelo.
   - **Vocabulário / Comparações / Expressões do dia a dia:** Tarja Ciano / Azul Elétrico com texto branco.
   - **Pronúncia / Dúvidas rápidas / Curiosidades:** Tarja Amarelo Alerta ou Verde Neon com texto escuro/preto para contraste máximo.

2. **Variável B — Enquadramento, Atmosfera e Iluminação Fiel ao Vídeo de Fundo:**
   - **Regra Fundamental de Coerência:** O cenário NÃO deve ser inventado aleatoriamente (ex: colocar cafeteria se o vídeo é uma caminhada em Los Angeles). O prompt deve **extrair a locação real a partir do título/tema do vídeo de fundo do YouTube** fornecido pelo usuário.
   - **Onde entra a variação visual (evitando a "mesmice" e mantendo 100% de realismo fotográfico):**
     - **Distância Focal e Enquadramento:** Alternar entre *plano geral aberto (wide angle / vista ampla da avenida ou horizonte)*, *plano médio (visão de pedestre com calçada/arquitetura próxima)* e *enquadramento com foco em elemento urbano específico (close em semáforo, placa de trânsito, fachada icônica com desfoque de fundo)*.
     - **Momento do Dia e Luz Natural:** Dia ensolarado com cores vivas e sombras nítidas, dia nublado/cinematográfico com iluminação suave, entardecer/golden hour com tons alaranjados/dourados, ou noite urbana com profundidade de luzes artificiais.
     - **Temperatura e Iluminação de Cena:** Variar entre tons quentes (luzes âmbar/amarelas de postes e vitrines) e tons frios/modernos (luzes de LED brancas/azuladas, neon, asfalto molhado refletindo luz).

3. **Variável C — Elemento Humano Opcional / Rotativo:**
   - **Padrão (Sem pessoa):** Cenário cinematográfico limpo com foco total na tipografia.
   - **Alternativa expressiva:** Quando a temática pedir forte apelo emocional (ex: espanto, dúvida ou alívio), incluir no lado direito um professor/homem jovem-adulto expressivo (olhando para o texto ou câmera com expressão de surpresa/alerta/dúvida), sem sobrepor as palavras.

---

#### Modelo do Prompt Gerado pela IA:
```text
Gere uma thumbnail de alto impacto para o YouTube, proporção 16:9 widescreen (1280x720).

Cenário e Fundo (Baseado no local real do vídeo de fundo: [Local extraído do título do vídeo]):
- Fotografia realista e cinematográfica em 4K capturada em [Local real do vídeo].
- Enquadramento e perspectiva: [Alternar entre: plano aberto com horizonte / perspectiva ao nível da rua / plano médio com foco em elemento urbano e fundo com bokeh suave].
- Atmosfera e luz: [Alternar entre: luz natural do dia vívida / pôr do sol dourado / noite vibrante com iluminação âmbar de postes / iluminação moderna fria de LED e reflexos].
- [Se sem pessoa: "Cena urbana realista sem pessoas em primeiro plano" | se com pessoa: "Homem expressivo ao lado direito em pose de [alerta/dúvida/surpresa], sem encobrir o texto"].

Textos Integrados na Imagem (Extremamente chamativos, grandes e legíveis - complementares ao título, sem repetir frases):
- No centro-esquerda, texto gigante em 3D estilo pôster moderno em amarelo vibrante com contorno preto e sombra profunda:
  "[FRASE CLICKBAIT COMPLEMENTAR / REAÇÃO HUMANA / GANCHO EMOTIVO]"
- Logo abaixo em uma tarja contrastante [COR ESCOLHIDA: Vermelha / Azul Elétrico / Verde Neon / Amarelo Alerta]:
  "[DÚVIDA RÁPIDA OU ALERTA IMPACTANTE EM TEXTO CONTRASTANTE]"
- No canto superior: um selo discreto e elegante: "POD #<N> • DICA RÁPIDA"
- No canto inferior: a tag sutil: "Carlos de Alcântara | @ei.ingles"

Estilo geral: Fotografia urbana realista em 4K combinada com design gráfico arrojado de capa de vídeo viral de YouTube, alto contraste para leitura perfeita mesmo no feed do celular.
```

---

## 4. Dados e Identidade do Projeto
- **Nome do Autor:** Carlos de Alcântara
- **Instagram:** `@ei.ingles` (`https://instagram.com/ei.ingles`)
- **Site:** `https://viaEi.com`
- **Página de Links / Grupos:** `https://viaEi.com/links`
- **Página da Mentoria:** `https://viaEi.com/mentoria`

