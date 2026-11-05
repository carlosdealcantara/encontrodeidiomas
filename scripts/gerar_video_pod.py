"""
Pipeline Automatizado: Pill of the Day (Áudio -> Vídeo YouTube com Legenda Dinâmica)
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

def to_srt_time(seconds):
    h = int(seconds // 3600)
    m = int((seconds % 3600) // 60)
    s = int(seconds % 60)
    ms = int(round((seconds - int(seconds)) * 1000))
    if ms >= 1000:
        ms = 999
    return f"{h:02d}:{m:02d}:{s:02d},{ms:03d}"

def get_audio_duration(audio_path):
    cmd = [
        "ffprobe", "-v", "error", "-show_entries", "format=duration",
        "-of", "default=noprint_wrappers=1:nokey=1", audio_path
    ]
    res = subprocess.run(cmd, capture_output=True, text=True, check=True)
    return float(res.stdout.strip())

def transcribe_audio(audio_path, output_dir):
    print("🎧 [1/4] Transcrevendo áudio via Faster-Whisper (100% offline)...")
    model = WhisperModel("small", device="cpu", compute_type="int8")
    segments, info = model.transcribe(audio_path, beam_size=5)

    chunks = []
    for seg in segments:
        text = seg.text.strip()
        # Correção automática de termos comuns
        if "bill of the day" in text.lower():
            text = re.sub(r'bill of the day', 'Pill of the Day', text, flags=re.IGNORECASE)
            
        words = text.split()
        if not words:
            continue
            
        total_duration = seg.end - seg.start
        chunk_size = 5  # 4 a 5 palavras por linha (sem spoiler)
        word_chunks = [words[i:i + chunk_size] for i in range(0, len(words), chunk_size)]
        time_per_word = total_duration / max(len(words), 1)
        
        curr_t = seg.start
        for wc in word_chunks:
            chunk_text = " ".join(wc)
            chunk_dur = len(wc) * time_per_word
            chunks.append({
                'start': curr_t,
                'end': curr_t + chunk_dur,
                'text': chunk_text
            })
            curr_t += chunk_dur

    srt_path = os.path.join(output_dir, "subtitles.srt")
    srt_lines = []
    for idx, ch in enumerate(chunks, 1):
        s_time = to_srt_time(ch['start'])
        e_time = to_srt_time(ch['end'])
        srt_lines.append(f"{idx}\n{s_time} --> {e_time}\n{ch['text']}\n")
        
    with open(srt_path, "w", encoding="utf-8") as f:
        f.write("\n".join(srt_lines))
        
    print(f"✅ Legendas dinâmicas geradas em: {srt_path}")
    return srt_path

def download_background(youtube_url, output_dir):
    print(f"📥 [2/4] Baixando vídeo de fundo ({youtube_url})...")
    bg_path = os.path.join(output_dir, "bg_video.mp4")
    cmd = [
        sys.executable, "-m", "yt_dlp",
        "-f", "bestvideo[ext=mp4][height<=1080]/bestvideo[height<=1080]/best[height<=1080]",
        "-o", bg_path,
        "--force-overwrites",
        youtube_url
    ]
    subprocess.run(cmd, check=True)
    return bg_path

def render_final_video(bg_path, audio_path, srt_path, duration_sec, output_file):
    print("🎬 [3/4] Renderizando vídeo com FFmpeg (Opção A: contorno + sombra)...")
    # Escapar caminho do SRT para o filtro do ffmpeg no Windows
    srt_clean = srt_path.replace("\\", "/").replace(":", "\\:")
    
    style = (
        "FontName=Arial,FontSize=28,Bold=1,PrimaryColour=&H00FFFFFF,"
        "BorderStyle=1,OutlineColour=&H00000000,Outline=3,Shadow=2,MarginV=55,Alignment=2"
    )
    
    cmd = [
        "ffmpeg", "-y",
        "-i", bg_path,
        "-i", audio_path,
        "-t", str(duration_sec),
        "-filter_complex", f"[0:v]subtitles={srt_clean}:force_style='{style}'[v]",
        "-map", "[v]",
        "-map", "1:a",
        "-c:v", "libx264",
        "-preset", "fast",
        "-crf", "20",
        "-c:a", "aac",
        "-b:a", "192k",
        output_file
    ]
    subprocess.run(cmd, check=True)
    print(f"🎉 [4/4] Vídeo final pronto: {output_file}")

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Automação Pod -> Vídeo YouTube")
    parser.add_argument("--audio", required=True, help="Caminho do arquivo de áudio (.ogg/.mp3)")
    parser.add_argument("--url", required=True, help="URL do vídeo Creative Commons do YouTube")
    parser.add_argument("--output", default="video_pronto.mp4", help="Nome do arquivo de saída")
    
    args = parser.parse_args()
    work_dir = os.path.dirname(os.path.abspath(args.output))
    
    dur = get_audio_duration(args.audio)
    srt = transcribe_audio(args.audio, work_dir)
    bg = download_background(args.url, work_dir)
    render_final_video(bg, args.audio, srt, dur, args.output)
