// Auditoria multi-dispositivo + SEO renderizado: node tools/qa.js <dir-preview> <saida.json> [paginas...]
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const fs = require('fs'), path = require('path');
const [,, dir, out, ...only] = process.argv;
const VP = [['mobile-360', 360, 740, true], ['phone-landscape-844', 844, 390, true], ['laptop-1366', 1366, 768, false], ['iphone-390', 390, 844, true], ['tablet-768', 768, 1024, true], ['tablet-1024', 1024, 768, true], ['laptop-1280', 1280, 800, false], ['desktop-1920', 1920, 1080, false]];
(async () => {
  const b = await chromium.launch({ proxy: process.env.HTTPS_PROXY ? { server: process.env.HTTPS_PROXY } : undefined, args: ['--use-angle=swiftshader', '--enable-unsafe-swiftshader'] });
  const files = fs.readdirSync(dir).filter(f => f.endsWith('.html') && (!only.length || only.includes(f.replace('.html', ''))));
  const report = {};
  for (const f of files) {
    report[f] = {};
    for (const [name, w, h, touch] of VP) {
      const ctx = await b.newContext({ viewport: { width: w, height: h }, hasTouch: touch, isMobile: touch && w < 800, deviceScaleFactor: 1 });
      await ctx.addInitScript(() => { try { localStorage.setItem('tx_consent', JSON.stringify({ v: 2, id: 't', ts: '', cats: { necessary: true } })); } catch (e) {} });
      const p = await ctx.newPage(); const errs = [];
      p.on('pageerror', e => errs.push(e.message));
      await p.goto('file://' + path.resolve(dir, f), { waitUntil: 'load', timeout: 60000 }).catch(e => errs.push('goto ' + e.message));
      await p.waitForTimeout(900);
      const r = await p.evaluate(({ touch }) => {
        const vw = document.documentElement.clientWidth, issues = [];
        if (document.documentElement.scrollWidth > vw + 1) issues.push('overflow-x ' + document.documentElement.scrollWidth + '>' + vw);
        const wide = [];
        document.querySelectorAll('.tx-main *').forEach(el => {
          const cs = getComputedStyle(el); if (cs.display === 'none' || cs.visibility === 'hidden' || el.closest('.tx-marquee,.tx-3d,.tx-ring,.tx-mega,.tx-drawer')) return;
          const rc = el.getBoundingClientRect(); if (rc.width && (rc.right > vw + 2 || rc.left < -2)) wide.push((el.className && typeof el.className === 'string' ? el.className.split(' ')[0] : el.tagName) + ':' + Math.round(rc.right));
        });
        if (wide.length) issues.push('fora-da-tela ' + [...new Set(wide)].slice(0, 6).join(','));
        const small = [];
        document.querySelectorAll('.tx-main p, .tx-main li, .tx-main td, .tx-main label, .tx-main a').forEach(el => { const fs = parseFloat(getComputedStyle(el).fontSize); if (fs < 12 && el.offsetParent) small.push(el.tagName + ':' + fs); });
        if (small.length) issues.push('fonte<12px x' + small.length);
        if (touch) {
          const tiny = [];
          document.querySelectorAll('a[href], button, input, select, summary').forEach(el => {
            if (!el.offsetParent || el.closest('.tx-mega,.tx-hp,p,li,td,dd,.tx-breadcrumb,.tx-small,.tx-help,.tx-footer__legal,.tx-sitemap,.tx-toc')) return;
            const rc = el.getBoundingClientRect(); if (rc.width && (rc.height < 32 || rc.width < 32)) tiny.push((el.textContent || el.getAttribute('aria-label') || el.tagName).trim().slice(0, 25) + '(' + Math.round(rc.width) + 'x' + Math.round(rc.height) + ')');
          });
          if (tiny.length) issues.push('toque-pequeno ' + [...new Set(tiny)].slice(0, 6).join(' | '));
        }
        const hidden = document.querySelector('.tx-burger'); const nav = document.querySelector('.tx-nav');
        const navOk = getComputedStyle(nav).display !== 'none' || getComputedStyle(hidden).display !== 'none';
        if (!navOk) issues.push('sem-navegação');
        // SEO renderizado
        const m = n => (document.querySelector(`meta[name="${n}"],meta[property="${n}"]`) || {}).content || '';
        const ld = [...document.querySelectorAll('script[type="application/ld+json"]')].map(s => { try { return JSON.parse(s.text); } catch (e) { return 'INVALID'; } });
        const seo = { title: document.title, tlen: document.title.length, desc: m('description').length, canonical: !!document.querySelector('link[rel=canonical]'), og: !!m('og:title') && !!m('og:image') && !!m('og:description'), h1: document.querySelectorAll('h1').length, ld: ld.map(x => x === 'INVALID' ? x : (x['@graph'] || [x]).map(g => [].concat(g['@type']).join('+')).join(',')).join(' ; '), imgNoAlt: [...document.images].filter(i => !i.hasAttribute('alt')).length, lang: document.documentElement.lang, viewport: !!document.querySelector('meta[name=viewport]') };
        return { issues, seo };
      }, { touch });
      if (errs.length) r.issues.push('js: ' + errs.slice(0, 2).join(' / '));
      report[f][name] = r;
      await ctx.close();
    }
  }
  fs.writeFileSync(out, JSON.stringify(report, null, 1));
  await b.close();
})();
