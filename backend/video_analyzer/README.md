# FloxWatch Video Analysis Engine (Python)

This module provides high-performance frame-by-frame video analysis using Python and OpenCV.
It extracts:
- Dominant Colors
- Motion Intensity
- Shape/Object Complexity
- Frame Timestamps

## Requirements
- Python 3.x
- OpenCV (`opencv-python`)

## Installation

```bash
pip install opencv-python numpy
```

## Usage

```bash
python run.py <path_to_video.mp4>
```

## Output
The tool outputs a JSON object to `stdout` containing high-resolution metadata.

```json
{
  "meta": { ... },
  "frames": [
    {
      "frame": 0,
      "timestamp": 0.0000,
      "dominant_color": "#2a1f1b",
      "motion_intensity": 0.0000,
      "detected_shapes": 45
    },
    ...
  ]
}
```
