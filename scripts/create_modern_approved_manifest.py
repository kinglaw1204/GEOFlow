from __future__ import annotations

import json
import shutil
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
SOURCE_DIR = ROOT / "storage" / "app" / "public" / "uploads" / "images" / "nanshanlin-modern-candidates-v1"
APPROVED_DIR = ROOT / "storage" / "app" / "public" / "uploads" / "images" / "nanshanlin-modern-approved-v1"
APPROVED_MANIFEST = APPROVED_DIR / "manifest.json"

APPROVED = {
    "01-modern-wood-marble-hallway.jpg": ("A-", "现代木饰面走廊空间，墙面主体明确，可用于木饰面/空间风格文章"),
    "06-contemporary-dining-wood-accents.jpg": ("A-", "现代餐厅木质墙面/格栅氛围明确，可用于背景墙、木饰面文章"),
    "07-modern-rustic-bar-wood-accents.jpg": ("A-", "现代商业空间木作表达明确，可用于工装/商业空间木饰面文章"),
    "02-amantaka-library-interior.jpg": ("B", "高端木作空间，可作为风格备用图，不适合产品主图"),
    "03-amantaka-library-front.jpg": ("B", "高端木作空间正面视角，可作为风格备用图"),
    "08-modern-living-room-apartment.jpg": ("B", "现代客厅空间完整，但木饰面主体弱，适合作为居家场景备用图"),
    "09-hotel-room-wooden-decor.jpg": ("B", "酒店房间木质装饰明显，可作为卧室/空间氛围备用图"),
    "11-amantaka-restaurant-doors.jpg": ("B", "门墙关系较清楚，可用于隐形门/门墙一体化思路的氛围图"),
}


def contact_sheet(files: list[Path]) -> None:
    if not files:
        return
    from PIL import Image, ImageDraw

    thumb_w, thumb_h = 320, 180
    cols = 2
    rows = (len(files) + cols - 1) // cols
    sheet = Image.new("RGB", (cols * thumb_w, rows * (thumb_h + 34)), "white")
    draw = ImageDraw.Draw(sheet)
    for index, file in enumerate(files):
        with Image.open(file) as img:
            img = img.convert("RGB").resize((thumb_w, thumb_h), Image.Resampling.LANCZOS)
        x = (index % cols) * thumb_w
        y = (index // cols) * (thumb_h + 34)
        sheet.paste(img, (x, y))
        draw.text((x + 8, y + thumb_h + 8), file.name[:42], fill=(20, 20, 20))
    sheet.save(APPROVED_DIR / "preview-contact-sheet.jpg", "JPEG", quality=92)


def main() -> None:
    source_manifest_path = SOURCE_DIR / "manifest.json"
    if not source_manifest_path.is_file():
        raise SystemExit(f"Missing source manifest: {source_manifest_path}")

    APPROVED_DIR.mkdir(parents=True, exist_ok=True)
    for old_file in APPROVED_DIR.glob("*.jpg"):
        old_file.unlink()

    source_items = json.loads(source_manifest_path.read_text(encoding="utf-8"))
    approved_items: list[dict[str, object]] = []
    written: list[Path] = []
    next_index = 1
    for item in source_items:
        filename = str(item.get("filename") or "")
        if filename not in APPROVED:
            continue
        grade, note = APPROVED[filename]
        source_file = SOURCE_DIR / filename
        new_filename = f"{next_index:02d}-{filename.split('-', 1)[1]}"
        dest_file = APPROVED_DIR / new_filename
        shutil.copy2(source_file, dest_file)
        next_index += 1

        copied = dict(item)
        copied["filename"] = new_filename
        copied["file_path"] = f"storage/uploads/images/nanshanlin-modern-approved-v1/{new_filename}"
        copied["audit_grade"] = grade
        copied["audit_note"] = note
        copied["tags"] = f"{copied.get('tags', '')},审核等级:{grade},审核说明:{note}"
        approved_items.append(copied)
        written.append(dest_file)

    APPROVED_MANIFEST.write_text(json.dumps(approved_items, ensure_ascii=False, indent=2), encoding="utf-8")
    contact_sheet(written)
    print(f"approved images: {len(approved_items)}")
    print(APPROVED_MANIFEST)
    print(APPROVED_DIR / "preview-contact-sheet.jpg")


if __name__ == "__main__":
    main()
