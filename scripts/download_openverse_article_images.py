from __future__ import annotations

import json
import re
import subprocess
import time
import urllib.parse
from pathlib import Path
from urllib.error import HTTPError, URLError

from PIL import Image


ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "storage" / "app" / "public" / "uploads" / "images" / "nanshanlin-openverse-article-images-v1"
MANIFEST = OUT_DIR / "manifest.json"

WIDTH = 1200
HEIGHT = 675
MAX_IMAGES = 12

SEARCHES = [
    ("wood wall interior", "木质墙面空间"),
    ("wood panel interior", "木饰面/护墙板空间"),
    ("wooden wall living room", "客厅木质背景墙"),
    ("wood slat wall", "木格栅背景墙"),
    ("wainscoting room", "半高护墙板空间"),
    ("interior wooden door wall", "木门/隐形门空间"),
    ("modern wood interior wall", "现代木质墙面"),
]

LICENSES = "cc0,pdm"

BLOCKED_WORDS = (
    "buddha",
    "statue",
    "church",
    "cathedral",
    "temple",
    "museum",
    "train",
    "ship",
    "aircraft",
    "exterior",
    "outdoor",
    "garden",
    "portrait",
    "people",
    "person",
    "animal",
    "cat",
    "dog",
    "food",
    "floor",
    "roof",
    "ceiling",
    "fireplace",
    "diagram",
    "drawing",
    "plan",
    "map",
)

RELEVANT_WORDS = (
    "wood",
    "wooden",
    "wall",
    "interior",
    "room",
    "living",
    "panel",
    "slat",
    "door",
    "wainscot",
    "wainscoting",
)


def fetch_json(url: str) -> dict:
    result = subprocess.run(
        ["curl", "-s", "-L", "--max-time", "45", "-A", "NanshanlinGEOFlowImageImporter/1.0", url],
        check=True,
        capture_output=True,
    )
    return json.loads(result.stdout.decode("utf-8"))


def fetch_bytes(url: str) -> bytes:
    result = subprocess.run(
        ["curl", "-s", "-L", "--max-time", "60", "-A", "Mozilla/5.0", url],
        check=True,
        capture_output=True,
    )
    return result.stdout


def relevant(item: dict) -> bool:
    title = str(item.get("title") or "").lower()
    tags = " ".join(str(tag.get("name") or "") for tag in item.get("tags") or []).lower()
    text = f"{title} {tags}"
    if any(word in text for word in BLOCKED_WORDS):
        return False
    return any(word in text for word in RELEVANT_WORDS)


def search_openverse(query: str, page: int = 1) -> list[dict]:
    params = {
        "q": query,
        "page_size": "24",
        "page": str(page),
        "license": LICENSES,
        "mature": "false",
    }
    url = "https://api.openverse.org/v1/images/?" + urllib.parse.urlencode(params)
    return list(fetch_json(url).get("results") or [])


def crop_to_16x9(src: Path, dest: Path) -> tuple[int, int]:
    with Image.open(src) as img:
        img = img.convert("RGB")
        w, h = img.size
        if w < 500 or h < 350:
            raise ValueError(f"image too small: {w}x{h}")
        target_ratio = WIDTH / HEIGHT
        current_ratio = w / h
        if current_ratio > target_ratio:
            new_w = int(h * target_ratio)
            left = (w - new_w) // 2
            box = (left, 0, left + new_w, h)
        else:
            new_h = int(w / target_ratio)
            top = max(0, (h - new_h) // 2)
            box = (0, top, w, top + new_h)
        img = img.crop(box).resize((WIDTH, HEIGHT), Image.Resampling.LANCZOS)
        img.save(dest, "JPEG", quality=88, optimize=True)
    return WIDTH, HEIGHT


def slugify(value: str) -> str:
    slug = re.sub(r"[^A-Za-z0-9]+", "-", value).strip("-").lower()
    return slug[:64] or "openverse-image"


def main() -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    for old_file in OUT_DIR.glob("*.jpg"):
        old_file.unlink()

    selected: list[tuple[dict, str]] = []
    seen_urls: set[str] = set()

    for query, topic in SEARCHES:
        for page in (1, 2):
            try:
                results = search_openverse(query, page)
            except (subprocess.CalledProcessError, HTTPError, URLError, TimeoutError, json.JSONDecodeError) as exc:
                print(f"search failed: {query} page {page} ({exc})")
                continue
            for item in results:
                image_url = str(item.get("url") or item.get("thumbnail") or "")
                width = int(item.get("width") or 0)
                height = int(item.get("height") or 0)
                if not image_url or image_url in seen_urls:
                    continue
                if width and height and (width < 700 or height < 450):
                    continue
                if not relevant(item):
                    continue
                selected.append((item, topic))
                seen_urls.add(image_url)
                if len(selected) >= MAX_IMAGES:
                    break
            if len(selected) >= MAX_IMAGES:
                break
            time.sleep(0.25)
        if len(selected) >= MAX_IMAGES:
            break

    manifest: list[dict[str, object]] = []
    for index, (item, topic) in enumerate(selected, start=1):
        image_url = str(item.get("url") or item.get("thumbnail") or "")
        title = str(item.get("title") or f"openverse-{index}")
        slug = slugify(title)
        tmp = OUT_DIR / f".tmp-{index:02d}-{slug}"
        filename = f"{index:02d}-{slug}.jpg"
        dest = OUT_DIR / filename
        try:
            tmp.write_bytes(fetch_bytes(image_url))
            width, height = crop_to_16x9(tmp, dest)
            tmp.unlink(missing_ok=True)
        except (OSError, subprocess.CalledProcessError, HTTPError, URLError, TimeoutError, ValueError) as exc:
            tmp.unlink(missing_ok=True)
            print(f"download failed: {title} ({exc})")
            continue

        source_url = str(item.get("foreign_landing_url") or item.get("detail_url") or "")
        license_code = str(item.get("license") or "")
        license_url = str(item.get("license_url") or "")
        creator = str(item.get("creator") or "")
        attribution = str(item.get("attribution") or "")
        manifest.append(
            {
                "filename": filename,
                "original_name": f"Openverse-{title}.jpg",
                "file_path": f"storage/uploads/images/nanshanlin-openverse-article-images-v1/{filename}",
                "width": width,
                "height": height,
                "mime_type": "image/jpeg",
                "tags": (
                    "Openverse,CC0或公有领域,南山林,护墙板,GEO,"
                    f"{topic},license:{license_code},source:{source_url}"
                ),
                "source_url": source_url,
                "image_url": image_url,
                "source_title": title,
                "license": license_code,
                "license_url": license_url,
                "creator": creator,
                "provider": str(item.get("provider") or ""),
                "attribution": attribution,
            }
        )
        print(f"downloaded {filename} | {license_code} | {source_url}")
        time.sleep(0.35)

    MANIFEST.write_text(json.dumps(manifest, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"generated manifest: {len(manifest)} images")
    print(MANIFEST)


if __name__ == "__main__":
    main()
