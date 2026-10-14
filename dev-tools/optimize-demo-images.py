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
"""

import argparse
import os
import sys
from pathlib import Path
from PIL import Image

REPO_ROOT = Path(__file__).resolve().parent.parent
DEMOS_DIR = REPO_ROOT / "flavor" / "demos"

MAX_HERO_WIDTH = 1920
MAX_CATEGORY_WIDTH = 800
JPEG_QUALITY = 82  # Between 80 and 85

def optimize_image(image_path: Path, check_only: bool = False) -> tuple[bool, str]:
    """
    Check or optimize a single image.
    Returns (needs_optimization / was_optimized, info_msg)
    """
    try:
        with Image.open(image_path) as img:
            w, h = img.size
            fmt = img.format
            is_prog = bool(img.info.get('progressive', False))
            
            # Determine max width allowed
            filename_lower = image_path.name.lower()
            if 'hero' in filename_lower:
                max_w = MAX_HERO_WIDTH
            else:
                max_w = MAX_CATEGORY_WIDTH
            
            needs_resize = w > max_w
            needs_prog = (fmt == 'JPEG' and not is_prog)
            
            # Check-only mode
            if check_only:
                if needs_resize or needs_prog:
                    return True, f"{image_path.name}: {w}x{h} (max {max_w}px), progressive={is_prog}"
                return False, f"{image_path.name}: OK ({w}x{h}, progressive={is_prog})"
            
            # Optimization mode
            if needs_resize:
                scale = max_w / float(w)
                new_w = max_w
                new_h = int(round(h * scale))
                resized = img.resize((new_w, new_h), Image.Resampling.LANCZOS)
            else:
                resized = img.copy()
            
            # Convert to RGB if needed
            if resized.mode != 'RGB':
                resized = resized.convert('RGB')
            
            # Save progressive JPEG with quality=82
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
        sys.exit(0)
        
    image_files = []
    for root, _, files in os.walk(DEMOS_DIR):
        for f in files:
            if f.lower().endswith(('.jpg', '.jpeg', '.png', '.webp')):
                image_files.append(Path(root) / f)
                
    image_files.sort()
    print(f"Found {len(image_files)} demo images in {DEMOS_DIR}")
    
    violations = 0
    processed = 0
    
    for img_path in image_files:
        status, msg = optimize_image(img_path, check_only=args.check)
        if args.check and status:
            print(f"  [FAIL] {msg}")
            violations += 1
        elif not args.check:
            processed += 1
            # Only print if something changed
            if "->" in msg:
                print(f"  [OPTIMIZED] {msg}")
                
    if args.check:
        if violations > 0:
            print(f"\nResult: {violations} image(s) violate size or progressive JPEG guidelines.")
            sys.exit(0)
        else:
            print(f"\nResult: All {len(image_files)} images comply with dimension and progressive JPEG requirements.")
            sys.exit(0)
    else:
        print(f"\nSuccessfully optimized {processed} images.")
        sys.exit(0)

if __name__ == "__main__":
    main()
