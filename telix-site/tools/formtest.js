// Testa envio real de um formulário no site publicado: node tools/formtest.js <url> <out.png>
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
(async () => {
  const [,, url, out] = process.argv;
  const b = await chromium.launch({ proxy: process.env.HTTPS_PROXY ? { server: process.env.HTTPS_PROXY } : undefined });
  const ctx = await b.newContext({ viewport: { width: 1280, height: 900 } });
  await ctx.addInitScript(() => { try { localStorage.setItem('tx_consent', JSON.stringify({ v: 2, id: 't', ts: '', cats: { necessary: true, functional: false, analytics: false, marketing: false } })); } catch (e) {} });
  const p = await ctx.newPage(); const logs = [];
  p.on('console', m => { if (['error', 'warning'].includes(m.type())) logs.push(m.type() + ': ' + m.text()); });
  p.on('pageerror', e => logs.push('pageerror: ' + e.message));
  let ok = false;
  for (let i = 0; i < 10 && !ok; i++) {
    try { await p.goto(url + (url.includes('?') ? '&' : '?') + 'r=' + i, { waitUntil: 'domcontentloaded', timeout: 90000 }); await p.waitForSelector('form.tx-form', { timeout: 20000 }); ok = true; }
    catch (e) { logs.push('goto retry ' + e.message.slice(0, 90)); }
  }
  if (!ok) { console.log(JSON.stringify({ res: 'NOLOAD', logs })); await b.close(); return; }
  await p.waitForFunction(() => { const q = document.querySelector('.tx-captcha__q'); return q && q.textContent.trim().length > 0; }, null, { timeout: 60000 }).catch(() => logs.push('captcha não renderizou (JS?)'));
  const f = p.locator('form.tx-form').first();
  await f.scrollIntoViewIfNeeded();
  // preenche campos visíveis
  const inputs = await f.locator('input:not([type=hidden]):not([type=checkbox]):not([type=radio]):visible, textarea:visible').all();
  for (const el of inputs) {
    const id = await el.getAttribute('id') || '';
    if (id.endsWith('-cap') || (await el.getAttribute('name')) === 'website') continue;
    const type = await el.getAttribute('type');
    const val = type === 'email' ? 'teste.automatizado@example.com' : type === 'tel' ? '(61) 99999-0000' : type === 'url' ? 'https://example.com' : 'TESTE AUTOMATIZADO — pode excluir. Mensagem de validação do novo site Télix.';
    await el.fill(val);
  }
  for (const s of await f.locator('select:visible').all()) { const opts = await s.locator('option').all(); if (opts.length > 1) { const v = await opts[1].getAttribute('value'); await s.selectOption(v !== null ? v : await opts[1].innerText()); } }
  for (const c of await f.locator('input[type=checkbox][required]:visible').all()) await c.check();
  const q = await f.locator('.tx-captcha__q').innerText();
  const m = q.match(/(\d+)\s*([+−-])\s*(\d+)/); const ans = m[2] === '+' ? (+m[1] + +m[3]) : (+m[1] - +m[3]);
  await f.locator('.tx-captcha input').fill(String(ans));
  await p.waitForTimeout(3600);
  await f.locator('button[type=submit]').click();
  await p.waitForSelector('.tx-form__success, .tx-alert--err', { timeout: 120000 }).catch(() => logs.push('sem resposta'));
  await p.waitForTimeout(800);
  const res = await p.evaluate(() => { const s = document.querySelector('.tx-form__success'); const e = document.querySelector('.tx-form .tx-alert--err'); return s ? 'OK ' + (document.querySelector('.tx-protocol') || {}).textContent : 'ERR ' + (e ? e.textContent : '?'); });
  await (await p.$('.tx-form')).screenshot({ path: out }).catch(() => {});
  console.log(JSON.stringify({ res, logs: logs.slice(0, 12) }));
  await b.close();
})();
