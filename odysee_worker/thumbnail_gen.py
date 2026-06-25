"""
thumbnail_gen.py — Gerador de thumbnail inteligente para o Odysee Worker.

Usa ffmpeg para extrair frames do vídeo e OpenCV para selecionar o melhor
frame com base em: detecção de rosto, detecção de sorriso, luminosidade e nitidez.
"""
import os
import subprocess
import logging
import tempfile
import shutil

logger = logging.getLogger(__name__)

# Número de frames a extrair para análise. Mais frames = mais precisão, mas mais lento.
# 12 frames distribui aproximadamente 1 por cada 5 minutos de um vídeo de 1h.
NUM_FRAMES = 12

def _get_video_duration(video_path: str) -> float:
    """Retorna a duração do vídeo em segundos usando ffprobe."""
    try:
        result = subprocess.run(
            [
                "ffprobe", "-v", "error",
                "-show_entries", "format=duration",
                "-of", "default=noprint_wrappers=1:nokey=1",
                video_path
            ],
            capture_output=True, text=True, timeout=30
        )
        return float(result.stdout.strip())
    except Exception as e:
        logger.warning(f"[THUMBNAIL] Não foi possível obter duração do vídeo: {e}")
        return 3600.0  # assume 1h como fallback


def _extract_frames(video_path: str, output_dir: str, num_frames: int) -> list:
    """
    Extrai `num_frames` frames distribuídos pelo vídeo usando ffmpeg.
    Evita os primeiros 5% e últimos 5% (geralmente tela de espera ou fade out).
    Retorna lista de caminhos para os arquivos PNG/JPG gerados.
    """
    duration = _get_video_duration(video_path)
    start    = duration * 0.05
    end      = duration * 0.95
    interval = (end - start) / num_frames

    extracted = []
    for i in range(num_frames):
        timestamp = start + (i * interval)
        output_path = os.path.join(output_dir, f"frame_{i:03d}.jpg")
        try:
            subprocess.run(
                [
                    "ffmpeg", "-y",
                    "-ss", str(timestamp),
                    "-i", video_path,
                    "-vframes", "1",
                    "-q:v", "2",        # qualidade JPEG alta (2 = melhor)
                    "-vf", "scale=1280:720",  # padroniza resolução
                    output_path
                ],
                capture_output=True, timeout=30
            )
            if os.path.exists(output_path) and os.path.getsize(output_path) > 0:
                extracted.append(output_path)
        except Exception as e:
            logger.warning(f"[THUMBNAIL] Erro ao extrair frame t={timestamp:.1f}s: {e}")

    logger.info(f"[THUMBNAIL] {len(extracted)} frames extraídos de {num_frames} tentativas.")
    return extracted


def _score_frame(frame_path: str, face_cascade, smile_cascade) -> dict:
    """
    Analisa um frame e retorna um dicionário com métricas de qualidade.

    Pontuação:
      - 40 pts por rosto detectado (máx 3 rostos = 120 pts)
      - 30 pts por sorriso detectado (máx 2 sorrisos = 60 pts)
      - até 20 pts por luminosidade ideal (desconta frames muito escuros ou superexpostos)
      - até 20 pts por nitidez (desconta frames borrados / desfocados)
    """
    import cv2
    import numpy as np

    score = 0
    details = {"faces": 0, "smiles": 0, "brightness": 0, "sharpness": 0, "path": frame_path}

    try:
        img  = cv2.imread(frame_path)
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)

        # --- Nitidez (Laplacian variance) ---
        lap_var = cv2.Laplacian(gray, cv2.CV_64F).var()
        sharpness_score = min(20, int(lap_var / 10))  # cap em 20
        score += sharpness_score
        details["sharpness"] = round(lap_var, 1)

        # --- Luminosidade ---
        brightness = gray.mean()
        if 60 <= brightness <= 200:  # intervalo ideal
            score += 20
        elif 40 <= brightness < 60 or 200 < brightness <= 220:
            score += 10
        # < 40 ou > 220: muito escuro/claro, 0 pontos
        details["brightness"] = round(brightness, 1)

        # --- Detecção de rostos ---
        if face_cascade is not None:
            faces = face_cascade.detectMultiScale(gray, scaleFactor=1.1, minNeighbors=5, minSize=(50, 50))
            face_count = min(len(faces), 3)
            score += face_count * 40
            details["faces"] = face_count

            # --- Detecção de sorrisos (só dentro das áreas de rosto) ---
            if smile_cascade is not None:
                smile_count = 0
                for (fx, fy, fw, fh) in faces[:3]:
                    face_roi = gray[fy:fy+fh, fx:fx+fw]
                    smiles = smile_cascade.detectMultiScale(face_roi, scaleFactor=1.7, minNeighbors=20)
                    if len(smiles) > 0:
                        smile_count += 1
                score += min(smile_count, 2) * 30
                details["smiles"] = smile_count

    except Exception as e:
        logger.warning(f"[THUMBNAIL] Erro ao analisar frame {frame_path}: {e}")

    details["score"] = score
    return details


def gerar_thumbnail_inteligente(video_path: str) -> str | None:
    """
    Função principal. Recebe o caminho do vídeo e retorna o caminho de um arquivo
    PNG temporário com a melhor thumbnail encontrada.

    Retorna None em caso de falha total (worker deve prosseguir sem thumbnail).
    """
    try:
        import cv2
    except ImportError:
        logger.error("[THUMBNAIL] opencv-python-headless não está instalado. Abortando geração.")
        return None

    face_xml  = cv2.data.haarcascades + "haarcascade_frontalface_default.xml"
    smile_xml = cv2.data.haarcascades + "haarcascade_smile.xml"

    face_cascade  = cv2.CascadeClassifier(face_xml) if os.path.exists(face_xml) else None
    smile_cascade = cv2.CascadeClassifier(smile_xml) if os.path.exists(smile_xml) else None

    if face_cascade is None:
        logger.warning(f"[THUMBNAIL] Classificador de rostos não encontrado em: {face_xml}. Analisando apenas nitidez e brilho.")

    temp_dir = tempfile.mkdtemp(prefix="odysee_thumb_")
    logger.info(f"[THUMBNAIL] Iniciando geração inteligente. Vídeo: {video_path}")

    try:
        frames = _extract_frames(video_path, temp_dir, NUM_FRAMES)
        if not frames:
            logger.error("[THUMBNAIL] Nenhum frame extraído. ffmpeg falhou?")
            return None

        # Pontua todos os frames
        resultados = []
        for frame_path in frames:
            score_data = _score_frame(frame_path, face_cascade, smile_cascade)
            resultados.append(score_data)
            logger.info(
                f"[THUMBNAIL] {os.path.basename(frame_path)} -> "
                f"score={score_data['score']} | "
                f"faces={score_data['faces']} | "
                f"sorrisos={score_data['smiles']} | "
                f"brilho={score_data['brightness']} | "
                f"nitidez={score_data['sharpness']}"
            )

        # Ordena pelo score (maior = melhor)
        resultados.sort(key=lambda x: x["score"], reverse=True)
        melhor = resultados[0]
        logger.info(
            f"[THUMBNAIL] Melhor frame: {os.path.basename(melhor['path'])} "
            f"(score={melhor['score']}, rostos={melhor['faces']}, sorrisos={melhor['smiles']})"
        )

        dest_path = os.path.join("/app/screenshots", "thumbnail_selected.jpg")
        shutil.copy2(melhor["path"], dest_path)
        return dest_path

    except Exception as e:
        logger.error(f"[THUMBNAIL] Falha na geração da thumbnail: {e}")
        return None
    finally:
        shutil.rmtree(temp_dir, ignore_errors=True)
