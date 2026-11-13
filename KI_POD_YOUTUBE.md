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
- **Duração do Próximo Episódio (OBRIGATÓRIO):** Ao finalizar a entrega de qualquer episódio N, a IA deve **obrigatoriamente**:
  1. Conectar via SCP na VPS e já baixar autonomamente o áudio do episódio N+1 para `scratch/pod_test/pod_<N+1>.ogg`.
  2. Medir a duração exata com FFprobe.
  3. Informar ao final da mensagem a **duração mínima que o vídeo do YouTube precisa ter** (que é a duração ORIGINAL do áudio) e a duração final que o vídeo terá pronto (acelerada 1.2x):
     - **Vídeo de fundo do YouTube necessário:** No mínimo `<DUR_ORIGINAL>` segundos (`<X>m <Y>s`).
       *(Explicação: como o FFmpeg acelera o vídeo de fundo em 1.2x junto com o áudio, 1s de vídeo acelerado consome 1.2s do vídeo original do YouTube; portanto, o vídeo bruto precisa cobrir todo o tempo original).*
     - **Duração final do vídeo pronto:** `<DUR_ACELERADA>` segundos (`<X>m <Y>s`).

---

## 3. O que a IA DEVE Entregar ao Usuário ao Final da Geração:

Ao concluir a geração do vídeo de qualquer episódio, a IA deve **obrigatoriamente** entregar os 3 blocos de conteúdo formatados individualmente em **blocos de código (code blocks)** com botão de cópia com um clique:

### A. Título do Vídeo
- **Estrutura:** `[Gancho Curioso / Anti-erro / Dúvida Real] | Pod #<N>`
- **Regra de Não-Repetição com a Capa:** O título deve focar mais no contexto ou na consequência do erro (ex: `CUIDADO ao falar isso em inglês! Você pode arrumar briga | Pod #2`), enquanto a thumbnail ataca por outro ângulo emocional ou direto (ex: `ISSO NÃO É BRIGA?!`). **Nunca repita as mesmas palavras exatas no texto da thumbnail e no título.**
- **Exemplo:** `NUNCA diga "Did you understand?" em inglês! Use isso | Pod #1`
- **Formato de entrega:** Fornecer sempre dentro de um bloco de código ```` ```text ```` para cópia direta em 1 clique:
  ```text
  [Gancho Curioso / Anti-erro / Dúvida Real] | Pod #<N>
  ```

### B. Descrição Completa do YouTube (Pronta para copiar e colar em 1 clique)
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

#Ingles #Pod #CarlosDeAlcantara #PillOfTheDay #[HashtagDinamicaTema1] #[HashtagDinamicaTema2]
```

#### Regras de Ouro para as Hashtags da Descrição:
1. **Ordem das 3 Primeiras (Topo do Vídeo no YouTube):**
   - O YouTube destaca automaticamente as 3 primeiras hashtags acima do título do vídeo.
   - A ordem **OBRIGATÓRIA** das 3 primeiras é sempre: `#Ingles #Pod #CarlosDeAlcantara`.
2. **Hashtag de Marca do Formato:**
   - A 4ª hashtag fixa é sempre `#PillOfTheDay` (marca oficial das pílulas de áudio).
3. **Hashtags Dinâmicas (Específicas do Conteúdo):**
   - Adicionar apenas de 2 a 3 hashtags dinâmicas complementares extraídas do tema central do áudio (ex: `#SayOuTell`, `#FalseFriends`, `#PronunciaEmIngles`, `#DicasDeIngles`).
4. **Regra Anti-Redundância e Anti-Spam:**
   - **PROIBIDO repetição:** Nunca repetir nenhuma das tags do topo (`#Ingles`, `#Pod`, `#CarlosDeAlcantara`) nas tags dinâmicas.
   - **PROIBIDO variações redundantes:** Não adicionar tags que dizem a mesma coisa de forma repetitiva (como `#AprenderIngles`, `#InglesDoDiaADia`, `#EnglishTips` todas juntas).
   - Manter no máximo **6 a 7 hashtags no total** para um visual limpo, profissional e sem poluição.

### C. Prompt Otimizado para Geração de Thumbnail no Gemini
O usuário gera a capa no Gemini para ter estética cinematográfica 4K e letreiros chamativos. A IA deve gerar um prompt sob medida combinando uma **Estrutura Fixa (Branding)** com **Variáveis Dinâmicas por Episódio** para evitar "Cegueira de Padrão" no feed do YouTube.

> **Regra de Ouro: Coerência Temática + Complementaridade de Ângulos:**
> O título e a thumbnail **DEVEM tratar rigorosamente do MESMO tema central**. O que muda é o **ângulo de ataque**:
> - **Thumbnail:** Ataque emocional, reação rápida, choque, dúvida direta ou o erro falado em destaque (lido em 0,3 segundo pelo cérebro).
> - **Título:** Contexto, exemplo prático real, consequência do erro e o termo de busca (SEO).
> - **Exemplo de sucesso com ângulos opostos/complementares:**
>   - **Thumbnail (Dúvida / Dilema):** `"QUAL É O CERTO?"` + `"SAY OU TELL?"`
>   - **Título (Anti-erro prático / Consequência):** `NUNCA diga "They say me" em inglês! Pare de errar isso | Pod #<N>`
>   *(A capa foca na dúvida direta e o título foca no erro prático a ser evitado, sem repetir palavras nem soar redundante).*

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
   - O cenário da thumbnail deve ser composto a partir da locação real do vídeo de fundo do YouTube fornecido pelo usuário. A IA extrai o local e a atmosfera do título/descrição desse vídeo e usa essa informação como base do cenário.
   - **Variações visuais para manter frescor e evitar repetição:**
     - **Distância Focal e Enquadramento:** Alternar entre *plano geral aberto (wide angle / vista ampla da avenida ou horizonte)*, *plano médio (visão de pedestre com calçada/arquitetura próxima)* e *enquadramento com foco em elemento urbano específico (close em semáforo, placa de trânsito, fachada icônica com desfoque de fundo)*.
     - **Momento do Dia e Luz Natural:** Dia ensolarado com cores vivas e sombras nítidas, dia nublado/cinematográfico com iluminação suave, entardecer/golden hour com tons alaranjados/dourados, ou noite urbana com profundidade de luzes artificiais.
     - **Temperatura e Iluminação de Cena:** Variar entre tons quentes (luzes âmbar/amarelas de postes e vitrines) e tons frios/modernos (luzes de LED brancas/azuladas, neon, asfalto molhado refletindo luz).

3. **Variável C — Elemento Humano: Carlos de Alcântara (Sempre Presente):**
   - A thumbnail **sempre** apresenta o Carlos de Alcântara como elemento humano principal. Assuma que, junto com o prompt gerado, o usuário está enviando uma foto real do Carlos para o Gemini usar como referência de identidade facial, corte de cabelo e aparência.
   - O prompt instrui o Gemini a manter fidelidade absoluta ao rosto da foto enviada, adaptando apenas a pose e expressão (dúvida, espanto, alerta, questionamento) conforme o tema do episódio, integrando o personagem ao cenário e iluminação do fundo.

---

#### Modelo do Prompt Gerado pela IA:

```text
Gere uma thumbnail de alto impacto para o YouTube no formato 16:9 widescreen (1280x720).

Instrução de Pessoa (Imagem de referência enviada junto com este prompt):
- Utilize a pessoa da imagem enviada como referência exata de identidade facial, cabelo e aparência. Mantenha fidelidade absoluta ao rosto da foto.
- Posicione-a no lado direito da cena, em plano médio, olhando para a câmera com uma expressão expressiva de [dúvida / choque / alerta / curiosidade / questionamento], [descrever gesto natural: apontando para o texto / mão no queixo / braços abertos], de forma que não sobreponha os textos à esquerda.
- A iluminação e os reflexos do ambiente devem se integrar realisticamente à pessoa.

Cenário e Fundo (Baseado no local real do vídeo: [Local e atração extraídos do vídeo]):
- Fotografia realista e cinematográfica em 4K em [Local real do vídeo].
- Elementos visuais e atmosfera: [Descrever elementos fiéis do vídeo: ex. arquitetura, veículos, iluminação cênica / luz natural, etc.].
- Profundidade de campo: desfoque suave (bokeh) no fundo para destacar a pessoa e a tipografia.

Textos Gráficos Integrados (Lado Esquerdo, sem cobrir a pessoa):
- No centro-esquerda, em tipografia 3D gigante estilo pôster moderno em amarelo vibrante (ou branco) com contorno preto espesso e sombra projetada:
  "[FRASE CLICKBAIT COMPLEMENTAR / REAÇÃO HUMANA / GANCHO EMOTIVO]"
- Logo abaixo em uma tarja contrastante [COR ESCOLHIDA: Vermelha / Azul Elétrico / Verde Neon / Amarelo Alerta] com letras em caixa alta:
  "[DÚVIDA RÁPIDA OU ALERTA IMPACTANTE EM TEXTO CONTRASTANTE]"
- No canto superior esquerdo: selo discreto "POD #<N> • DICA RÁPIDA"
- No canto inferior esquerdo: tag "Carlos de Alcântara | @ei.ingles"

Estilo geral: Fotografia realista 4K combinada com design arrojado e viral de capa do YouTube, alto contraste e legibilidade perfeita na tela pequena do celular.
```

---

## 4. Dados e Identidade do Projeto
- **Nome do Autor:** Carlos de Alcântara
- **Instagram:** `@ei.ingles` (`https://instagram.com/ei.ingles`)
- **Site:** `https://viaEi.com`
- **Página de Links / Grupos:** `https://viaEi.com/links`
- **Página da Mentoria:** `https://viaEi.com/mentoria`

