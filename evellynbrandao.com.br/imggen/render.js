// Renderiza imagens (PNG/JPG) a partir de páginas HTML com Playwright/Chromium.
// Uso: node render.js spec.json   (spec: [{html, out, w, h, transparent}])
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const path = require('path');
const fs = require('fs');
(async () => {
  const spec = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ deviceScaleFactor: 1 });
  for (const it of spec) {
    const page = await ctx.newPage();
    await page.setViewportSize({ width: it.w, height: it.h });
    await page.goto('file://' + path.resolve(it.html), { waitUntil: 'load' });
    try { await page.evaluate(() => document.fonts.ready); } catch (e) { }
    await page.waitForTimeout(it.wait || 600);
    const isJpg = /\.jpe?g$/i.test(it.out);
    await page.screenshot({ path: it.out, type: isJpg ? 'jpeg' : 'png', quality: isJpg ? 86 : undefined, omitBackground: !!it.transparent, fullPage: false });
    await page.close();
    console.log('ok', it.out);
  }
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
