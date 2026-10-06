// Builds public/assets/logo.svg, logo-white.svg and scripts/brand/icon.svg
// (the favicon source) for eVaistine.lt: navy rounded tile with a green
// pharmacy cross, wordmark "eVaistine" navy + ".lt" green, outlined from
// Inter 600 so it renders the same in emails as on the site.
//
// opentype.js isn't a project dependency. Run from a temp dir that has it:
//   cd "$(mktemp -d)" && npm i opentype.js@1.3.4 && NODE_PATH=$PWD/node_modules \
//     node /Users/tomas/www/vaistines/scripts/brand/make-logo.cjs
// then build the favicon with scripts/brand/build-favicon.sh.
const opentype = require('opentype.js');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../..');
const font = opentype.loadSync(`${root}/node_modules/@fontsource/inter/files/inter-latin-600-normal.woff`);
const SIZE = 18.4, BASE = 17.23, TRACK = 0.3;
const NAVY = '#0F234A', GREEN = '#58A618';

function word(segments, x0) {
  let x = x0; const out = [];
  for (const [text, fill] of segments) {
    let d = '';
    for (const ch of text) {
      const g = font.charToGlyph(ch);
      d += g.getPath(x, BASE, SIZE).toPathData(2);
      x += g.advanceWidth * SIZE / font.unitsPerEm + TRACK;
    }
    out.push(`<path d="${d}" fill="${fill}"/>`);
  }
  return { paths: out.join('\n'), width: Math.ceil(x - TRACK + 1) };
}
const plus = (fill) =>
  `<rect x="9" y="4.6" width="4" height="12.8" rx="1.3" fill="${fill}"/>` +
  `<rect x="4.6" y="9" width="12.8" height="4" rx="1.3" fill="${fill}"/>`;
const tile = (fill) => `<rect x="1" y="1" width="20" height="20" rx="5" fill="${fill}"/>`;
const svg = (w, body, vb = `0 0 ${w} 22`) =>
  `<svg width="${w}" height="22" viewBox="${vb}" fill="none" xmlns="http://www.w3.org/2000/svg">\n${body}\n</svg>\n`;

const dark = word([['eVaistine', NAVY], ['.lt', GREEN]], 28);
const light = word([['eVaistine', 'white'], ['.lt', GREEN]], 28);
fs.writeFileSync(`${root}/public/assets/logo.svg`, svg(dark.width, tile(NAVY) + plus(GREEN) + '\n' + dark.paths));
fs.writeFileSync(`${root}/public/assets/logo-white.svg`, svg(light.width, tile('white') + plus(NAVY) + '\n' + light.paths));
fs.writeFileSync(`${__dirname}/icon.svg`, svg(22, tile(NAVY) + plus(GREEN), '1 1 20 20'));
console.log(`logo width ${dark.width}px; update width="..." on the <img> tags if it changed`);
