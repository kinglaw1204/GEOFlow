from __future__ import annotations

import html
import json
import re
import subprocess
import time
from pathlib import Path
from urllib.error import URLError

from PIL import Image


ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "storage" / "app" / "public" / "uploads" / "images" / "nanshanlin-unsplash-article-images-v1"
MANIFEST = OUT_DIR / "manifest.json"

WIDTH = 1200
HEIGHT = 675

PAGES = [
    ("wood-slat-wall-bench", "现代木格栅墙与休闲区", "https://unsplash.com/photos/a-room-with-a-wooden-wall-and-a-wooden-bench-yHtnteIZ5D0"),
    ("bedroom-wood-panel", "现代卧室木质背景墙", "https://unsplash.com/photos/a-bedroom-with-a-bed-and-a-chair-in-it-97oDN-1c-EA"),
    ("dining-room-wood-wall", "现代餐厅木质墙面", "https://unsplash.com/photos/a-dining-room-with-a-table-and-chairs-xvFLCmkzGoI"),
    ("living-room-wood-wall", "现代客厅木质背景墙", "https://unsplash.com/photos/a-living-room-filled-with-furniture-and-a-wooden-wall-a48UyA8Rk0Y"),
    ("living-room-couch-wall", "现代客厅沙发背景墙", "https://unsplash.com/photos/a-living-room-with-a-couch-and-a-table-Z9GlY8szteY"),
    ("wood-door-interior", "现代木门与墙面空间", "https://unsplash.com/photos/a-room-with-a-wooden-door-and-a-table-EKWHTgn1dYs"),
    ("yellow-chair-wood-wall", "木墙与单椅空间", "https://unsplash.com/photos/a-yellow-chair-sitting-in-front-of-a-wooden-wall-8nHrmzD6j4Q"),
    ("couch-wood-wall", "现代木墙客厅空间", "https://unsplash.com/photos/a-couch-sitting-in-a-living-room-next-to-a-wooden-wall-XAICWq4rpqo"),
    ("tv-fireplace-wall", "现代电视背景墙空间", "https://unsplash.com/photos/a-living-room-with-a-fireplace-and-a-flat-screen-tv-tKy1HivnC2I"),
    ("living-room-wood-table", "现代客厅木质元素", "https://unsplash.com/photos/a-living-room-filled-with-furniture-and-a-wooden-table-Fvyc8R47ti8"),
]


def fetch(url: str) -> bytes:
    result = subprocess.run(
        ["curl", "--max-time", "60", "-s", "-L", "-A", "Mozilla/5.0", url],
        check=True,
        capture_output=True,
    )
    return result.stdout


def extract_image_url(page_html: str) -> str | None:
    matches = re.findall(r"https://images\.unsplash\.com/photo-[^\"' <]+", page_html)
    if not matches:
        return None
    # Prefer a photo URL and normalize to a predictable JPG crop.
    raw = html.unescape(matches[-1])
    base = raw.split("?")[0]
    return f"{base}?auto=format&fit=crop&w=1800&q=85&fm=jpg"


def crop_to_16x9(src: Path, dest: Path) -> tuple[int, int]:
    with Image.open(src) as img:
        img = img.convert("RGB")
        w, h = img.size
        target_ratio = WIDTH / HEIGHT
        current_ratio = w / h
        if current_ratio > target_ratio:
            new_w = int(h * target_ratio)
            left = (w - new_w) // 2
            box = (left, 0, left + new_w, h)
        else:
            new_h = int(w / target_ratio)
            top = (h - new_h) // 2
            box = (0, top, w, top + new_h)
        img = img.crop(box).resize((WIDTH, HEIGHT), Image.Resampling.LANCZOS)
        img.save(dest, "JPEG", quality=88, optimize=True)
    return WIDTH, HEIGHT


def main() -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    for old_file in OUT_DIR.glob("*.jpg"):
        old_file.unlink()
    manifest: list[dict[str, object]] = []
    for index, (slug, title, page_url) in enumerate(PAGES, start=1):
        try:
            page = fetch(page_url).decode("utf-8", errors="ignore")
            image_url = extract_image_url(page)
            if not image_url:
                print(f"skip no image: {page_url}")
                continue
            tmp = OUT_DIR / f".tmp-{slug}.jpg"
            tmp.write_bytes(fetch(image_url))
            filename = f"{index:02d}-{slug}.jpg"
            dest = OUT_DIR / filename
            width, height = crop_to_16x9(tmp, dest)
            tmp.unlink(missing_ok=True)
            manifest.append(
                {
                    "filename": filename,
                    "original_name": f"Unsplash-{title}.jpg",
                    "file_path": f"storage/uploads/images/nanshanlin-unsplash-article-images-v1/{filename}",
                    "width": width,
                    "height": height,
                    "tags": f"Unsplash,免费图库,南山林,护墙板,GEO,{title},source:{page_url}",
                    "source_url": page_url,
                    "title": title,
                }
            )
            print(f"downloaded {filename}")
            time.sleep(0.4)
        except (OSError, subprocess.CalledProcessError, URLError, TimeoutError) as exc:
            print(f"skip failed: {page_url} ({exc})")
    MANIFEST.write_text(json.dumps(manifest, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"generated manifest: {len(manifest)} images")
    print(MANIFEST)


if __name__ == "__main__":
    main()
