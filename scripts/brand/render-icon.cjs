// Used by build-favicon.sh inside the Sail container.
const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');
(async () => {
  const root = path.resolve(__dirname, '../..');
  const svg = fs.readFileSync(`${__dirname}/icon.svg`, 'utf8');
  const b = await puppeteer.launch({ headless: 'new', executablePath: '/usr/bin/google-chrome-stable', args: ['--no-sandbox', '--disable-setuid-sandbox'] });
  const p = await b.newPage();
  for (const s of [16, 32, 48]) {
    await p.setViewport({ width: s, height: s });
    await p.setContent(`<body style="margin:0;background:transparent">${svg.replace('width="22" height="22"', `width="${s}" height="${s}"`)}</body>`);
    await p.screenshot({ path: `${root}/storage/app/favicon-${s}.png`, omitBackground: true });
  }
  await b.close();
})();
