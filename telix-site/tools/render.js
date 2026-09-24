const { chromium } = require('/opt/node22/lib/node_modules/playwright');
(async () => {
  const [,, url, out, w, h, transparent] = process.argv;
  const b = await chromium.launch({ proxy: process.env.HTTPS_PROXY ? { server: process.env.HTTPS_PROXY } : undefined });
  const p = await b.newPage({ viewport: { width: +w, height: +h } });
  await p.goto(url, { waitUntil: 'networkidle' }); await p.waitForTimeout(800);
  await p.screenshot({ path: out, omitBackground: transparent === '1' }); await b.close();
})();
