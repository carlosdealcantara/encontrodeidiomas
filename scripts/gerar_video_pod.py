"""
Pipeline Automatizado: Pill of the Day (Áudio -> Vídeo YouTube com Legenda Dinâmica e Branding)
Gera DOIS vídeos por episódio:
  - Horizontal 16:9 → ~/Downloads/pod<N>.mp4  (YouTube)
  - Vertical   9:16 → ~/Downloads/pod<N>v.mp4 (Reels / Shorts / TikTok)

Uso:
    python scripts/gerar_video_pod.py --pod 1 --url "https://www.youtube.com/watch?v=..."
    python scripts/gerar_video_pod.py --pod 2 --audio caminho/audio.ogg --url "https://..."
"""

import os
import sys
import argparse
import subprocess
import re
from pathlib import Path
from faster_whisper import WhisperModel

def get_downloads_dir():
    home = Path.home()
    downloads = home / "Downloads"
    if not downloads.exists():
        downloads.mkdir(parents=True, exist_ok=True)
    return downloads

def to_ass_time(seconds):
    h = int(seconds // 3600)
    m = int((seconds % 3600) // 60)
    s = seconds % 60
    return f"{h:01d}:{m:02d}:{s:05.2f}"

def get_audio_duration(audio_path):
    cmd = [
        "ffprobe", "-v", "error", "-show_entries", "format=duration",
        "-of", "default=noprint_wrappers=1:nokey=1", audio_path
    ]
    res = subprocess.run(cmd, capture_output=True, text=True, check=True)
    return float(res.stdout.strip())

def transcribe_audio(audio_path):
    """Roda o Faster-Whisper e retorna lista de segmentos com fallback resiliente de modelos e beam_size."""
    print("🎧 [1/5] Transcrevendo áudio via Faster-Whisper (100% offline)...")
    model_configs = [
        ("medium", 5),
        ("small", 5),
        ("base", 1),
        ("tiny", 1)
    ]
    
    last_err = None
    for model_name, b_size in model_configs:
        try:
            print(f"Tentando carregar modelo '{model_name}'...")
            model = WhisperModel(model_name, device="cpu", compute_type="int8")
            segments, _info = model.transcribe(
                audio_path,
                beam_size=b_size,
                condition_on_previous_text=False,
                vad_filter=True,
                vad_parameters=dict(min_silence_duration_ms=500),
                no_speech_threshold=0.6
            )
            res = list(segments)
            print(f"✅ Transcrição concluída com sucesso usando modelo '{model_name}' (beam_size={b_size})!")
            return res
        except (RuntimeError, MemoryError) as e:
            print(f"⚠️ Erro ao transcrever com '{model_name}': {e}. Tentando próximo modelo de fallback...")
            last_err = e

    raise RuntimeError(f"Não foi possível transcrever com nenhum modelo: {last_err}")

def build_ass_events(segments, duration_sec, pod_num, mode="horizontal"):
    """
    Constrói o bloco [Events] do arquivo ASS.
    mode: "horizontal" → branding top-left, legendas center-screen (Alignment=5)
          "vertical"   → branding top-center, legendas center-screen (Alignment=5)
    """
    max_t = to_ass_time(duration_sec)

    if mode == "vertical":
        # Branding: Alignment=8 (top-center, FontSize=56, MarginV=140 para safe zone de Reels/Shorts/TikTok)
        # Subtitle:  Alignment=5 (center-screen, posição de destaque para Reels/Shorts)
        header = f"""[Script Info]
ScriptType: v4.00+
PlayResX: 1080
PlayResY: 1920
ScaledBorderAndShadow: yes

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Branding,Arial,56,&H00FFFFFF,&H000000FF,&H00000000,&H80000000,-1,0,0,0,100,100,1,0,3,10,0,8,30,30,140,1
Style: Subtitle,Arial,90,&H00FFFFFF,&H000000FF,&H00000000,&H80000000,-1,0,0,0,100,100,0,0,1,3.5,2,5,40,40,0,1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
"""
        branding_line = f"Dialogue: 0,0:00:00.00,{max_t},Branding,,0,0,0,,POD #{pod_num}   |   CARLOS DE ALCANTARA"
    else:
        # Horizontal (YouTube 16:9): Branding FontSize=44 (100% maior que os 22 originais), Subtitle Alignment=5
        header = f"""[Script Info]
ScriptType: v4.00+
PlayResX: 1920
PlayResY: 1080
ScaledBorderAndShadow: yes

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Branding,Arial,44,&H00FFFFFF,&H000000FF,&H00000000,&H80000000,-1,0,0,0,100,100,1,0,3,10,0,7,45,45,40,1
Style: Subtitle,Arial,90,&H00FFFFFF,&H000000FF,&H00000000,&H80000000,-1,0,0,0,100,100,0,0,1,3.5,2,5,60,60,0,1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
"""
        branding_line = f"Dialogue: 0,0:00:00.00,{max_t},Branding,,0,0,0,,POD #{pod_num}   |   CARLOS DE ALCANTARA"

    events = [branding_line]

    for seg in segments:
        text = seg.text.strip()
        if "bill of the day" in text.lower():
            text = re.sub(r'bill of the day', 'Pill of the Day', text, flags=re.IGNORECASE)

        words = text.split()
        if not words:
            continue

        total_duration = seg.end - seg.start
        chunk_size = 5
        word_chunks = [words[i:i + chunk_size] for i in range(0, len(words), chunk_size)]
        time_per_word = total_duration / max(len(words), 1)

        curr_t = seg.start
        for wc in word_chunks:
            chunk_text = " ".join(wc)
            chunk_dur = len(wc) * time_per_word
            s_str = to_ass_time(curr_t)
            e_str = to_ass_time(curr_t + chunk_dur)
            events.append(f"Dialogue: 1,{s_str},{e_str},Subtitle,,0,0,0,,{chunk_text}")
            curr_t += chunk_dur

    return header + "\n".join(events) + "\n"

def write_ass(content, path):
    with open(path, "w", encoding="utf-8") as f:
        f.write(content)

def download_background(youtube_url, temp_dir):
    print(f"📥 [2/5] Baixando vídeo de fundo ({youtube_url})...")
    bg_path = os.path.join(temp_dir, "bg_video.mp4")
    # Usamos um template sem extensão fixa para que o yt-dlp possa baixar
    # vídeo e áudio separadamente (formato DASH) e mesclá-los internamente via ffmpeg.
    # IMPORTANTE: NÃO usar --extractor-args com cliente android/ios pois o YouTube
    # ativa o experimento SABR nesses clientes, bloqueando formatos DASH e forçando
    # fallback para o formato combinado 360p (formato 18). O cliente padrão (visionOS/web)
    # tem acesso completo a todos os formatos DASH, incluindo 1080p+.
    # Seletor de formato: prefere H.264 MP4 1080p (compatibilidade máxima) + áudio M4A.
    bg_template = os.path.join(temp_dir, "bg_video.%(ext)s")
    cmd = [
        sys.executable, "-m", "yt_dlp",
        "-f", (
            "bestvideo[ext=mp4][height<=1080]+bestaudio[ext=m4a]"
            "/bestvideo[ext=mp4][height<=1080]+bestaudio"
            "/bestvideo[height<=1080]+bestaudio"
            "/best[height<=1080]"
        ),
        "--merge-output-format", "mp4",
        "-o", bg_template,
        "--force-overwrites",
        youtube_url
    ]
    subprocess.run(cmd, check=True)

    # Após o merge o arquivo final sempre termina em .mp4
    if not os.path.exists(bg_path):
        # Fallback: procura qualquer bg_video.* gerado
        candidates = [
            f for f in os.listdir(temp_dir)
            if f.startswith("bg_video") and f.endswith(".mp4")
        ]
        if candidates:
            os.replace(os.path.join(temp_dir, candidates[0]), bg_path)
        else:
            raise FileNotFoundError(
                f"Vídeo de fundo não encontrado em {temp_dir} após o download. "
                "Verifique se o yt-dlp tem o ffmpeg disponível para merge."
            )
    return bg_path

def measure_loudnorm(audio_path, speed=1.0):
    """
    Passo 1 do loudnorm de dois passes: mede os parâmetros reais do áudio
    (Integrated LUFS, True Peak, LRA) e retorna o filtro loudnorm parametrizado
    para o passo 2, eliminando completamente o modo Dynamic que causa chiados.

    O modo Dynamic do loudnorm de passe único aplica ganho variável frame a frame.
    Nas transições silêncio → voz alta (comuns em áudios de celular), esse ganho
    sobe drasticamente e clipar/distorce exatamente como um "rádio saindo de sintonia".
    Com dois passes, o ganho é fixo e linear — zero distorção dinâmica.

    Cadeia de restauração inicial:
      - adeclip=threshold=95 → reconstrói topos cortados/achatados de ondas clipadas no microfone
      - volume=-1.5dB        → cria margem de segurança contra novos inter-sample peaks
      - highpass=f=80        → corta sub-graves de vento/puffs
      - lowpass=f=12000      → elimina ruídos de ultra-alta frequência e chiados de rádio
      - alimiter             → limitador suave analógico
    """
    print("🔬 [2.5/5] Medindo loudness do áudio (pré-análise com adeclip para normalização linear)...")
    base = "adeclip=threshold=95,volume=-1.5dB,highpass=f=80,lowpass=f=12000,alimiter=level_in=1:level_out=1:limit=0.9:attack=5:release=50:level=disabled"
    if speed != 1.0:
        af_chain = f"{base},atempo={speed},loudnorm=I=-16:TP=-1.5:LRA=11:print_format=json"
    else:
        af_chain = f"{base},loudnorm=I=-16:TP=-1.5:LRA=11:print_format=json"

    res = subprocess.run(
        ["ffmpeg", "-i", audio_path, "-af", af_chain, "-f", "null", "-"],
        capture_output=True, text=True
    )
    # Extrai o bloco JSON do stderr
    output = res.stderr
    import json, re
    match = re.search(r'\{[^{}]+\}', output, re.DOTALL)
    if not match:
        print("⚠️  Medição loudnorm falhou — usando modo single-pass como fallback.")
        return f"loudnorm=I=-16:TP=-1.5:LRA=11"

    stats = json.loads(match.group())
    il  = stats.get("input_i",  "-17.0")
    itp = stats.get("input_tp", "-1.0")
    ilra = stats.get("input_lra", "4.5")
    ith = stats.get("input_thresh", "-28.0")
    offset = stats.get("target_offset", "0.0")

    # Passo 2: loudnorm linear (measured) — ganho fixo, sem variação dinâmica = zero chiado
    loudnorm_pass2 = (
        f"loudnorm=I=-16:TP=-1.5:LRA=11:linear=true"
        f":measured_I={il}:measured_TP={itp}"
        f":measured_LRA={ilra}:measured_thresh={ith}"
        f":offset={offset}:print_format=none"
    )
    print(f"   📊 Input: {il} LUFS | TP: {itp} dBTP | LRA: {ilra} LU → normalização LINEAR (zero chiado)")
    return loudnorm_pass2


def render_horizontal(bg_path, audio_path, ass_path, duration_sec, output_file, speed=1.0, loudnorm_filter=None):
    """
    Renderiza o vídeo horizontal 16:9 (1920x1080) para YouTube.
    speed: fator de aceleração (ex: 1.2 = 20% mais rápido). 1.0 = sem alteração.
    Ordem do filtro: queima a legenda com PTS original → depois acelera os frames.
    Assim a legenda já está gravada como pixel e acompanha automaticamente.

    Cadeia de áudio (reconstrução acústica + 2 passes lineares):
      1. adeclip           → reconstrói curvas de onda clipadas no microfone do celular
      2. volume=-1.5dB     → headroom de segurança para interpolação analógica
      3. highpass=80Hz     → corta sub-graves de proximidade e vento
      4. lowpass=12000Hz   → corta chiado de rádio e hiss estático
      5. alimiter          → limita transientes residuais
      6. atempo=N          → aceleração temporal limpa sobre sinal já restaurado
      7. loudnorm LINEAR   → ganho estático pré-medido, sem flutuação dinâmica
    """
    print(f"🎬 [3/5] Renderizando vídeo HORIZONTAL 16:9 (velocidade: {speed}x)...")
    ass_clean = ass_path.replace("\\", "/").replace(":", "\\:")
    target_duration = duration_sec / speed

    ln = loudnorm_filter or "loudnorm=I=-16:TP=-1.5:LRA=11"
    audio_base = "adeclip=threshold=95,volume=-1.5dB,highpass=f=80,lowpass=f=12000,alimiter=level_in=1:level_out=1:limit=0.9:attack=5:release=50:level=disabled"

    if speed != 1.0:
        v_filter = f"[0:v]subtitles={ass_clean},setpts=PTS/{speed}[v]"
        a_filter = f"[1:a]{audio_base},atempo={speed},{ln}[a]"
        filter_complex = f"{v_filter};{a_filter}"
        audio_map = "[a]"
    else:
        filter_complex = f"[0:v]subtitles={ass_clean}[v];[1:a]{audio_base},{ln}[a]"
        audio_map = "[a]"

    cmd = [
        "ffmpeg", "-y",
        "-i", bg_path,
        "-i", audio_path,
        "-t", str(target_duration),
        "-filter_complex", filter_complex,
        "-map", "[v]",
        "-map", audio_map,
        "-c:v", "libx264",
        "-preset", "veryfast",
        "-x264-params", "threads=4:rc-lookahead=10",
        "-crf", "24",
        "-c:a", "aac",
        "-b:a", "128k",
        str(output_file)
    ]
    subprocess.run(cmd, check=True)
    print(f"✅ Horizontal exportado: {output_file}")

def render_vertical(bg_path, audio_path, ass_path, duration_sec, output_file, speed=1.0, loudnorm_filter=None):
    """
    Renderiza o vídeo vertical 9:16 (1080x1920) para Reels / Shorts / TikTok.
    Recorta o centro da faixa horizontal (crop=ih*9/16:ih) e escala para 1080x1920.
    speed: fator de aceleração (ex: 1.2 = 20% mais rápido). 1.0 = sem alteração.

    Cadeia de áudio (reconstrução acústica + 2 passes lineares) — idêntica à horizontal.
    """
    print(f"📱 [4/5] Renderizando vídeo VERTICAL 9:16 (velocidade: {speed}x)...")
    ass_clean = ass_path.replace("\\", "/").replace(":", "\\:")
    target_duration = duration_sec / speed

    ln = loudnorm_filter or "loudnorm=I=-16:TP=-1.5:LRA=11"
    audio_base = "adeclip=threshold=95,volume=-1.5dB,highpass=f=80,lowpass=f=12000,alimiter=level_in=1:level_out=1:limit=0.9:attack=5:release=50:level=disabled"

    if speed != 1.0:
        v_filter = f"[0:v]crop=ih*9/16:ih,scale=1080:1920,subtitles={ass_clean},setpts=PTS/{speed}[v]"
        a_filter = f"[1:a]{audio_base},atempo={speed},{ln}[a]"
        filter_complex = f"{v_filter};{a_filter}"
        audio_map = "[a]"
    else:
        filter_complex = f"[0:v]crop=ih*9/16:ih,scale=1080:1920,subtitles={ass_clean}[v];[1:a]{audio_base},{ln}[a]"
        audio_map = "[a]"

    cmd = [
        "ffmpeg", "-y",
        "-i", bg_path,
        "-i", audio_path,
        "-t", str(target_duration),
        "-filter_complex", filter_complex,
        "-map", "[v]",
        "-map", audio_map,
        "-c:v", "libx264",
        "-preset", "veryfast",
        "-x264-params", "threads=4:rc-lookahead=10",
        "-crf", "24",
        "-c:a", "aac",
        "-b:a", "128k",
        str(output_file)
    ]
    subprocess.run(cmd, check=True)
    print(f"✅ Vertical exportado: {output_file}")

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Automação Pod -> Vídeo YouTube + Reels/Shorts")
    parser.add_argument("--pod", type=int, required=True, help="Número do episódio (ex: 1, 2, 3...)")
    parser.add_argument("--audio", help="Caminho local do áudio (padrão: scratch/pod_test/pod_X.ogg)")
    parser.add_argument("--url", required=True, help="URL do vídeo Creative Commons do YouTube")
    parser.add_argument("--output", help="Caminho customizado para o vídeo horizontal (padrão: ~/Downloads/podX.mp4)")
    parser.add_argument("--speed", type=float, default=1.2,
                        help="Fator de aceleração do vídeo (ex: 1.2 = 20%% mais rápido). Padrão: 1.2 (otimizado para retenção)")

    args = parser.parse_args()

    # --- Resolve áudio ---
    audio_file = args.audio if args.audio else f"scratch/pod_test/pod_{args.pod}.ogg"
    if not os.path.exists(audio_file):
        print(f"❌ Erro: Arquivo de áudio não encontrado em '{audio_file}'.")
        sys.exit(1)

    # --- Resolve destinos de saída ---
    downloads_folder = get_downloads_dir()
    final_h = Path(args.output) if args.output else downloads_folder / f"pod{args.pod}.mp4"
    final_v = downloads_folder / f"pod{args.pod}v.mp4"

    # --- Diretório temporário ---
    temp_dir = Path("scratch/pod_test")
    temp_dir.mkdir(parents=True, exist_ok=True)

    # --- Pipeline ---
    dur = get_audio_duration(audio_file)
    ass_h_path = str(temp_dir / f"subtitles_pod{args.pod}_h.ass")
    ass_v_path = str(temp_dir / f"subtitles_pod{args.pod}_v.ass")

    if os.path.exists(ass_h_path) and os.path.exists(ass_v_path):
        print(f"ℹ️ Arquivos ASS já encontrados em cache ({ass_h_path}, {ass_v_path}). Pulando transcrição...")
    else:
        segments = transcribe_audio(audio_file)
        # Gera ASS horizontal
        write_ass(build_ass_events(segments, dur, args.pod, mode="horizontal"), ass_h_path)
        # Gera ASS vertical (PlayRes 1080x1920, Alignment central)
        write_ass(build_ass_events(segments, dur, args.pod, mode="vertical"), ass_v_path)
        print(f"✅ [2/5] Arquivos ASS gerados: horizontal + vertical")

    # Baixa o vídeo de fundo (compartilhado entre os dois renders)
    bg = download_background(args.url, str(temp_dir))

    speed = args.speed
    if speed != 1.0:
        print(f"⚡ Aceleração ativa: {speed}x (legenda queimada primeiro, depois frames acelerados)")

    # Pré-análise de loudness: mede parâmetros reais do áudio para normalização LINEAR (2 passes)
    # Isso elimina completamente os chiados/engasgos do modo Dynamic do loudnorm single-pass
    ln_filter = measure_loudnorm(audio_file, speed=speed)

    # Renderiza ambos
    render_horizontal(bg, audio_file, ass_h_path, dur, final_h, speed=speed, loudnorm_filter=ln_filter)
    render_vertical(bg, audio_file, ass_v_path, dur, final_v, speed=speed, loudnorm_filter=ln_filter)

    duration_final = dur / speed
    print()
    print("=" * 60)
    print(f"🎉 [5/5] PIPELINE CONCLUÍDO!")
    print(f"   📺 YouTube (16:9):         {final_h}")
    print(f"   📱 Reels/Shorts (9:16):    {final_v}")
    if speed != 1.0:
        print(f"   ⚡ Velocidade:              {speed}x  ({dur:.0f}s → {duration_final:.0f}s)")
    print("=" * 60)

