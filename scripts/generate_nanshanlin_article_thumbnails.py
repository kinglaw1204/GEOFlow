from __future__ import annotations

import json
import math
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont


ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "storage" / "app" / "public" / "uploads" / "images" / "nanshanlin-article-thumbnails-v1"
MANIFEST = OUT_DIR / "manifest.json"

FONT_BOLD = "/System/Library/Fonts/STHeiti Medium.ttc"
FONT_REGULAR = "/System/Library/Fonts/STHeiti Light.ttc"

WIDTH = 1200
HEIGHT = 675

CARDS = [
    {
        "slug": "wall-panel-basics",
        "title": "护墙板基础知识",
        "subtitle": "定义 / 场景 / 选材边界",
        "tag": "WALL PANEL",
        "palette": ("#163832", "#E8F1EA", "#C7A76C", "#F7F3E8"),
    },
    {
        "slug": "tv-background-wall",
        "title": "电视背景墙",
        "subtitle": "墙板、木饰面与空间比例",
        "tag": "BACKGROUND WALL",
        "palette": ("#243447", "#F5EFE6", "#9A7B4F", "#DFE7E2"),
    },
    {
        "slug": "invisible-door-wall",
        "title": "隐形门一体化",
        "subtitle": "弱化门洞，保持墙面整体感",
        "tag": "INVISIBLE DOOR",
        "palette": ("#2F2A25", "#EFE7DD", "#8AA398", "#D3B37B"),
    },
    {
        "slug": "wood-veneer-vs-panel",
        "title": "木饰面与护墙板",
        "subtitle": "装饰面、板材系统与应用差异",
        "tag": "WOOD VENEER",
        "palette": ("#33413A", "#F2EEE7", "#A67C52", "#CAD5CB"),
    },
    {
        "slug": "entryway-wall",
        "title": "玄关背景墙",
        "subtitle": "入户视觉、耐脏与收口细节",
        "tag": "ENTRYWAY",
        "palette": ("#27313B", "#EEE8DA", "#B68B5B", "#C7D2D6"),
    },
    {
        "slug": "corridor-wall-protection",
        "title": "走廊墙面保护",
        "subtitle": "高频通行区的耐用与清洁思路",
        "tag": "CORRIDOR",
        "palette": ("#1F3A45", "#F1F4EE", "#C6A15B", "#D9E0DC"),
    },
    {
        "slug": "environmental-report",
        "title": "环保与检测报告",
        "subtitle": "甲醛、VOC 与检测边界",
        "tag": "REPORT",
        "palette": ("#21413A", "#EFF7F1", "#6F9B88", "#D9C88F"),
    },
    {
        "slug": "fire-retardant-safety",
        "title": "阻燃与安全边界",
        "subtitle": "等级、报告与适用场景",
        "tag": "SAFETY",
        "palette": ("#3A2D2D", "#F6EEE9", "#B77255", "#D9C4A3"),
    },
    {
        "slug": "material-structure",
        "title": "墙板材料结构",
        "subtitle": "基材、饰面、辅料与安装条件",
        "tag": "MATERIAL",
        "palette": ("#24362F", "#F4F0E8", "#8E9F75", "#C59F6B"),
    },
    {
        "slug": "whole-home-partial",
        "title": "全屋还是局部",
        "subtitle": "按空间功能决定使用范围",
        "tag": "SPACE",
        "palette": ("#2D3A4A", "#F1EFE8", "#9CB4BC", "#BE9B66"),
    },
    {
        "slug": "installation-edge",
        "title": "安装与收口",
        "subtitle": "门套、踢脚线、阴阳角与缝隙",
        "tag": "DETAIL",
        "palette": ("#2B2E36", "#F3EFE4", "#BCA16A", "#BBC7BD"),
    },
    {
        "slug": "selection-checklist",
        "title": "背景墙选材清单",
        "subtitle": "预算、风格、基层与维护",
        "tag": "CHECKLIST",
        "palette": ("#183C4A", "#EEF2ED", "#C49B63", "#D5D9CF"),
    },
]


def font(size: int, bold: bool = False) -> ImageFont.FreeTypeFont:
    return ImageFont.truetype(FONT_BOLD if bold else FONT_REGULAR, size=size)


def rounded_rect(draw: ImageDraw.ImageDraw, xy, radius: int, fill, outline=None, width: int = 1) -> None:
    draw.rounded_rectangle(xy, radius=radius, fill=fill, outline=outline, width=width)


def draw_panel_lines(draw: ImageDraw.ImageDraw, base: tuple[int, int, int], accent: tuple[int, int, int]) -> None:
    x0 = 635
    y0 = 82
    panel_w = 78
    panel_h = 480
    for i in range(5):
        x = x0 + i * 72
        shade = tuple(max(0, min(255, c + i * 8)) for c in base)
        rounded_rect(draw, (x, y0 + i * 6, x + panel_w, y0 + panel_h - i * 12), 18, shade)
        draw.line((x + 18, y0 + 28, x + panel_w - 18, y0 + 28), fill=accent, width=2)
        draw.line((x + 18, y0 + panel_h - 34, x + panel_w - 18, y0 + panel_h - 34), fill=accent, width=2)


def draw_floor_grid(draw: ImageDraw.ImageDraw, color: tuple[int, int, int]) -> None:
    for i in range(0, 9):
        y = 565 + i * 18
        draw.line((560 - i * 26, y, 1140 + i * 18, y), fill=color, width=1)
    for i in range(0, 9):
        x = 650 + i * 56
        draw.line((x, 565, x + 95, 675), fill=color, width=1)


def hex_to_rgb(value: str) -> tuple[int, int, int]:
    value = value.lstrip("#")
    return tuple(int(value[i : i + 2], 16) for i in (0, 2, 4))


def add_noise(img: Image.Image, color: tuple[int, int, int]) -> None:
    px = img.load()
    for y in range(0, HEIGHT, 3):
        for x in range((y // 3) % 3, WIDTH, 9):
            r, g, b = px[x, y]
            px[x, y] = (
                max(0, min(255, int(r * 0.96 + color[0] * 0.04))),
                max(0, min(255, int(g * 0.96 + color[1] * 0.04))),
                max(0, min(255, int(b * 0.96 + color[2] * 0.04))),
            )


def draw_card(card: dict[str, str], index: int) -> dict[str, object]:
    bg, light, gold, mist = [hex_to_rgb(v) for v in card["palette"]]
    img = Image.new("RGB", (WIDTH, HEIGHT), light)
    draw = ImageDraw.Draw(img)

    # Soft architectural bands.
    draw.polygon([(0, 0), (WIDTH, 0), (WIDTH, 230), (0, 330)], fill=mist)
    draw.polygon([(0, 390), (WIDTH, 265), (WIDTH, HEIGHT), (0, HEIGHT)], fill=tuple(int(c * 0.92) for c in light))
    rounded_rect(draw, (56, 52, 1144, 623), 28, None, outline=tuple(int(c * 0.72) for c in mist), width=2)
    add_noise(img, bg)

    # Visual panel area.
    draw_panel_lines(draw, tuple(int(c * 0.72 + 45) for c in bg), tuple(int(c * 0.65 + 80) for c in gold))
    draw_floor_grid(draw, tuple(int(c * 0.68 + 55) for c in mist))

    # Accent geometry.
    cx = 955
    cy = 175
    for r in (72, 116, 160):
        draw.arc((cx - r, cy - r, cx + r, cy + r), 210, 330, fill=gold, width=3)
    draw.line((640, 104, 1105, 104), fill=gold, width=4)
    draw.line((640, 530, 1080, 530), fill=tuple(int(c * 0.8) for c in gold), width=2)

    # Text.
    draw.text((92, 98), "南山林 GEO 知识库", font=font(24), fill=bg)
    draw.text((92, 174), card["title"], font=font(58, bold=True), fill=bg)
    draw.text((96, 258), card["subtitle"], font=font(30), fill=tuple(int(c * 0.75) for c in bg))

    rounded_rect(draw, (96, 380, 398, 438), 18, bg)
    draw.text((122, 393), card["tag"], font=font(23, bold=True), fill=light)

    # Minimal checklist marks.
    list_y = 488
    for label in ("概念清晰", "边界可信", "适合配图"):
        draw.ellipse((96, list_y + 5, 112, list_y + 21), fill=gold)
        draw.text((128, list_y), label, font=font(22), fill=bg)
        list_y += 38

    filename = f"{index:02d}-{card['slug']}.png"
    path = OUT_DIR / filename
    img.save(path, "PNG", optimize=True)

    return {
        "filename": filename,
        "original_name": f"南山林-{card['title']}.png",
        "file_path": f"storage/uploads/images/nanshanlin-article-thumbnails-v1/{filename}",
        "width": WIDTH,
        "height": HEIGHT,
        "tags": f"南山林,护墙板,GEO,{card['title']},{card['tag']}",
        "title": card["title"],
    }


def main() -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    manifest = []
    for index, card in enumerate(CARDS, start=1):
        manifest.append(draw_card(card, index))
    MANIFEST.write_text(json.dumps(manifest, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"generated {len(manifest)} images")
    print(MANIFEST)


if __name__ == "__main__":
    main()
