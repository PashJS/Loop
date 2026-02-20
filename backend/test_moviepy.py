from moviepy import VideoFileClip
import os

video_path = r"c:\MAMP\htdocs\FloxWatch\uploads\videos\video_692071a3e73ee8.78490746.webm"
try:
    clip = VideoFileClip(video_path)
    print("Duration:", clip.duration)
    clip.audio.write_audiofile("test_audio.wav")
    print("Audio extracted successfully")
    clip.close()
except Exception as e:
    print("Error:", e)
