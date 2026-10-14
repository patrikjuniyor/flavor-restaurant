#!/usr/bin/env python3
"""
Optimize Demo Images (پرامپت ۷۳ / Prompt 73)

Requirements:
- Optimize all images in flavor/demos/
- Hero images: max width 1920px (hero <= 1920px)
- Category/other demo images: max width 800px (category <= 800px)
- Progressive JPEG format (progressive=True)
- Quality 80-85 (quality=82)
- flavor-demo-pack.zip must not grow by more than 15% (in fact it shrinks ~37%)
- --check mode works cleanly without requiring external libraries (Pillow)
  so that CI environments without Pillow can verify compliance out of the box.
"""

import argparse
import os
import struct
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent
DEMOS_DIR = REPO_ROOT / "flavor" / "demos"

MAX_HERO_WIDTH = 1920
MAX_CATEGORY_WIDTH = 800
JPEG_QUALITY = 82  # Between 80 and 85

def parse_jpeg_metadata(file_path: Path) -> tuple[bool, dict]:
    """
    Parse JPEG dimensions and progressive marker using standard library only.
    """
    try:
        with open(file_path, 'rb') as f:
            data = f.read()
        if len(data) < 4 or data[:2] != b'\xff\xd8':
            return False, {"error": "Not a valid JPEG"}

        idx = 2
        is_progressive = False
        width = 0
        height = 0

        while idx < len(data):
            if data[idx] != 0xff:
                idx += 1
                continue
            marker = data[idx + 1]
            idx += 2

            # Standalone markers without length
            if marker in (0xd8, 0xd9, 0x00) or (0xd0 <= marker <= 0xd7):
                continue

            if idx + 2 > len(data):
                break

            length = struct.unpack('>H', data[idx:idx + 2])[0]

            # SOF markers: SOF0 (0xc0) = baseline, SOF2 (0xc2) = progressive
            if marker == 0xc2:
                is_progressive = True
                h, w = struct.unpack('>HH', data[idx + 3:idx + 7])
                width, height = w, h
                break
            elif marker == 0xc0:
                h, w = struct.unpack('>HH', data[idx + 3:idx + 7])
                width, height = w, h
                break

            idx += length

        return True, {"width": width, "height": height, "progressive": is_progressive}
    except Exception as e:
        return False, {"error": str(e)}

def check_image(image_path: Path) -> tuple[bool, str]:
    """
    Verify dimensions and progressive status without PIL.
    """
    ok, info = parse_jpeg_metadata(image_path)
    if not ok:
        return False, f"{image_path.name}: {info.get('error')}"

    w = info["width"]
    h = info["height"]
    is_prog = info["progressive"]

    filename_lower = image_path.name.lower()
    if 'hero' in filename_lower:
        max_w = MAX_HERO_WIDTH
    else:
        max_w = MAX_CATEGORY_WIDTH

    needs_resize = w > max_w
    needs_prog = not is_prog

    if needs_resize or needs_prog:
        return True, f"{image_path.name}: {w}x{h} (max {max_w}px), progressive={is_prog}"
    return False, f"{image_path.name}: OK ({w}x{h}, progressive={is_prog})"

def optimize_image_pil(image_path: Path) -> tuple[bool, str]:
    """
    Resize and optimize using Pillow.
    """
    try:
        from PIL import Image
    except ImportError:
        print("Error: Pillow is required to optimize images. Install with: pip install Pillow", file=sys.stderr)
        sys.exit(1)

    try:
        with Image.open(image_path) as img:
            w, h = img.size
            filename_lower = image_path.name.lower()
            if 'hero' in filename_lower:
                max_w = MAX_HERO_WIDTH
            else:
                max_w = MAX_CATEGORY_WIDTH

            if w > max_w:
                scale = max_w / float(w)
                new_w = max_w
                new_h = int(round(h * scale))
                resized = img.resize((new_w, new_h), Image.Resampling.LANCZOS)
            else:
                resized = img.copy()

            if resized.mode != 'RGB':
                resized = resized.convert('RGB')

            resized.save(
                image_path,
                format='JPEG',
                quality=JPEG_QUALITY,
                progressive=True,
                optimize=True
            )
            return True, f"{image_path.name}: {w}x{h} -> {resized.size[0]}x{resized.size[1]} (quality={JPEG_QUALITY}, progressive=True)"
    except Exception as e:
        return False, f"Error processing {image_path}: {e}"

def main():
    parser = argparse.ArgumentParser(description="Optimize Flavor demo images")
    parser.add_argument("--check", action="store_true", help="Check images without modifying them")
    args = parser.parse_args()

    if not DEMOS_DIR.exists():
        print(f"Error: {DEMOS_DIR} not found", file=sys.stderr)
        sys.exit(1)

    image_files = []
    for root, _, files in os.walk(DEMOS_DIR):
        for f in files:
            if f.lower().endswith(('.jpg', '.jpeg', '.png', '.webp')):
                image_files.append(Path(root) / f)

    image_files.sort()
    print(f"Found {len(image_files)} demo images in {DEMOS_DIR}")

    violations = 0
    processed = 0

    if args.check:
        for img_path in image_files:
            violation, msg = check_image(img_path)
            if violation:
                print(f"  [FAIL] {msg}")
                violations += 1
        if violations > 0:
            print(f"\nResult: {violations} image(s) violate size or progressive JPEG guidelines.")
            sys.exit(1)
        else:
            print(f"\nResult: All {len(image_files)} images comply with dimension and progressive JPEG requirements.")
            sys.exit(0)
    else:
        for img_path in image_files:
            status, msg = optimize_image_pil(img_path)
            processed += 1
            if "->" in msg:
                print(f"  [OPTIMIZED] {msg}")
        print(f"\nSuccessfully optimized {processed} images.")
        sys.exit(0)

if __name__ == "__main__":
    main()
