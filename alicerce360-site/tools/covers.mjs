// Gera as capas das matérias (1200x630, sem texto, válidas nos 3 idiomas).
// Uso: npm i playwright && node tools/covers.mjs   (depois: python3 tools/covers_webp.py)
// Lê src/posts/*.html (slug, icon, category) e grava src/img/posts/<slug>.png
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = process.env.A3_ROOT || path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const SRC = path.join(ROOT, 'src');
const OUT = path.join(SRC, 'img', 'posts');
fs.mkdirSync(OUT, { recursive: true });

const ACCENT = {
  'Gestão e Conformidade': ['#0a7a80', '#d9f6f2', 0],
  'Compliance e Integridade': ['#c45f00', '#ffecd6', 40],
  'LGPD e Privacidade': ['#6b3be6', '#ece4fd', 80],
  'Segurança da Informação': ['#0e1f4d', '#dfe7f3', 120],
  'Pessoas e Treinamentos': ['#1f6feb', '#e1ecfe', 160],
  'Comunicação Interna': ['#0a8f8f', '#dcf5f5', 200],
  'Negócios e Licitações': ['#b45309', '#fdebd3', 240],
  'Hospedagem e Infraestrutura': ['#1e5fd6', '#e2ebfd', 280],
};
const icons = fs.readFileSync(path.join(SRC, 'partials', 'icons.svg'), 'utf8');
const logo = 'data:image/png;base64,' + fs.readFileSync(path.join(SRC, 'img', 'logo-h.png')).toString('base64');
const font = 'file://' + path.join(SRC, 'fonts', 'Manrope-VariableFont_wght.woff2');

const posts = fs.readdirSync(path.join(SRC, 'posts')).filter((f) => f.endsWith('.html')).map((f) => {
  const raw = fs.readFileSync(path.join(SRC, 'posts', f), 'utf8');
  const m = raw.match(/<!--meta\s*(\{[\s\S]*?\})\s*-->/);
  return m ? JSON.parse(m[1]) : null;
}).filter(Boolean);

const only = process.argv.slice(2);
const browser = await chromium.launch(process.env.CHROME ? { executablePath: process.env.CHROME } : {});
const page = await browser.newPage({ viewport: { width: 1200, height: 630 }, deviceScaleFactor: 1 });
for (const [i, p] of posts.entries()) {
  if (only.length && !only.includes(p.slug)) continue;
  const [acc, soft, rot] = ACCENT[p.category] || ['#0a7a80', '#d9f6f2', i * 36];
  const ic = (n, s) => `<svg width="${s}" height="${s}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="#i-${n}"/></svg>`;
  const html = `<!doctype html><html><head><meta charset="utf-8"><style>
  @font-face{font-family:M;src:url("${font}") format("woff2");font-weight:200 800}
  *{box-sizing:border-box} body{margin:0;width:1200px;height:630px;overflow:hidden;font-family:M,sans-serif;position:relative}
  .bg{position:absolute;inset:0;background:linear-gradient(135deg,#f8fafd 0%,#eef4fa 55%,#f4f0fd 100%)}
  .b{position:absolute;border-radius:50%;filter:blur(70px)}
  .b1{width:520px;height:520px;right:-120px;top:-180px;background:rgba(47,221,190,.45)}
  .b2{width:460px;height:460px;left:-140px;bottom:-220px;background:rgba(107,59,230,.25)}
  .b3{width:300px;height:300px;left:520px;top:380px;background:rgba(255,154,31,.22)}
  .grid{position:absolute;inset:0;background-image:linear-gradient(rgba(14,31,77,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(14,31,77,.05) 1px,transparent 1px);background-size:48px 48px;-webkit-mask:radial-gradient(ellipse 70% 70% at 70% 40%,#000 30%,transparent 75%)}
  .ring{position:absolute;right:70px;top:65px;width:500px;height:500px;border-radius:50%;
    background:conic-gradient(from ${rot}deg,#2fddbe 0 22%,transparent 22% 25%,#ff9a1f 25% 47%,transparent 47% 50%,#6b3be6 50% 72%,transparent 72% 75%,#1f8bf0 75% 97%,transparent 97%);
    -webkit-mask:radial-gradient(circle,transparent 57%,#000 58%,#000 70%,transparent 71%);filter:drop-shadow(0 20px 30px rgba(14,31,77,.25))}
  .halo{position:absolute;right:62px;top:57px;width:516px;height:516px;border-radius:50%;border:4px solid #0e1f4d;opacity:.9}
  .core{position:absolute;right:195px;top:190px;width:250px;height:250px;border-radius:50%;display:grid;place-items:center;
    background:radial-gradient(circle at 35% 30%,#fff,${soft});box-shadow:inset 0 0 0 1px rgba(14,31,77,.06),0 30px 60px -25px rgba(14,31,77,.45);color:${acc}}
  .card{position:absolute;left:70px;top:120px;width:470px;padding:34px;border-radius:30px;background:rgba(255,255,255,.86);border:1px solid #fff;box-shadow:0 40px 80px -30px rgba(14,31,77,.35)}
  .ib{width:82px;height:82px;border-radius:22px;display:grid;place-items:center;background:${soft};color:${acc};margin-bottom:24px}
  .ln{height:14px;border-radius:8px;background:#e6edf5;margin:12px 0}
  .ln.a{width:88%;background:linear-gradient(90deg,${acc},#1f8bf0);opacity:.85;height:18px}
  .ln.b{width:72%} .ln.c{width:56%}
  .pills{display:flex;gap:10px;margin-top:22px}
  .pill{height:30px;border-radius:999px;background:${soft};width:110px}
  .pill.ok{background:#dff3ea;width:130px} .pill.o{background:#fff1df;width:90px}
  .logo{position:absolute;left:70px;bottom:44px;height:58px}
  .chip{position:absolute;right:40px;bottom:40px;display:flex;gap:8px}
  .chip span{width:12px;height:12px;border-radius:50%}
  </style></head><body>${icons}
  <div class="bg"></div><div class="b b1"></div><div class="b b2"></div><div class="b b3"></div><div class="grid"></div>
  <div class="halo"></div><div class="ring"></div><div class="core">${ic(p.icon || 'book', 120)}</div>
  <div class="card"><div class="ib">${ic(p.icon || 'book', 44)}</div><div class="ln a"></div><div class="ln b"></div><div class="ln c"></div>
  <div class="pills"><div class="pill ok"></div><div class="pill"></div><div class="pill o"></div></div></div>
  <img class="logo" src="${logo}">
  <div class="chip"><span style="background:#2fddbe"></span><span style="background:#1f8bf0"></span><span style="background:#6b3be6"></span><span style="background:#ff9a1f"></span></div>
  </body></html>`;
  await page.setContent(html, { waitUntil: 'load' });
  await page.waitForTimeout(150);
  await page.screenshot({ path: path.join(OUT, `${p.slug}.png`) });
  console.log('capa', p.slug);
}
await browser.close();
