import cv2
import sys
import json
import numpy as np

def analyze_video(video_path):
    """
    Analyzes a video file frame-by-frame to extract metadata, motion, color, and shapes.
    """
    try:
        cap = cv2.VideoCapture(video_path)
        if not cap.isOpened():
            print(json.dumps({"error": "Could not open video file"}))
            return

        fps = cap.get(cv2.CAP_PROP_FPS)
        frame_count = int(cap.get(cv2.CAP_PROP_FRAME_COUNT))
        width = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
        height = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))

        output = {
            "meta": {
                "file": video_path,
                "fps": max(fps, 1.0), # Safety clamp
                "width": width,
                "height": height,
                "total_frames": frame_count
            },
            "frames": []
        }

        prev_gray = None
        frame_idx = 0

        while True:
            ret, frame = cap.read()
            if not ret:
                break

            # 1. Color Extraction (Dominant via Average)
            # Resize to tiny grid for super-fast processing (64x64)
            small_frame = cv2.resize(frame, (64, 64))
            avg_color_row = np.average(small_frame, axis=0)
            avg_color = np.average(avg_color_row, axis=0)
            # Convert BGR to Hex
            hex_color = "#{:02x}{:02x}{:02x}".format(int(avg_color[2]), int(avg_color[1]), int(avg_color[0]))

            # 2. Motion Detection (Frame Difference)
            gray = cv2.cvtColor(small_frame, cv2.COLOR_BGR2GRAY)
            motion_score = 0.0

            if prev_gray is not None:
                # Calculate absolute difference
                diff = cv2.absdiff(prev_gray, gray)
                # Apply threshold to remove noise
                _, thresh = cv2.threshold(diff, 25, 255, cv2.THRESH_BINARY)
                # Calculate percentage of changed pixels
                non_zero = cv2.countNonZero(thresh)
                total_pixels = small_frame.shape[0] * small_frame.shape[1]
                motion_score = non_zero / total_pixels

            # 3. Shape/Object Complexity (Canny Edges on full frame or scaled)
            # We use a slightly larger scale for edge detection than color, but still reduced for speed
            edge_frame = cv2.resize(gray, (320, 180)) 
            edges = cv2.Canny(edge_frame, 100, 200)
            # Find contours
            contours, _ = cv2.findContours(edges, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
            detected_shapes = len(contours)

            frame_data = {
                "frame": frame_idx,
                "timestamp": float(f"{frame_idx / output['meta']['fps']:.4f}"),
                "dominant_color": hex_color,
                "motion_intensity": float(f"{motion_score:.4f}"),
                "detected_shapes": detected_shapes
            }

            output["frames"].append(frame_data)

            prev_gray = gray
            frame_idx += 1

        cap.release()
        
        # Dump compressed JSON to stdout
        print(json.dumps(output))

    except Exception as e:
        print(json.dumps({"error": str(e)}))

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "Usage: python video_analyzer.py <video_path>"}))
    else:
        analyze_video(sys.argv[1])
