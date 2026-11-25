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
import json
import argparse
import subprocess
import re
from pathlib import Path
from faster_whisper import WhisperModel

try:
    import numpy as np
except ImportError:
    np = None

def get_downloads_dir():
    home = Path.home()
    downloads = home / "Downloads"
    if not downloads.exists():
        downloads.mkdir(parents=True, exist_ok=True)
    return downloads

# Garante que CTranslate2 use oneDNN em vez do MKL no Windows, eliminando 100% dos erros de mkl_malloc
os.environ["CT2_USE_MKL"] = "0"
os.environ["KMP_DUPLICATE_LIB_OK"] = "TRUE"

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
    """Roda o Faster-Whisper usando exclusivamente o modelo large-v3-turbo 100% offline."""
    print("🎧 [1/5] Transcrevendo áudio via Faster-Whisper (large-v3-turbo local 100% offline)...")
    try:
        model = WhisperModel("large-v3-turbo", device="cpu", compute_type="int8", cpu_threads=2, local_files_only=True)
        segments, _info = model.transcribe(
            audio_path,
            beam_size=1,
            condition_on_previous_text=False,
            vad_filter=True,
            vad_parameters=dict(min_silence_duration_ms=500),
            no_speech_threshold=0.6
        )
        res = list(segments)
        print("✅ Transcrição concluída com sucesso com large-v3-turbo!")
        return res
    except Exception as e:
        # Se por acaso falhar local_files_only na inicialização direta, tenta sem a flag mas mantendo o modelo único
        print(f"⚠️ Tentando carregar modelo large-v3-turbo: {e}")
        model = WhisperModel("large-v3-turbo", device="cpu", compute_type="int8", cpu_threads=2)
        segments, _info = model.transcribe(
            audio_path,
            beam_size=1,
            condition_on_previous_text=False,
            vad_filter=True,
            vad_parameters=dict(min_silence_duration_ms=500),
            no_speech_threshold=0.6
        )
        res = list(segments)
        print("✅ Transcrição concluída com sucesso!")
        return res

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

def destutter_pcm(audio, sample_rate=48000):
    """
    Detecta e suaviza gaguejos de gravação (frames repetidos de 20 ms causados por
    PLC/Opus DTX ou captura Bluetooth/Android nas retomadas de silêncio),
    sem afetar vogais sustentadas na fala contínua.
    """
    if np is None or len(audio) < sample_rate // 10:
        return audio

    step = int(sample_rate * 0.020)  # blocos de 20 ms (960 amostras @ 48 kHz)
    n_blocks = len(audio) // step
    if n_blocks < 3:
        return audio

    blocks = audio[:n_blocks * step].reshape(n_blocks, step)
    norms = np.linalg.norm(blocks, axis=1)
    rms_all = norms / np.sqrt(step)

    out = audio.copy()
    cleaned_runs = 0
    i = 0
    while i < n_blocks - 1:
        if rms_all[i] > 0.02 and rms_all[i + 1] > 0.02:
            sim = float(np.dot(blocks[i], blocks[i + 1]) / (norms[i] * norms[i + 1]))
            if sim >= 0.985:
                j = i + 1
                while (
                    j < n_blocks - 1
                    and rms_all[j] > 0.02
                    and rms_all[j + 1] > 0.02
                    and float(np.dot(blocks[j], blocks[j + 1]) / (norms[j] * norms[j + 1])) >= 0.985
                ):
                    j += 1

                prev_min = float(np.min(rms_all[max(0, i - 4):i])) if i > 0 else 0.0
                next_min = float(np.min(rms_all[j + 1:min(n_blocks, j + 5)])) if j + 1 < n_blocks else 0.0
                pk = float(np.max(np.abs(blocks[i:j + 1])))

                # Só atua junto a pausas de silêncio (nunca no meio de vogais contínuas)
                if prev_min < 0.015 or next_min < 0.015:
                    isolated_burst = (prev_min < 0.015 and next_min < 0.015)
                    severe_stutter = (pk >= 0.65 or sim >= 0.995)

                    if isolated_burst or severe_stutter:
                        s_idx = max(0, i * step - int(sample_rate * 0.010))
                        e_idx = min(len(out), (j + 1) * step + int(sample_rate * 0.010))
                        fade = min(int(sample_rate * 0.005), (e_idx - s_idx) // 3)
                        env = np.full(e_idx - s_idx, 0.01, dtype=np.float32)
                        if fade > 0:
                            t_in = 0.5 * (1.0 + np.cos(np.linspace(0.0, np.pi, fade, dtype=np.float32)))
                            t_out = 0.5 * (1.0 - np.cos(np.linspace(0.0, np.pi, fade, dtype=np.float32)))
                            env[:fade] = 0.01 + 0.99 * t_in
                            env[-fade:] = 0.01 + 0.99 * t_out
                        out[s_idx:e_idx] *= env
                        cleaned_runs += 1

                i = j + 1
                continue
        i += 1

    if cleaned_runs:
        print(f"   🧹 De-stutter: {cleaned_runs} engasgo(s) de frame repetido suavizado(s) na origem")
    return out


def prepare_clean_audio(audio_path, clean_wav_path, speed=1.0):
    """
    Executa a restauração acústica completa em 1 passo (gerando WAV float32 limpo):
      1. De-stutter Python (elimina gaguejos de 20 ms repetidos nas pausas)
      2. adeclip=threshold=10 (limiar real para reconstruir topos clipados no celular)
      3. adeclick=threshold=2:burst=2 (suaviza microcliques impulsivos)
      4. highpass=85Hz / lowpass=12000Hz (corta batidas graves e chiado estático)
      5. acompressor suave (nivela a voz antes do atempo)
      6. atempo=speed (acelera sobre sinal restaurado)
      7. alimiter=limit=0.40 (-7.96 dBFS) APÓS o atempo (absorve picos WSOLA e cria
         headroom obrigatório para o loudnorm permanecer 100% em modo LINEAR)
    """
    print("🛠️  [2.2/5] Restaurando áudio (de-stutter + adeclip + adeclick + compressor + limiter)...")
    raw_cmd = [
        "ffmpeg", "-v", "error", "-i", audio_path,
        "-f", "f32le", "-ac", "1", "-ar", "48000", "pipe:1"
    ]
    raw_bytes = subprocess.run(raw_cmd, capture_output=True, check=True).stdout

    if np is not None:
        pcm = np.frombuffer(raw_bytes, dtype=np.float32).copy()
        pcm = destutter_pcm(pcm, sample_rate=48000)
        input_bytes = pcm.tobytes()
    else:
        input_bytes = raw_bytes

    chain = [
        "adeclip=threshold=10",
        "adeclick=threshold=2:burst=2",
        "highpass=f=85",
        "lowpass=f=12000",
        "acompressor=threshold=0.125:ratio=3:attack=2:release=80:makeup=1.5",
    ]
    if speed != 1.0:
        chain.append(f"atempo={speed}")
    chain.append("alimiter=limit=0.40:attack=3:release=50:level=disabled")

    ff_cmd = [
        "ffmpeg", "-y", "-v", "error",
        "-f", "f32le", "-ar", "48000", "-ac", "1", "-i", "pipe:0",
        "-af", ",".join(chain),
        "-c:a", "pcm_f32le",
        str(clean_wav_path)
    ]
    res = subprocess.run(ff_cmd, input=input_bytes, capture_output=True)
    if res.returncode != 0:
        raise RuntimeError(f"Falha ao preparar áudio limpo: {res.stderr.decode(errors='ignore')}")
    return str(clean_wav_path)


def measure_loudnorm(clean_wav_path):
    """
    Passo 1 do loudnorm de dois passes sobre o áudio já restaurado e acelerado.
    Verifica matematicamente se `measured_TP + (-16 - measured_I) <= -1.55` para
    garantir que o Passo 2 execute estritamente em modo LINEAR (nunca Dynamic).
    Se o áudio vier extremamente baixo (onde loudnorm forçaria modo dynamic),
    usa ganho linear estático + alimiter transparente, garantindo 0% de pumping.
    Sempre finaliza com `aresample=48000` para evitar exportação AAC em 96 kHz.
    """
    print("🔬 [2.5/5] Medindo loudness do áudio restaurado (verificação de linearidade)...")
    af_chain = "loudnorm=I=-16:TP=-1.5:LRA=11:print_format=json"

    res = subprocess.run(
        ["ffmpeg", "-i", clean_wav_path, "-af", af_chain, "-f", "null", "-"],
        capture_output=True, text=True
    )
    output = res.stderr
    match = re.search(r'\{[^{}]+\}', output, re.DOTALL)
    if not match:
        print("⚠️  Medição loudnorm falhou — usando ganho estático seguro + limiter.")
        return "volume=2.5dB,alimiter=limit=0.84:attack=3:release=50:level=disabled,aresample=48000"

    stats = json.loads(match.group())
    il = stats.get("input_i", "-20.0")
    itp = stats.get("input_tp", "-7.5")
    ilra = stats.get("input_lra", "3.5")
    ith = stats.get("input_thresh", "-31.0")
    offset = stats.get("target_offset", "0.0")

    needed_gain = -16.0 - float(il)
    projected_tp = float(itp) + needed_gain

    if projected_tp <= -1.55 and float(ilra) <= 11.0:
        audio_filter = (
            f"loudnorm=I=-16:TP=-1.5:LRA=11:linear=true"
            f":measured_I={il}:measured_TP={itp}"
            f":measured_LRA={ilra}:measured_thresh={ith}"
            f":offset={offset}:print_format=none,aresample=48000"
        )
        print(
            f"   📊 Input: {il} LUFS | TP: {itp} dBTP | LRA: {ilra} LU "
            f"(proj TP: {projected_tp:+.2f} dBTP) → normalização LINEAR confirmada @ 48 kHz"
        )
    else:
        audio_filter = (
            f"volume={needed_gain:.2f}dB,"
            f"alimiter=limit=0.84:attack=3:release=50:level=disabled,aresample=48000"
        )
        print(
            f"   📊 Input baixo ({il} LUFS, proj TP: {projected_tp:+.2f} dBTP) → "
            f"ganho estático linear (+{needed_gain:.2f} dB) + true-peak limiter @ 48 kHz (zero dynamic)"
        )

    return audio_filter


def _render_video(bg_path, clean_audio_path, ass_path, duration_sec, output_file, speed=1.0, audio_filter=None, crop_vertical=False):
    """Helper compartilhado de renderização FFmpeg para horizontal (16:9) e vertical (9:16)."""
    ass_clean = ass_path.replace("\\", "/").replace(":", "\\:")
    target_duration = duration_sec / speed
    af = audio_filter or "loudnorm=I=-16:TP=-1.5:LRA=11,aresample=48000"

    v_prefix = "crop=ih*9/16:ih,scale=1080:1920," if crop_vertical else ""
    pts_suffix = f",setpts=PTS/{speed}" if speed != 1.0 else ""
    filter_complex = f"[0:v]{v_prefix}subtitles={ass_clean}{pts_suffix}[v];[1:a]{af}[a]"

    cmd = [
        "ffmpeg", "-y",
        "-i", bg_path,
        "-i", clean_audio_path,
        "-t", str(target_duration),
        "-filter_complex", filter_complex,
        "-map", "[v]",
        "-map", "[a]",
        "-c:v", "libx264",
        "-preset", "veryfast",
        "-x264-params", "threads=4:rc-lookahead=10",
        "-crf", "24",
        "-c:a", "aac",
        "-b:a", "128k",
        str(output_file)
    ]
    subprocess.run(cmd, check=True)


def render_horizontal(bg_path, clean_audio_path, ass_path, duration_sec, output_file, speed=1.0, audio_filter=None):
    """Renderiza o vídeo horizontal 16:9 (1920x1080) para YouTube."""
    print(f"🎬 [3/5] Renderizando vídeo HORIZONTAL 16:9 (velocidade: {speed}x)...")
    _render_video(bg_path, clean_audio_path, ass_path, duration_sec, output_file, speed=speed, audio_filter=audio_filter, crop_vertical=False)
    print(f"✅ Horizontal exportado: {output_file}")


def render_vertical(bg_path, clean_audio_path, ass_path, duration_sec, output_file, speed=1.0, audio_filter=None):
    """Renderiza o vídeo vertical 9:16 (1080x1920) para Reels / Shorts / TikTok."""
    print(f"📱 [4/5] Renderizando vídeo VERTICAL 9:16 (velocidade: {speed}x)...")
    _render_video(bg_path, clean_audio_path, ass_path, duration_sec, output_file, speed=speed, audio_filter=audio_filter, crop_vertical=True)
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
        write_ass(build_ass_events(segments, dur, args.pod, mode="horizontal"), ass_h_path)
        write_ass(build_ass_events(segments, dur, args.pod, mode="vertical"), ass_v_path)
        print(f"✅ [2/5] Arquivos ASS gerados: horizontal + vertical")

    # Baixa o vídeo de fundo (compartilhado entre os dois renders)
    bg = download_background(args.url, str(temp_dir))

    speed = args.speed
    if speed != 1.0:
        print(f"⚡ Aceleração ativa: {speed}x (legenda queimada primeiro, depois frames acelerados)")

    clean_wav_path = str(temp_dir / f"pod_{args.pod}_clean.wav")
    try:
        # 1) De-stutter + restauração acústica + compressão + atempo + limiter pós-WSOLA
        prepare_clean_audio(audio_file, clean_wav_path, speed=speed)

        # 2) Pré-análise de loudness com trava anti-dynamic + reamostragem 48 kHz
        af_final = measure_loudnorm(clean_wav_path)

        # 3) Renderiza ambos usando o áudio limpo já pré-acelerado
        render_horizontal(bg, clean_wav_path, ass_h_path, dur, final_h, speed=speed, audio_filter=af_final)
        render_vertical(bg, clean_wav_path, ass_v_path, dur, final_v, speed=speed, audio_filter=af_final)
    finally:
        if os.path.exists(clean_wav_path):
            os.remove(clean_wav_path)

    duration_final = dur / speed
    print()
    print("=" * 60)
    print(f"🎉 [5/5] PIPELINE CONCLUÍDO!")
    print(f"   📺 YouTube (16:9):         {final_h}")
    print(f"   📱 Reels/Shorts (9:16):    {final_v}")
    if speed != 1.0:
        print(f"   ⚡ Velocidade:              {speed}x  ({dur:.0f}s → {duration_final:.0f}s)")
    print("=" * 60)

