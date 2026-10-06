"""Pulls products out of saved Eurovaistinė category pages (research, not the scraper).

Eurovaistinė's category pages (e.g. https://www.eurovaistine.lt/vitaminai-ir-maisto-papildai)
embed the full product JSON in the HTML, escaped. Each product object has:
  "variants":{..."sku":"<EAN>","inStock":true},"price":{"price":<cents or null>,"regularPrice":<cents>}
with its "slug" and an "ev_large" image URL earlier in the same object. The
object's "title" is NOT the product name (it's often the page title), so the
name is the "name"/"title" string whose slug matches the product slug.
A few products use another shape ("sku":["<EAN>"] with "price":N,"regularPrice":M);
this script skips those. "price": null means no current sale price.

Usage:
  curl -sL -A "Mozilla/5.0" https://www.eurovaistine.lt/vitaminai-ir-maisto-papildai -o vit.html
  python3 -I scripts/research/eurovaistine-category-json.py vit.html > products.json
Used on 2026-10-06 to build the local test data (storage/app/test-products.json).
"""
import json
import re
import sys
import unicodedata


def slugify(text):
    text = unicodedata.normalize('NFKD', text).encode('ascii', 'ignore').decode().lower()
    return re.sub(r'[^a-z0-9]+', '-', text).strip('-')


def parse(path):
    s = open(path, encoding='utf-8', errors='ignore').read().replace('\\"', '"').replace('\\/', '/')
    names = {}
    for m in re.finditer(r'"(?:name|title)":"([^"]{5,200})"', s):
        names.setdefault(slugify(m.group(1)), m.group(1))
    seen = set()
    for m in re.finditer(r'"sku":"(\d{8,14})","inStock":(true|false)\},"price":\{"price":(null|\d+),"regularPrice":(\d+)', s):
        ean = m.group(1)
        if ean in seen:
            continue
        before = s[max(0, m.start() - 20000):m.start()]
        images = re.findall(r'(https://api\.eurovaistine\.lt/media/cache/ev_large/[^"]+\.(?:png|jpg|jpeg|webp))', before)
        slugs = re.findall(r'"slug":"([a-z0-9-]{5,200})"', before)
        if not (images and slugs):
            continue
        slug = slugs[-1]
        price = m.group(3)
        seen.add(ean)
        yield {
            'ean': ean,
            'name': names.get(slug),
            'price': int(price) / 100 if price != 'null' else None,
            'regular': int(m.group(4)) / 100,
            'in_stock': m.group(2) == 'true',
            'image': images[-1],
            'url': f'https://www.eurovaistine.lt/{slug}',
        }


if __name__ == '__main__':
    rows = [row for path in sys.argv[1:] for row in parse(path)]
    json.dump(rows, sys.stdout, ensure_ascii=False, indent=1)
