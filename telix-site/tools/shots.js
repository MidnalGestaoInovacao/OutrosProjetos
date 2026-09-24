// uso: node tools/shots.js <url> <prefixo> [largura] [altura] — captura a página em fatias (viewport a viewport)
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
(async () => {
  const [,, url, prefix, w, h] = process.argv;
  const b = await chromium.launch({ proxy: process.env.HTTPS_PROXY ? { server: process.env.HTTPS_PROXY } : undefined, args: ['--ignore-gpu-blocklist', '--use-angle=swiftshader', '--enable-unsafe-swiftshader'] });
  const ctx = await b.newContext({ viewport: { width: +w || 1440, height: +h || 900 } });
  await ctx.addInitScript(() => { try { localStorage.setItem('tx_consent', JSON.stringify({ v: 2, id: 'test', ts: '', cats: { necessary: true, functional: false, analytics: false, marketing: false } })); } catch (e) {} });
  const p = await ctx.newPage(); const logs = [];
  p.on('console', m => { if (['error', 'warning'].includes(m.type())) logs.push(m.type() + ': ' + m.text()); });
  p.on('pageerror', e => logs.push('pageerror: ' + e.message));
  await p.goto(url, { waitUntil: 'networkidle', timeout: 120000 }).catch(e => logs.push('goto: ' + e.message));
  const H = await p.evaluate(() => document.documentElement.scrollHeight); const vh = +h || 900; let i = 0;
  for (let y = 0; y < H && i < 24; y += vh, i++) { await p.evaluate(yy => window.scrollTo(0, yy), y); await p.waitForTimeout(1300); await p.screenshot({ path: `${prefix}-${String(i).padStart(2, '0')}.png` }); }
  console.log(JSON.stringify({ height: H, shots: i, logs: logs.slice(0, 20) }));
  await b.close();
})();
