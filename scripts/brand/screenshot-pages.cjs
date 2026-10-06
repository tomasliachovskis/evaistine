// Visual check: screenshots key pages at 390px and 1440px into
// storage/app/screenshots/. Run inside Sail:
//   ./vendor/bin/sail exec -T laravel.test node scripts/brand/screenshot-pages.cjs [/path ...]
const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');
const out = path.resolve(__dirname, '../../storage/app/screenshots');
fs.mkdirSync(out, { recursive: true });
const paths = process.argv.slice(2).length ? process.argv.slice(2) : ['/', '/vaistines', '/akcijos/vitaminai-ir-maisto-papildai'];
(async () => {
  const b = await puppeteer.launch({ headless: 'new', executablePath: '/usr/bin/google-chrome-stable', args: ['--no-sandbox', '--disable-setuid-sandbox'] });
  const p = await b.newPage();
  for (const url of paths) {
    for (const w of [390, 1440]) {
      await p.setViewport({ width: w, height: 900 });
      await p.goto('http://localhost' + url, { waitUntil: 'networkidle0', timeout: 60000 });
      await p.evaluate(() => {
        const btn = [...document.querySelectorAll('button')].find(el => el.offsetParent && el.textContent.trim().startsWith('Tik būtini'));
        if (btn) btn.click();
      });
      await new Promise(r => setTimeout(r, 600));
      const name = (url.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'home') + `-${w}.png`;
      await p.screenshot({ path: `${out}/${name}`, fullPage: true });
      console.log(`${out}/${name}`);
    }
  }
  await b.close();
})();
