// uso: node tools/shot.js <url> <out.png> [largura] [altura] [full=1] [acao]
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
(async () => {
  const [,, url, out, w, h, full, action] = process.argv;
  const b = await chromium.launch({ proxy: process.env.HTTPS_PROXY ? { server: process.env.HTTPS_PROXY, bypass: '127.0.0.1,localhost' } : undefined, args: ['--ignore-gpu-blocklist', '--use-angle=swiftshader', '--enable-unsafe-swiftshader'] });
  const p = await b.newPage({ viewport: { width: +w || 1366, height: +h || 860 }, deviceScaleFactor: 1 });
  const logs = [];
  p.on('console', m => { if (['error', 'warning'].includes(m.type())) logs.push(m.type() + ': ' + m.text()); });
  p.on('pageerror', e => logs.push('pageerror: ' + e.message));
  await p.goto(url, { waitUntil: 'networkidle', timeout: 120000 }).catch(e => logs.push('goto: ' + e.message));
  if (action === 'mega') { await p.hover('.tx-nav__item.has-mega:nth-of-type(3) .tx-nav__link').catch(()=>{}); }
  if (action && action.startsWith('mega:')) { const i = action.split(':')[1]; await p.hover(`.tx-nav > ul > li:nth-child(${i}) > .tx-nav__link`).catch(()=>{}); }
  if (action === 'a11y') { await p.click('.tx-a11y-btn').catch(()=>{}); }
  if (action === 'cookiemodal') { await p.waitForTimeout(900); await p.click('[data-tx-cookie="customize"]').catch(()=>{}); }
  if (action === 'fab') { await p.click('.tx-fab__toggle').catch(()=>{}); }
  if (action === 'drawer') { await p.click('.tx-burger').catch(()=>{}); }
  if (full === '1') { await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); } window.scrollTo(0, 0); }); }
  await p.waitForTimeout(2500);
  await p.screenshot({ path: out, fullPage: full === '1' });
  console.log(JSON.stringify(logs.slice(0, 30)));
  await b.close();
})();
