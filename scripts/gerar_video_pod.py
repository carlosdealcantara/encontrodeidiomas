"""
Pipeline Automatizado: Pill of the Day (Áudio -> Vídeo YouTube com Legenda Dinâmica e Branding)
Gera DOIS vídeos por episódio:
  - Horizontal 16:9 → ~/Downloads/podN.mp4          (YouTube)
  - Vertical   9:16 → ~/Downloads/podN_vertical.mp4 (Reels / Shorts / TikTok)

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
    """Roda o Faster-Whisper e retorna lista de segmentos."""
    print("🎧 [1/5] Transcrevendo áudio via Faster-Whisper (100% offline)...")
    model = WhisperModel("small", device="cpu", compute_type="int8")
    segments, _info = model.transcribe(audio_path, beam_size=5)
    return list(segments)  # materializa o generator aqui para reusar

def build_ass_events(segments, duration_sec, pod_num, mode="horizontal"):
    """
    Constrói o bloco [Events] do arquivo ASS.
    mode: "horizontal" → branding top-left, legendas center-screen (Alignment=5)
          "vertical"   → branding top-center, legendas center-screen (Alignment=5)
    """
    max_t = to_ass_time(duration_sec)

    if mode == "vertical":
        # Branding: Alignment=8 (top-center)
        # Subtitle:  Alignment=5 (center-screen, posição de destaque para Reels/Shorts)
        header = f"""[Script Info]
ScriptType: v4.00+
PlayResX: 1080
PlayResY: 1920
ScaledBorderAndShadow: yes

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Branding,Arial,28,&H00FFFFFF,&H000000FF,&H00000000,&H80000000,-1,0,0,0,100,100,1,0,3,8,0,8,30,30,50,1
Style: Subtitle,Arial,90,&H00FFFFFF,&H000000FF,&H00000000,&H80000000,-1,0,0,0,100,100,0,0,1,3.5,2,5,40,40,0,1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
"""
        branding_line = f"Dialogue: 0,0:00:00.00,{max_t},Branding,,0,0,0,,POD #{pod_num}   |   CARLOS DE ALCANTARA"
    else:
        # Horizontal (YouTube 16:9): Subtitle Alignment=5 (center-screen como protagonista)
        header = f"""[Script Info]
ScriptType: v4.00+
PlayResX: 1920
PlayResY: 1080
ScaledBorderAndShadow: yes

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Branding,Arial,22,&H00FFFFFF,&H000000FF,&H00000000,&H80000000,-1,0,0,0,100,100,1,0,3,8,0,7,45,45,40,1
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
    cmd = [
        sys.executable, "-m", "yt_dlp",
        "-f", "bestvideo[ext=mp4][height<=1080]/bestvideo[height<=1080]/best[height<=1080]",
        "-o", bg_path,
        "--force-overwrites",
        youtube_url
    ]
    subprocess.run(cmd, check=True)
    return bg_path

def render_horizontal(bg_path, audio_path, ass_path, duration_sec, output_file, speed=1.0):
    """
    Renderiza o vídeo horizontal 16:9 (1920x1080) para YouTube.
    speed: fator de aceleração (ex: 1.2 = 20% mais rápido). 1.0 = sem alteração.
    Ordem do filtro: queima a legenda com PTS original → depois acelera os frames.
    Assim a legenda já está gravada como pixel e acompanha automaticamente.
    """
    print(f"🎬 [3/5] Renderizando vídeo HORIZONTAL 16:9 (velocidade: {speed}x)...")
    ass_clean = ass_path.replace("\\", "/").replace(":", "\\:")

    if speed != 1.0:
        # subtitles com PTS original → setpts acelera os frames já com legenda queimada
        # atempo acelera o áudio no mesmo fator
        v_filter = f"[0:v]subtitles={ass_clean},setpts=PTS/{speed}[v]"
        a_filter = f"[1:a]atempo={speed}[a]"
        filter_complex = f"{v_filter};{a_filter}"
        audio_map = "[a]"
    else:
        filter_complex = f"[0:v]subtitles={ass_clean}[v]"
        audio_map = "1:a"

    # ultrafast + threads=2 + rc-lookahead=0 → minimiza consumo de RAM (~2.5 GB free é suficiente)
    cmd = [
        "ffmpeg", "-y",
        "-i", bg_path,
        "-i", audio_path,
        "-t", str(duration_sec),
        "-filter_complex", filter_complex,
        "-map", "[v]",
        "-map", audio_map,
        "-c:v", "libx264",
        "-preset", "ultrafast",
        "-tune", "fastdecode",
        "-x264-params", "threads=2:rc-lookahead=0:bframes=0",
        "-crf", "23",
        "-c:a", "aac",
        "-b:a", "128k",
        str(output_file)
    ]
    subprocess.run(cmd, check=True)
    print(f"✅ Horizontal exportado: {output_file}")

def render_vertical(bg_path, audio_path, ass_path, duration_sec, output_file, speed=1.0):
    """
    Renderiza o vídeo vertical 9:16 (1080x1920) para Reels / Shorts / TikTok.
    Recorta o centro da faixa horizontal (crop=ih*9/16:ih) e escala para 1080x1920.
    speed: fator de aceleração (ex: 1.2 = 20% mais rápido). 1.0 = sem alteração.
    """
    print(f"📱 [4/5] Renderizando vídeo VERTICAL 9:16 (velocidade: {speed}x)...")
    ass_clean = ass_path.replace("\\", "/").replace(":", "\\:")

    if speed != 1.0:
        v_filter = f"[0:v]crop=ih*9/16:ih,scale=1080:1920,subtitles={ass_clean},setpts=PTS/{speed}[v]"
        a_filter = f"[1:a]atempo={speed}[a]"
        filter_complex = f"{v_filter};{a_filter}"
        audio_map = "[a]"
    else:
        filter_complex = f"[0:v]crop=ih*9/16:ih,scale=1080:1920,subtitles={ass_clean}[v]"
        audio_map = "1:a"

    cmd = [
        "ffmpeg", "-y",
        "-i", bg_path,
        "-i", audio_path,
        "-t", str(duration_sec),
        "-filter_complex", filter_complex,
        "-map", "[v]",
        "-map", audio_map,
        "-c:v", "libx264",
        "-preset", "ultrafast",
        "-tune", "fastdecode",
        "-x264-params", "threads=2:rc-lookahead=0:bframes=0",
        "-crf", "23",
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
    final_v = downloads_folder / f"pod{args.pod}_vertical.mp4"

    # --- Diretório temporário ---
    temp_dir = Path("scratch/pod_test")
    temp_dir.mkdir(parents=True, exist_ok=True)

    # --- Pipeline ---
    dur = get_audio_duration(audio_file)
    segments = transcribe_audio(audio_file)

    # Gera ASS horizontal
    ass_h_path = str(temp_dir / f"subtitles_pod{args.pod}_h.ass")
    write_ass(build_ass_events(segments, dur, args.pod, mode="horizontal"), ass_h_path)

    # Gera ASS vertical (PlayRes 1080x1920, Alignment central)
    ass_v_path = str(temp_dir / f"subtitles_pod{args.pod}_v.ass")
    write_ass(build_ass_events(segments, dur, args.pod, mode="vertical"), ass_v_path)

    print(f"✅ [2/5] Arquivos ASS gerados: horizontal + vertical")

    # Baixa o vídeo de fundo (compartilhado entre os dois renders)
    bg = download_background(args.url, str(temp_dir))

    speed = args.speed
    if speed != 1.0:
        print(f"⚡ Aceleração ativa: {speed}x (legenda queimada primeiro, depois frames acelerados)")

    # Renderiza ambos
    render_horizontal(bg, audio_file, ass_h_path, dur, final_h, speed=speed)
    render_vertical(bg, audio_file, ass_v_path, dur, final_v, speed=speed)

    duration_final = dur / speed
    print()
    print("=" * 60)
    print(f"🎉 [5/5] PIPELINE CONCLUÍDO!")
    print(f"   📺 YouTube (16:9):         {final_h}")
    print(f"   📱 Reels/Shorts (9:16):    {final_v}")
    if speed != 1.0:
        print(f"   ⚡ Velocidade:              {speed}x  ({dur:.0f}s → {duration_final:.0f}s)")
    print("=" * 60)

