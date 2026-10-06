#!/bin/bash
# Renders scripts/brand/icon.svg at 16/32/48 px in the Sail container's Chrome
# and packs public/favicon.ico (PNG-in-ICO). Run from the repo root.
set -euo pipefail
./vendor/bin/sail exec -T laravel.test node scripts/brand/render-icon.cjs
python3 - <<'PY'
import struct
imgs = [(s, open(f'storage/app/favicon-{s}.png', 'rb').read()) for s in (16, 32, 48)]
header = struct.pack('<HHH', 0, 1, len(imgs))
offset = 6 + 16 * len(imgs)
entries = data = b''
for size, png in imgs:
    entries += struct.pack('<BBBBHHII', size, size, 0, 0, 1, 32, len(png), offset + len(data))
    data += png
open('public/favicon.ico', 'wb').write(header + entries + data)
print('public/favicon.ico written')
PY
