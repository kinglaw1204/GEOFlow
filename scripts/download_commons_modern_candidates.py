from __future__ import annotations

import html
import json
import re
import subprocess
import urllib.parse
from pathlib import Path

from PIL import Image


ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "storage" / "app" / "public" / "uploads" / "images" / "nanshanlin-modern-candidates-v1"
MANIFEST = OUT_DIR / "manifest.json"

WIDTH = 1200
HEIGHT = 675

CANDIDATES = [
    (
        "modern-wood-marble-hallway",
        "现代木饰面与石材走廊空间",
        "File:Bright hallway in a modern building showcasing wood and marble design elements.jpg",
        "现代公共空间/木饰面",
    ),
    (
        "amantaka-library-interior",
        "现代酒店图书馆木作空间",
        "File:Interior of the library at Amantaka luxury Resort & Hotel in Luang Prabang Laos.jpg",
        "现代高端空间/木作氛围",
    ),
    (
        "amantaka-library-front",
        "现代酒店图书馆木作正面空间",
        "File:Interior of the library at Amantaka luxury Resort & Hotel in Luang Prabang Laos - front view.jpg",
        "现代高端空间/木作氛围",
    ),
    (
        "amantaka-reception-lounge",
        "现代接待休闲区木作空间",
        "File:Reception lounge at Amantaka luxury Resort & Hotel at blue hour in Luang Prabang Laos.jpg",
        "现代接待空间/背景墙氛围",
    ),
    (
        "amantaka-restaurant-room",
        "现代餐厅木作空间",
        "File:Restaurant room of Amantaka luxury Resort & Hotel in Luang Prabang Laos.jpg",
        "现代餐厅空间/木作氛围",
    ),
    (
        "contemporary-dining-wood-accents",
        "现代餐厅木质装饰空间",
        "File:Dining table set for an indoor meal in a contemporary styled room with wooden accents.jpg",
        "现代餐厅空间/木作氛围",
    ),
    (
        "modern-rustic-bar-wood-accents",
        "现代木质酒吧空间",
        "File:Modern rustic interior of a spacious bar featuring wooden accents and stylish seating in a lively social setting.jpg",
        "现代商业空间/木作氛围",
    ),
    (
        "modern-living-room-apartment",
        "现代公寓客厅空间",
        "File:Modern living room with stylish furniture and a view of the outdoors in a cozy apartment setting.jpg",
        "现代客厅空间/背景墙氛围",
    ),
    (
        "hotel-room-wooden-decor",
        "酒店房间木质装饰空间",
        "File:Hotel room with traditional wooden decor and city view.jpg",
        "酒店空间/木作氛围",
    ),
    (
        "amantaka-suite-lobby-lounge",
        "现代套房休闲区木作空间",
        "File:Lobby lounge of Amantaka Suite Amantaka luxury Resort & Hotel Luang Prabang Laos.jpg",
        "现代高端空间/木作氛围",
    ),
    (
        "amantaka-restaurant-doors",
        "现代餐厅门墙空间",
        "File:Row of round tables and doors in restaurant of Amantaka luxury Resort & Hotel in Luang Prabang Laos.jpg",
        "现代餐厅空间/门墙关系",
    ),
    (
        "norton-canes-interior",
        "现代服务区木作室内空间",
        "File:Norton Canes Services, a pleasing interior. - geograph.org.uk - 1342429.jpg",
        "现代公共空间/木作氛围",
    ),
    (
        "wonderland-interior",
        "现代商业室内空间",
        "File:Interior of Wonderland, 49 Old Compton Street, August 2023.jpg",
        "现代商业空间/墙面氛围",
    ),
]


def clean_text(value: object) -> str:
    text = html.unescape(str(value or ""))
    text = re.sub(r"<[^>]+>", "", text)
    return re.sub(r"\s+", " ", text).strip()


def curl_bytes(url: str) -> bytes:
    result = subprocess.run(
        ["curl", "--max-time", "60", "-s", "-L", "-A", "Mozilla/5.0", url],
        check=True,
        capture_output=True,
    )
    return result.stdout


def commons_info(title: str) -> dict:
    params = {
        "action": "query",
        "format": "json",
        "titles": title,
        "prop": "imageinfo",
        "iiprop": "url|mime|size|extmetadata",
        "iiurlwidth": "1800",
    }
    url = "https://commons.wikimedia.org/w/api.php?" + urllib.parse.urlencode(params)
    data = json.loads(curl_bytes(url).decode("utf-8"))
    pages = (data.get("query") or {}).get("pages") or {}
    page = next(iter(pages.values()))
    info = (page.get("imageinfo") or [])[0]
    return {"page": page, "info": info}


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
        img.save(dest, "JPEG", quality=90, optimize=True)
    return WIDTH, HEIGHT


def contact_sheet(files: list[Path]) -> None:
    if not files:
        return
    thumb_w, thumb_h = 320, 180
    cols = 2
    rows = (len(files) + cols - 1) // cols
    sheet = Image.new("RGB", (cols * thumb_w, rows * (thumb_h + 30)), "white")
    from PIL import ImageDraw

    draw = ImageDraw.Draw(sheet)
    for index, file in enumerate(files):
        with Image.open(file) as img:
            img = img.convert("RGB").resize((thumb_w, thumb_h), Image.Resampling.LANCZOS)
        x = (index % cols) * thumb_w
        y = (index // cols) * (thumb_h + 30)
        sheet.paste(img, (x, y))
        draw.text((x + 8, y + thumb_h + 8), file.name[:42], fill=(20, 20, 20))
    sheet.save(OUT_DIR / "preview-contact-sheet.jpg", "JPEG", quality=92)


def main() -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    for old_file in OUT_DIR.glob("*.jpg"):
        old_file.unlink()

    manifest: list[dict[str, object]] = []
    written: list[Path] = []
    for index, (slug, local_title, commons_title, topic) in enumerate(CANDIDATES, 1):
        try:
            data = commons_info(commons_title)
            info = data["info"]
            meta = info.get("extmetadata") or {}
            image_url = str(info.get("thumburl") or info.get("url") or "")
            tmp = OUT_DIR / f".tmp-{slug}"
            tmp.write_bytes(curl_bytes(image_url))
            filename = f"{index:02d}-{slug}.jpg"
            dest = OUT_DIR / filename
            width, height = crop_to_16x9(tmp, dest)
            tmp.unlink(missing_ok=True)
            source_url = str(info.get("descriptionurl") or "")
            license_name = clean_text((meta.get("LicenseShortName") or {}).get("value", ""))
            artist = clean_text((meta.get("Artist") or {}).get("value", ""))
            attribution_required = clean_text((meta.get("AttributionRequired") or {}).get("value", ""))
            manifest.append(
                {
                    "filename": filename,
                    "original_name": f"Wikimedia Commons-{local_title}.jpg",
                    "file_path": f"storage/uploads/images/nanshanlin-modern-candidates-v1/{filename}",
                    "width": width,
                    "height": height,
                    "mime_type": "image/jpeg",
                    "tags": f"Wikimedia Commons,现代装修效果图候选,南山林,护墙板,GEO,{topic},license:{license_name},source:{source_url}",
                    "source_url": source_url,
                    "image_url": image_url,
                    "source_title": commons_title,
                    "title": local_title,
                    "topic": topic,
                    "license": license_name,
                    "artist": artist,
                    "attribution_required": attribution_required,
                }
            )
            written.append(dest)
            print(f"downloaded {filename} | {license_name} | {source_url}")
        except Exception as exc:
            print(f"skip {commons_title}: {exc}")

    MANIFEST.write_text(json.dumps(manifest, ensure_ascii=False, indent=2), encoding="utf-8")
    contact_sheet(written)
    print(f"generated manifest: {len(manifest)} images")
    print(MANIFEST)
    print(OUT_DIR / "preview-contact-sheet.jpg")


if __name__ == "__main__":
    main()
