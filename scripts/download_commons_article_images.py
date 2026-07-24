from __future__ import annotations

import html
import json
import re
import subprocess
import time
import urllib.parse
from pathlib import Path
from urllib.error import HTTPError, URLError

from PIL import Image


ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "storage" / "app" / "public" / "uploads" / "images" / "nanshanlin-commons-article-images-v2"
MANIFEST = OUT_DIR / "manifest.json"

WIDTH = 1200
HEIGHT = 675
MAX_IMAGES = 12

SEARCHES = [
    ("wood panelling room -machine -drawing -DPLA", "木质墙板空间"),
    ("wood paneling room interior -machine -drawing -DPLA", "木质护墙板/木饰面空间"),
    ("wainscoting room interior -church -drawing", "半高护墙板空间"),
    ("wood slat wall interior -people -salon", "木格栅背景墙"),
    ("wooden wall panels interior room -drawing -machine", "木质墙板室内"),
    ("wood panel wall living room -drawing -machine", "客厅木质背景墙"),
    ("interior wooden door wall -people -drawing", "隐形门/木门空间"),
]

ACCEPTED_LICENSE_WORDS = (
    "public domain",
    "cc0",
    "cc by",
    "cc-by",
    "cc by-sa",
    "cc-by-sa",
)

BLOCKED_TITLE_WORDS = (
    "epstein",
    "fbi",
    "salon",
    "hair",
    "drawing",
    "diagram",
    "plan",
    "dpla",
    "templet",
    "template",
    "machine",
    "machines",
    "catalog",
    "page",
    "patent",
    "map",
    "church",
    "cathedral",
    "museum",
    "tomb",
    "ship",
    "train",
    "aircraft",
    "portrait",
    "people",
    "person",
)

RELEVANT_TITLE_WORDS = (
    "wood",
    "wooden",
    "panel",
    "paneling",
    "panelling",
    "wainscot",
    "wainscoting",
    "slat",
    "wall",
    "interior",
    "room",
    "door",
)


def fetch_json(url: str) -> dict:
    result = subprocess.run(
        ["curl", "--max-time", "45", "-s", "-L", "-A", "NanshanlinGEOFlowImageImporter/1.0", url],
        check=True,
        capture_output=True,
    )
    return json.loads(result.stdout.decode("utf-8"))


def fetch_bytes(url: str) -> bytes:
    result = subprocess.run(
        ["curl", "--max-time", "60", "-s", "-L", "-A", "Mozilla/5.0", url],
        check=True,
        capture_output=True,
    )
    return result.stdout


def clean_text(value: object) -> str:
    text = html.unescape(str(value or ""))
    text = re.sub(r"<[^>]+>", "", text)
    return re.sub(r"\s+", " ", text).strip()


def license_is_usable(imageinfo: dict) -> bool:
    metadata = imageinfo.get("extmetadata") or {}
    license_name = clean_text((metadata.get("LicenseShortName") or {}).get("value", ""))
    usage_terms = clean_text((metadata.get("UsageTerms") or {}).get("value", ""))
    combined = f"{license_name} {usage_terms}".lower()
    return any(word in combined for word in ACCEPTED_LICENSE_WORDS)


def title_is_relevant(title: str) -> bool:
    lowered = title.lower()
    if any(word in lowered for word in BLOCKED_TITLE_WORDS):
        return False
    return any(word in lowered for word in RELEVANT_TITLE_WORDS)


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
            top = max(0, (h - new_h) // 2)
            box = (0, top, w, top + new_h)
        img = img.crop(box).resize((WIDTH, HEIGHT), Image.Resampling.LANCZOS)
        img.save(dest, "JPEG", quality=88, optimize=True)
    return WIDTH, HEIGHT


def search_commons(query: str) -> list[dict]:
    params = {
        "action": "query",
        "format": "json",
        "generator": "search",
        "gsrnamespace": "6",
        "gsrsearch": query,
        "gsrlimit": "24",
        "prop": "imageinfo",
        "iiprop": "url|mime|size|extmetadata",
        "iiurlwidth": "1800",
    }
    url = "https://commons.wikimedia.org/w/api.php?" + urllib.parse.urlencode(params)
    data = fetch_json(url)
    pages = (data.get("query") or {}).get("pages") or {}
    return sorted(pages.values(), key=lambda item: int(item.get("index", 999)))


def slugify(title: str) -> str:
    slug = title.removeprefix("File:")
    slug = re.sub(r"\.[A-Za-z0-9]+$", "", slug)
    slug = re.sub(r"[^A-Za-z0-9]+", "-", slug).strip("-").lower()
    return slug[:64] or "commons-image"


def main() -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    for old_file in OUT_DIR.glob("*.jpg"):
        old_file.unlink()
    selected: list[dict] = []
    seen_urls: set[str] = set()

    for query, topic in SEARCHES:
        try:
            pages = search_commons(query)
        except (subprocess.CalledProcessError, HTTPError, URLError, TimeoutError, json.JSONDecodeError) as exc:
            print(f"search failed: {query} ({exc})")
            continue

        for page in pages:
            title = str(page.get("title") or "")
            infos = page.get("imageinfo") or []
            if not infos:
                continue
            info = infos[0]
            url = str(info.get("thumburl") or info.get("url") or "")
            mime = str(info.get("mime") or "")
            width = int(info.get("width") or 0)
            height = int(info.get("height") or 0)
            if url in seen_urls:
                continue
            if mime not in {"image/jpeg", "image/png"}:
                continue
            if width < 900 or height < 500:
                continue
            if not title_is_relevant(title):
                continue
            if not license_is_usable(info):
                continue

            selected.append({"page": page, "info": info, "topic": topic})
            seen_urls.add(url)
            if len(selected) >= MAX_IMAGES:
                break
        if len(selected) >= MAX_IMAGES:
            break
        time.sleep(0.3)

    manifest: list[dict[str, object]] = []
    for index, item in enumerate(selected, start=1):
        page = item["page"]
        info = item["info"]
        title = str(page.get("title") or f"File:{index}")
        metadata = info.get("extmetadata") or {}
        source_url = str(info.get("descriptionurl") or "")
        image_url = str(info.get("thumburl") or info.get("url") or "")
        license_name = clean_text((metadata.get("LicenseShortName") or {}).get("value", ""))
        usage_terms = clean_text((metadata.get("UsageTerms") or {}).get("value", ""))
        artist = clean_text((metadata.get("Artist") or {}).get("value", ""))
        credit = clean_text((metadata.get("Credit") or {}).get("value", ""))
        attribution_required = clean_text((metadata.get("AttributionRequired") or {}).get("value", ""))

        slug = slugify(title)
        tmp = OUT_DIR / f".tmp-{index:02d}-{slug}"
        filename = f"{index:02d}-{slug}.jpg"
        dest = OUT_DIR / filename
        try:
            tmp.write_bytes(fetch_bytes(image_url))
            width, height = crop_to_16x9(tmp, dest)
            tmp.unlink(missing_ok=True)
        except (OSError, subprocess.CalledProcessError, HTTPError, URLError, TimeoutError) as exc:
            tmp.unlink(missing_ok=True)
            print(f"download failed: {title} ({exc})")
            continue

        manifest.append(
            {
                "filename": filename,
                "original_name": f"Wikimedia Commons-{clean_text(title.removeprefix('File:'))}.jpg",
                "file_path": f"storage/uploads/images/nanshanlin-commons-article-images-v2/{filename}",
                "width": width,
                "height": height,
                "mime_type": "image/jpeg",
                "tags": (
                    "Wikimedia Commons,开放授权图片,南山林,护墙板,GEO,"
                    f"{item['topic']},license:{license_name or usage_terms},"
                    f"source:{source_url}"
                ),
                "source_url": source_url,
                "image_url": image_url,
                "source_title": title,
                "license": license_name or usage_terms,
                "usage_terms": usage_terms,
                "artist": artist,
                "credit": credit,
                "attribution_required": attribution_required,
            }
        )
        print(f"downloaded {filename} | {license_name or usage_terms} | {source_url}")
        time.sleep(0.4)

    MANIFEST.write_text(json.dumps(manifest, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"generated manifest: {len(manifest)} images")
    print(MANIFEST)


if __name__ == "__main__":
    main()
