// Default Open Graph image (1200x630 PNG; Facebook and Messenger don't show
// SVG). Run inside Sail:
//   ./vendor/bin/sail exec -T laravel.test node scripts/brand/render-og.cjs
const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');
(async () => {
  const root = path.resolve(__dirname, '../..');
  const logo = fs.readFileSync(`${root}/public/assets/logo.svg`, 'utf8');
  const b = await puppeteer.launch({ headless: 'new', executablePath: '/usr/bin/google-chrome-stable', args: ['--no-sandbox', '--disable-setuid-sandbox'] });
  const p = await b.newPage();
  await p.setViewport({ width: 1200, height: 630 });
  await p.setContent(`<!doctype html><html><head><meta charset="utf-8"><style>
    body { margin: 0; width: 1200px; height: 630px; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; font-family: Inter, Arial, sans-serif; }
    .logo svg { width: 760px; height: auto; display: block; }
    p { margin: 48px 0 0; font-size: 44px; font-weight: 600; color: #13306a; }
    .bar { position: absolute; left: 0; right: 0; bottom: 0; height: 24px; background: #13306a; }
  </style></head><body><div class="logo">${logo}</div><p>Vaistų kainos visose vaistinėse</p><div class="bar"></div></body></html>`);
  await p.screenshot({ path: `${root}/public/assets/og-default.png` });
  await b.close();
})();
