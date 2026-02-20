import os
import glob
import subprocess
import sys

# Add backend directory to path to import generate_captions
sys.path.append(os.path.dirname(os.path.abspath(__file__)))
from generate_captions import generate_vtt

def process_all():
    videos_dir = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'uploads', 'videos')
    
    # Get all video files
    extensions = ['*.mp4', '*.webm', '*.ogg', '*.mov', '*.mkv']
    video_files = []
    for ext in extensions:
        video_files.extend(glob.glob(os.path.join(videos_dir, ext)))
        
    print(f"Found {len(video_files)} videos.")
    
    for video_path in video_files:
        vtt_path = os.path.splitext(video_path)[0] + ".vtt"
        if not os.path.exists(vtt_path):
            print(f"Generating captions for: {os.path.basename(video_path)}")
            generate_vtt(video_path)
        else:
            print(f"Skipping (VTT exists): {os.path.basename(video_path)}")

if __name__ == "__main__":
    process_all()
