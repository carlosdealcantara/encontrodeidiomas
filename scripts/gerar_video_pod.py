"""
Pipeline Automatizado: Pill of the Day (Áudio -> Vídeo YouTube com Legenda Dinâmica e Branding)
Uso:
    python scripts/gerar_video_pod.py --pod 1 --url "https://www.youtube.com/watch?v=..."
"""

import os
import sys
import argparse
import subprocess
import json
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

def transcribe_and_generate_ass(audio_path, temp_dir, pod_num, duration_sec):
    print("🎧 [1/4] Transcrevendo áudio via Faster-Whisper (100% offline)...")
    model = WhisperModel("small", device="cpu", compute_type="int8")
    segments, info = model.transcribe(audio_path, beam_size=5)

    header = """[Script Info]
ScriptType: v4.00+
PlayResX: 1920
PlayResY: 1080
ScaledBorderAndShadow: yes

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Branding,Arial,22,&H00FFFFFF,&H000000FF,&H00000000,&H80000000,-1,0,0,0,100,100,1,0,3,8,0,7,45,45,40,1
Style: Subtitle,Arial,32,&H00FFFFFF,&H000000FF,&H00000000,&H80000000,-1,0,0,0,100,100,0,0,1,3.5,2,2,60,60,60,1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
"""
    events = []
    max_t = to_ass_time(duration_sec)
    events.append(f"Dialogue: 0,0:00:00.00,{max_t},Branding,,0,0,0,,POD #{pod_num}   |   CARLOS DE ALCANTARA")

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

    ass_path = os.path.join(temp_dir, f"subtitles_pod{pod_num}.ass")
    with open(ass_path, "w", encoding="utf-8") as f:
        f.write(header + "\n".join(events) + "\n")
        
    print(f"✅ Legendas dinâmicas e branding gerados em: {ass_path}")
    return ass_path

def download_background(youtube_url, temp_dir):
    print(f"📥 [2/4] Baixando vídeo de fundo ({youtube_url})...")
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

def render_final_video(bg_path, audio_path, ass_path, duration_sec, output_file):
    print("🎬 [3/4] Renderizando vídeo com FFmpeg (Branding + Legenda Dinâmica)...")
    ass_clean = ass_path.replace("\\", "/").replace(":", "\\:")
    
    cmd = [
        "ffmpeg", "-y",
        "-i", bg_path,
        "-i", audio_path,
        "-t", str(duration_sec),
        "-filter_complex", f"[0:v]subtitles={ass_clean}[v]",
        "-map", "[v]",
        "-map", "1:a",
        "-c:v", "libx264",
        "-preset", "fast",
        "-crf", "20",
        "-c:a", "aac",
        "-b:a", "192k",
        str(output_file)
    ]
    subprocess.run(cmd, check=True)
    print(f"🎉 [4/4] Vídeo final exportado diretamente para: {output_file}")

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Automação Pod -> Vídeo YouTube")
    parser.add_argument("--pod", type=int, required=True, help="Número do episódio do Pod (ex: 1, 2, 3...)")
    parser.add_argument("--audio", help="Caminho local do áudio (se omitido, busca em scratch/pod_test/pod_X.ogg)")
    parser.add_argument("--url", required=True, help="URL do vídeo Creative Commons do YouTube")
    parser.add_argument("--output", help="Caminho customizado de saída do vídeo (padrão: ~/Downloads/podX.mp4)")
    
    args = parser.parse_args()
    
    if args.audio:
        audio_file = args.audio
    else:
        audio_file = f"scratch/pod_test/pod_{args.pod}.ogg"
        
    if not os.path.exists(audio_file):
        print(f"❌ Erro: Arquivo de áudio não encontrado em '{audio_file}'.")
        sys.exit(1)
        
    downloads_folder = get_downloads_dir()
    final_video = Path(args.output) if args.output else downloads_folder / f"pod{args.pod}.mp4"
        
    temp_dir = Path("scratch/pod_test")
    temp_dir.mkdir(parents=True, exist_ok=True)
    
    dur = get_audio_duration(audio_file)
    ass_file = transcribe_and_generate_ass(audio_file, str(temp_dir), args.pod, dur)
    bg = download_background(args.url, str(temp_dir))
    render_final_video(bg, audio_file, ass_file, dur, final_video)
