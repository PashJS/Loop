import os
import sys
import argparse
import subprocess
import mysql.connector

# Database config
DB_CONFIG = {
    'user': 'root',
    'password': 'root',
    'host': '127.0.0.1',
    'database': 'floxwatch',
}

UPLOAD_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), '../uploads/videos'))

def timestamp_to_vtt(seconds):
    hours = int(seconds // 3600)
    minutes = int((seconds % 3600) // 60)
    secs = seconds % 60
    return f"{hours:02}:{minutes:02}:{secs:06.3f}"

def check_ffmpeg():
    try:
        subprocess.check_output(['ffmpeg', '-version'])
        return True
    except FileNotFoundError:
        print("CRITICAL ERROR: 'ffmpeg' command not found. Please install FFmpeg and add it to your System PATH.")
        return False

def install_dependencies():
    packages = ["openai-whisper", "torch"]
    for package in packages:
        try:
            # handle package name difference
            full_pkg = "whisper" if package == "openai-whisper" else package
            __import__(full_pkg)
        except ImportError:
            print(f"Installing {package}...", flush=True)
            subprocess.check_call([sys.executable, "-m", "pip", "install", package])

def generate_captions(video_id=None):
    if not check_ffmpeg():
        return

    install_dependencies()
    
    import whisper
    import torch

    device = "cuda" if torch.cuda.is_available() else "cpu"
    print(f"Loading OpenAI Whisper model (base) on {device}...", flush=True)
    
    try:
        model = whisper.load_model("base", device=device)
    except Exception as e:
        print(f"Failed to load Whisper model: {e}")
        return

    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor(dictionary=True)

        query = "SELECT id, title, video_url FROM videos WHERE (captions_url IS NULL OR captions_url = '')"
        params = []
        if video_id:
            query = "SELECT id, title, video_url FROM videos WHERE id = %s"
            params.append(video_id)
        
        cursor.execute(query, params)
        videos = cursor.fetchall()

        if not videos:
            print("No videos found needing captions.")
            return

        print(f"Found {len(videos)} video(s) to process.", flush=True)

        for vid in videos:
            print(f"[{vid['id']}] Processing: {vid['title']}", flush=True)
            
            if not vid['video_url']:
                print(f"  Skipping: No video URL")
                continue

            input_path = None
            is_remote = False
            
            if vid['video_url'].startswith('http'):
                print(f"  Downloading remote video...", flush=True)
                is_remote = True
                try:
                    import urllib.request
                    temp_filename = f"temp_{vid['id']}.mp4"
                    input_path = os.path.join(UPLOAD_DIR, temp_filename)
                    if os.path.exists(input_path):
                        os.remove(input_path)
                    urllib.request.urlretrieve(vid['video_url'], input_path)
                except Exception as e:
                    print(f"  Download failed: {e}")
                    continue
            else:
                clean_path = vid['video_url'].replace('/uploads/videos/', '').replace('../', '').replace('/', '')
                input_path = os.path.join(UPLOAD_DIR, clean_path)

            if not os.path.exists(input_path):
                print(f"  Error: File not found at {input_path}")
                continue

            print(f"  Transcribing with Whisper...", flush=True)
            
            try:
                # Transcribe
                # Whisper returns segments with start/end
                result = model.transcribe(input_path, word_timestamps=True)
                
                if is_remote and os.path.exists(input_path):
                    os.remove(input_path)

                segments = result.get('segments', [])
                if not segments:
                    print("  Warning: No speech detected.")
                    continue

                # Save VTT
                base_name = f"captions_{vid['id']}" if is_remote else os.path.splitext(os.path.basename(input_path))[0]
                vtt_filename = base_name + '.vtt'
                output_path = os.path.join(UPLOAD_DIR, vtt_filename)

                with open(output_path, 'w', encoding='utf-8') as f:
                    f.write("WEBVTT\n\n")
                    for seg in segments:
                        start_vtt = timestamp_to_vtt(seg['start'])
                        end_vtt = timestamp_to_vtt(seg['end'])
                        text = seg['text'].strip()
                        f.write(f"{start_vtt} --> {end_vtt}\n{text}\n\n")
                
                vtt_url = '/uploads/videos/' + vtt_filename
                
                # Update DB
                update_cursor = conn.cursor()
                update_cursor.execute("UPDATE videos SET captions_url = %s WHERE id = %s", (vtt_url, vid['id']))
                conn.commit()
                update_cursor.close()
                
                print(f"  Success! Captions saved to {vtt_url}", flush=True)

            except Exception as e:
                print(f"  Error processing video: {e}")

    except mysql.connector.Error as err:
        print(f"Database Error: {err}")
    finally:
        if 'conn' in locals() and conn.is_connected():
            cursor.close()
            conn.close()

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--video_id", type=int, help="Process a specific video ID")
    args = parser.parse_args()
    
    generate_captions(args.video_id)
