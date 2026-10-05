// Gera as capas das matérias (1200x630) com categoria, título, chamada e um infográfico,
// uma por idioma: src/img/posts/<slug>.png (PT), <slug>.en.png e <slug>.es.png.
// Textos da chamada e do infográfico: src/covers.json. Título e categoria: meta da matéria + src/i18n/{en,es}.json.
// Uso: npm i playwright && node tools/covers.mjs [slug ...]   (depois: python3 tools/covers_webp.py)
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';

const ROOT = process.env.A3_ROOT || path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const SRC = path.join(ROOT, 'src');
const OUT = path.join(SRC, 'img', 'posts');
fs.mkdirSync(OUT, { recursive: true });

const LANGS = ['pt', 'en', 'es'];
const ACCENT = { // categoria -> [cor de destaque, fundo suave, giro do anel]
  'Gestão e Conformidade': ['#0a7a80', '#d9f6f2', 0],
  'Compliance e Integridade': ['#b45309', '#ffecd6', 40],
  'LGPD e Privacidade': ['#6b3be6', '#ece4fd', 80],
  'Segurança da Informação': ['#17306d', '#dfe7f3', 120],
  'Pessoas e Treinamentos': ['#1f6feb', '#e1ecfe', 160],
  'Comunicação Interna': ['#0a7a80', '#dcf5f5', 200],
  'Negócios e Licitações': ['#b45309', '#fdebd3', 240],
  'Hospedagem e Infraestrutura': ['#1e5fd6', '#e2ebfd', 280],
};
const icons = fs.readFileSync(path.join(SRC, 'partials', 'icons.svg'), 'utf8');
const logo = 'data:image/png;base64,' + fs.readFileSync(path.join(SRC, 'img', 'logo-h.png')).toString('base64');
const font = 'data:font/woff2;base64,' + fs.readFileSync(path.join(SRC, 'fonts', 'Manrope-VariableFont_wght.woff2')).toString('base64');
const SPEC = JSON.parse(fs.readFileSync(path.join(SRC, 'covers.json'), 'utf8'));
const DICT = { en: JSON.parse(fs.readFileSync(path.join(SRC, 'i18n', 'en.json'), 'utf8')), es: JSON.parse(fs.readFileSync(path.join(SRC, 'i18n', 'es.json'), 'utf8')) };

/* mesma chave de tradução do build (t + sha1 do texto normalizado) */
const norm = (s) => s.replace(/\s+/g, ' ').trim();
const keyFor = (s) => 't' + crypto.createHash('sha1').update(norm(s), 'utf8').digest('hex').slice(0, 9);
const trPt = (pt, lang) => (lang === 'pt' ? pt : DICT[lang][keyFor(pt)] || pt);
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/SHA-256/g, '<span class="nw">SHA-256</span>');
/* texto do covers.json: string neutra ou {pt,en,es} */
const T = (v, lang) => esc(v && typeof v === 'object' ? (v[lang] ?? v.pt) : v);
const ic = (n, s = 20, extra = '') => `<svg class="i" width="${s}" height="${s}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ${extra}><use href="#i-${n}"/></svg>`;
const roundHalfEven = (x) => { const f = Math.floor(x), d = x - f; return d > 0.5 ? f + 1 : d < 0.5 ? f : (f % 2 ? f + 1 : f); };

const posts = fs.readdirSync(path.join(SRC, 'posts')).filter((f) => f.endsWith('.html')).map((f) => {
  const raw = fs.readFileSync(path.join(SRC, 'posts', f), 'utf8');
  const m = raw.match(/<!--meta\s*(\{[\s\S]*?\})\s*-->/);
  if (!m) return null;
  const meta = JSON.parse(m[1]);
  const words = raw.slice(m.index + m[0].length).replace(/<[^>]+>/g, ' ').split(/\s+/).filter(Boolean).length;
  meta.read = Math.max(3, roundHalfEven(words / 210));
  return meta;
}).filter(Boolean);

/* ------------------------------------------------------------ infográficos */
function qrSvg(size) {
  const n = 21, k = size / n; let r = '';
  for (let i = 0; i < n; i++) for (let j = 0; j < n; j++) if (((i * 7 + j * 13 + i * j) % 5) < 2) r += `<rect x="${i * k}" y="${j * k}" width="${k}" height="${k}"/>`;
  [[0, 0], [n - 7, 0], [0, n - 7]].forEach(([a, b]) => { r += `<rect x="${a * k}" y="${b * k}" width="${7 * k}" height="${7 * k}" fill="#0b1a3f"/><rect x="${(a + 1) * k}" y="${(b + 1) * k}" width="${5 * k}" height="${5 * k}" fill="#fff"/><rect x="${(a + 2) * k}" y="${(b + 2) * k}" width="${3 * k}" height="${3 * k}" fill="#0b1a3f"/>`; });
  return `<svg width="${size}" height="${size}" viewBox="0 0 ${size} ${size}"><rect width="${size}" height="${size}" fill="#fff"/><g fill="#0b1a3f">${r}</g></svg>`;
}
const head = (txt, icon, right = '') => `<div class="ih"><span class="ih-t">${ic(icon, 18)}${txt}</span>${right}</div>`;

const INFO = {
  compare(d, L) {
    return head(T(d.head, L), 'scale') +
      `<div class="cmp-h"><span class="neg">${ic('x', 14)}${T(d.weak, L)}</span><span class="pos">${ic('check', 14)}${T(d.strong, L)}</span></div>` +
      '<div class="cmp-rows">' + d.rows.map((r) => `<div class="cmp-r"><div class="q">${T(r.q, L)}</div><div class="pair"><span class="pill bad"><span>${T(r.a, L)}</span></span>${ic('arrow', 18, 'style="color:#8a97b0"')}<span class="pill good"><span>${T(r.b, L)}</span></span></div></div>`).join('') + '</div>';
  },
  seal(d, L) {
    const C = 2 * Math.PI * 34, on = C * d.pct / 100;
    return head(T(d.doc, L), 'file-check') +
      `<div class="seal-ck"><span class="box">${ic('check', 18)}</span><b>${T(d.check, L)}</b></div>` +
      `<div class="fields">${d.fields.map(([k, v]) => `<div><span>${T(k, L)}</span><b class="${/…/.test(v) ? 'mono' : ''}">${esc(v)}</b></div>`).join('')}</div>` +
      `<div class="donut-row"><svg width="92" height="92" viewBox="0 0 92 92"><circle cx="46" cy="46" r="34" fill="none" stroke="#e6edf5" stroke-width="12"/><circle cx="46" cy="46" r="34" fill="none" stroke="var(--acc)" stroke-width="12" stroke-linecap="round" stroke-dasharray="${on} ${C}" transform="rotate(-90 46 46)"/><text x="46" y="52" text-anchor="middle" font-size="19" font-weight="800" fill="#0e1f4d">${d.pct}%</text></svg>` +
      `<div><b>${T(d.pctLabel, L)}</b><span>${ic('bell', 14)}${T(d.pending, L)}</span></div></div>`;
  },
  flow(d, L) {
    const code = d.codeT ? T(d.codeT, L) : esc(d.code);
    return head(T(d.head, L), 'flow', `<span class="code">${code}</span>`) +
      `<div class="flow">${d.steps.map(([t, s], i) => `<div class="st ${s}"><span class="dot">${s === 'done' ? ic('check', 16) : s === 'active' ? '<i></i>' : i + 1}</span><b>${T(t, L)}</b></div>`).join('')}</div>` +
      `<div class="badges">${d.badges.map(([i, t]) => `<span>${ic(i, 15)}${T(t, L)}</span>`).join('')}</div>`;
  },
  countdown(d, L) {
    const last = d.steps[d.steps.length - 1][0];
    return `<div class="cd"><div class="big">${esc(d.big)}<small>${T(d.unit, L)}</small></div><p>${T(d.label, L)}</p></div>` +
      `<div class="cd-list">${d.steps.map(([day, t]) => `<div><span class="day">${T(d.day, L)} ${day}</span>${ic('check', 16, 'style="color:#12805c"')}<b>${T(t, L)}</b></div>`).join('')}</div>` +
      `<div class="cd-bar"><div class="track"><i style="width:${(last / 15) * 100}%"></i></div><div class="cd-legend"><span>${T(d.day, L)} 0</span><span>${T(d.spare, L)}</span><span>${T(d.day, L)} 15</span></div></div>`;
  },
  chain(d, L) {
    return head(T(d.head, L), 'finger') +
      `<div class="chain">${d.blocks.map(([n, ev, h, s], i) => `${i ? `<div class="link ${s === 'bad' || s === 'off' ? 'broken' : ''}">${ic('link', 14)}</div>` : ''}<div class="blk ${s}"><span class="n">#${n}</span><span class="ev"><b>${ev}</b>${s === 'bad' ? `<em>${T(d.bad, L)}</em>` : ''}</span><span class="hash">${h}</span><span class="mk">${s === 'ok' ? ic('check', 16) : s === 'bad' ? ic('x', 16) : ic('minus', 16)}</span></div>`).join('')}</div>` +
      `<div class="verify"><span class="btn">${ic('refresh', 15)}${T(d.button, L)}</span><span class="res">${ic('alert', 16)}${T(d.result, L)}</span></div>`;
  },
  course(d, L) {
    return head(T(d.head, L), 'grad') +
      `<div class="crs">${d.steps.map(([t, v, s]) => `<div class="${s}"><span class="mk">${s === 'done' ? ic('check', 15) : ic('clock', 15)}</span><b>${T(t, L)}</b><span class="v">${esc(v)}</span></div>`).join('')}</div>` +
      `<div class="cert"><div><span class="aw">${ic('award', 26)}</span><b>${T(d.cert, L)}</b><span>${T(d.verify, L)}</span><span class="iso">${esc(d.iso)}</span></div>${qrSvg(112)}</div>`;
  },
  timeline(d, L) {
    return `<div class="tl">${d.dates.map(([dt, t, s]) => `<div class="${s}"><span class="pt"></span><b>${T(dt, L)}</b><span>${T(t, L)}</span></div>`).join('')}</div>` +
      head(T(d.head, L), 'check') +
      `<ol class="mig">${d.steps.map((t, i) => `<li><span class="num">${i + 1}</span><b>${T(t, L)}</b>${ic('check', 16, 'style="color:#12805c;margin-left:auto"')}</li>`).join('')}</ol>`;
  },
  bars(d, L) {
    const W = 436, H = 280, base = H - 34, top = 30, max = Math.max(...d.series.flatMap((s) => s.values));
    const gw = W / d.groups.length, bw = 56, gap = 12;
    const money = (v) => L === 'en' ? `R$ ${(v / 1000).toLocaleString('en-US', { maximumFractionDigits: 1 })}${d.fmt.en}` : `R$ ${(v / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} ${T(d.fmt, L)}`;
    let g = `<line x1="0" x2="${W}" y1="${base}" y2="${base}" stroke="#cfd9e6" stroke-width="1.5"/>`;
    d.groups.forEach((n, gi) => {
      const cx = gw * gi + gw / 2;
      d.series.forEach((s, si) => {
        const h = (s.values[gi] / max) * (base - top), x = cx - bw - gap / 2 + si * (bw + gap), y = base - h, r = 4;
        g += `<path d="M${x},${base} V${y + r} Q${x},${y} ${x + r},${y} H${x + bw - r} Q${x + bw},${y} ${x + bw},${y + r} V${base} Z" fill="${s.color}"/>`;
        g += `<text x="${x + bw / 2}" y="${y - 8}" text-anchor="middle" font-size="12.5" font-weight="800" fill="#0e1f4d">${money(s.values[gi])}</text>`;
      });
      g += `<text x="${cx}" y="${base + 22}" text-anchor="middle" font-size="13" font-weight="700" fill="#56637f">${n} ${T(d.people, L)}</text>`;
    });
    return head(T(d.head, L), 'chart') +
      `<div class="legend">${d.series.map((s) => `<span><i style="background:${s.color}"></i>${T(s.name, L)}</span>`).join('')}</div>` +
      `<svg class="chart" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}">${g}</svg>` +
      `<div class="save"><b>${esc(d.badge)}</b><span>${T(d.badgeLabel, L)}</span></div>`;
  },
  checklist(d, L) {
    return head(T(d.head, L), 'shield', `<span class="code ok">${ic('check', 14)}${esc(d.score)}</span>`) +
      `<div class="ckg">${d.items.map(([i, t, s]) => `<div><span class="ib">${ic(i, 20)}</span><span class="tx"><b>${T(t, L)}</b><span>${T(s, L)}</span></span><span class="ok">${ic('check', 14)}</span></div>`).join('')}</div>`;
  },
};

/* ------------------------------------------------------------ página da capa */
function coverHtml(p, L) {
  const spec = SPEC[p.slug];
  if (!spec) throw new Error(`src/covers.json: falta a capa de "${p.slug}"`);
  const [acc, soft, rot] = ACCENT[p.category] || ['#0a7a80', '#d9f6f2', 0];
  const ui = SPEC._ui;
  return `<!doctype html><html lang="${L === 'pt' ? 'pt-BR' : L}"><head><meta charset="utf-8"><style>
  @font-face{font-family:M;src:url("${font}") format("woff2");font-weight:200 800}
  :root{--acc:${acc};--soft:${soft}}
  *{box-sizing:border-box;margin:0;padding:0}
  body{width:1200px;height:630px;overflow:hidden;font-family:M,sans-serif;position:relative;color:#2b3653;-webkit-font-smoothing:antialiased}
  .bg{position:absolute;inset:0;background:linear-gradient(135deg,#f8fafd 0%,#eef4fa 55%,#f3effd 100%)}
  .b{position:absolute;border-radius:50%;filter:blur(70px)}
  .b1{width:560px;height:560px;right:-160px;top:-220px;background:rgba(47,221,190,.42)}
  .b2{width:460px;height:460px;left:-180px;bottom:-260px;background:rgba(107,59,230,.20)}
  .b3{width:320px;height:320px;left:430px;top:420px;background:rgba(255,154,31,.18)}
  .grid{position:absolute;inset:0;background-image:linear-gradient(rgba(14,31,77,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(14,31,77,.045) 1px,transparent 1px);background-size:44px 44px;-webkit-mask:radial-gradient(ellipse 60% 70% at 25% 40%,#000 20%,transparent 75%)}
  .topline{position:absolute;left:0;right:0;top:0;height:8px;background:linear-gradient(90deg,#2fddbe,#1f9beb 35%,#6b3be6 70%,#ff9a1f)}
  .ring{position:absolute;right:-110px;top:-120px;width:380px;height:380px;border-radius:50%;
    background:conic-gradient(from ${rot}deg,#2fddbe 0 22%,transparent 22% 25%,#ff9a1f 25% 47%,transparent 47% 50%,#6b3be6 50% 72%,transparent 72% 75%,#1f8bf0 75% 97%,transparent 97%);
    -webkit-mask:radial-gradient(circle,transparent 58%,#000 59%,#000 71%,transparent 72%);opacity:.9}
  .left{position:absolute;left:64px;top:52px;width:556px;height:530px;display:flex;flex-direction:column;overflow:hidden}
  .chip{display:inline-flex;align-self:flex-start;align-items:center;gap:10px;padding:7px 16px 7px 7px;border-radius:999px;background:#fff;border:1px solid #e2e8f0;box-shadow:0 6px 18px -10px rgba(14,31,77,.35);color:var(--acc);font-weight:800;font-size:15px;letter-spacing:.06em;text-transform:uppercase}
  .chip .ib{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:var(--soft)}
  h1{margin-top:22px;color:#0b1a3f;font-weight:800;font-size:52px;line-height:1.08;letter-spacing:-.025em;text-wrap:balance}
  .hook{margin-top:18px;font-size:23px;line-height:1.42;font-weight:500;color:#45526e;text-wrap:pretty}
  .hook::before{content:"";display:block;width:56px;height:5px;border-radius:4px;background:var(--acc);margin-bottom:16px;opacity:.85}
  .foot{margin-top:auto;display:flex;align-items:center;gap:16px;padding-top:18px}
  .foot img{height:46px}
  .foot .sep{width:1px;height:34px;background:#cfd9e6}
  .foot .meta{display:flex;flex-direction:column;font-size:14px;font-weight:700;color:#56637f;line-height:1.35}
  .foot .meta b{color:#0b1a3f;font-size:15px}
  .panel{position:absolute;left:656px;top:42px;width:500px;height:546px;padding:26px 30px;border-radius:30px;background:rgba(255,255,255,.94);border:1px solid #fff;box-shadow:0 40px 80px -30px rgba(14,31,77,.38),0 0 0 1px rgba(14,31,77,.05);display:flex;flex-direction:column;gap:14px;overflow:hidden}
  .i{flex:none;vertical-align:middle}
  .nw{white-space:nowrap}
  .ih{display:flex;align-items:center;justify-content:space-between;gap:10px}
  .ih-t{display:inline-flex;align-items:center;gap:9px;font-size:15px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:#0e1f4d}
  .ih-t .i{color:var(--acc)}
  .code{font:700 13px ui-monospace,"DejaVu Sans Mono",monospace;background:#eef3f8;color:#17306d;border-radius:8px;padding:5px 9px;display:inline-flex;gap:6px;align-items:center;white-space:nowrap}
  .code.ok{background:#e3f5ee;color:#12805c;font-family:M,sans-serif;font-size:15px;font-weight:800}
  .mono{font-family:ui-monospace,"DejaVu Sans Mono",monospace}
  /* compare */
  .cmp-h{display:grid;grid-template-columns:1fr 26px 1fr;font-size:12.5px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;margin-top:2px}
  .cmp-h span{display:inline-flex;gap:6px;align-items:center}.cmp-h .neg{color:#c62f3a}.cmp-h .pos{color:#12805c;grid-column:3}
  .cmp-rows{flex:1;display:flex;flex-direction:column;justify-content:space-evenly}
  .cmp-r{border-top:1px dashed #e2e8f0;padding-top:14px;display:grid;gap:10px}
  .cmp-r .q{font-weight:800;color:#0b1a3f;font-size:17px}
  .pair{display:grid;grid-template-columns:1fr 26px 1fr;align-items:center}
  .pill{border-radius:12px;padding:10px 12px;font-size:14.5px;font-weight:700;line-height:1.3;min-height:54px;display:flex;align-items:center}
  .pill.bad{background:#fdecee;color:#9b2530;text-decoration:line-through;text-decoration-color:rgba(198,47,58,.45)}
  .pill.good{background:#e3f5ee;color:#0e6b4c}
  /* seal */
  .seal-ck{display:flex;align-items:center;gap:12px;background:var(--soft);border-radius:14px;padding:12px 14px;font-size:19px;color:#0b1a3f}
  .seal-ck .box{width:30px;height:30px;border-radius:8px;background:var(--acc);color:#fff;display:grid;place-items:center}
  .fields{display:grid;gap:0;border:1px solid #e6edf5;border-radius:14px;overflow:hidden}
  .fields div{display:flex;justify-content:space-between;gap:14px;padding:11px 14px;font-size:15.5px}
  .fields div+div{border-top:1px solid #eef2f7}
  .fields span{color:#7e8aa3;font-weight:600}.fields b{color:#0b1a3f;font-weight:800}
  .donut-row{display:flex;align-items:center;gap:16px;margin-top:auto}
  .donut-row div{display:grid;gap:6px}.donut-row b{font-size:18px;color:#0b1a3f}
  .donut-row span{display:inline-flex;gap:7px;align-items:center;font-size:14px;color:#56637f;font-weight:600}
  /* flow */
  .flow{flex:1;display:flex;flex-direction:column;margin:0 0 0 6px}
  .st{flex:1;display:flex;align-items:center;gap:16px;position:relative;padding:6px 0}
  .st:not(:last-child)::after{content:"";position:absolute;left:17px;top:calc(50% + 22px);height:calc(100% - 44px);width:3px;border-radius:3px;background:#dfe6ef}
  .st.done:not(:last-child)::after{background:var(--acc)}
  .st .dot{width:37px;height:37px;border-radius:50%;display:grid;place-items:center;flex:none;font-weight:800;font-size:15px;background:#eef2f7;color:#7e8aa3;border:2px solid #dfe6ef}
  .st.done .dot{background:var(--acc);border-color:var(--acc);color:#fff}
  .st.active .dot{background:#fff;border:3px solid #ff9a1f;box-shadow:0 0 0 7px rgba(255,154,31,.18)}
  .st.active .dot i{width:12px;height:12px;border-radius:50%;background:#ff9a1f}
  .st b{font-size:20px;color:#0b1a3f}.st.todo b{color:#7e8aa3}
  .badges{display:flex;flex-wrap:wrap;gap:8px}
  .badges span{display:inline-flex;gap:7px;align-items:center;background:var(--soft);color:#0b1a3f;border-radius:999px;padding:8px 13px;font-size:13.5px;font-weight:700}
  .badges .i{color:var(--acc)}
  /* countdown */
  .cd{display:flex;align-items:center;gap:18px}
  .cd .big{font-size:112px;line-height:.9;font-weight:800;letter-spacing:-.05em;color:var(--acc);display:flex;align-items:baseline;gap:8px}
  .cd .big small{font-size:28px;letter-spacing:0;color:#0b1a3f}
  .cd p{font-size:17px;font-weight:700;color:#45526e;line-height:1.4}
  .cd-list{flex:1;display:flex;flex-direction:column;justify-content:space-evenly}
  .cd-list div{display:grid;grid-template-columns:76px 20px 1fr;align-items:center;gap:8px;background:#f6f8fb;border-radius:12px;padding:9px 12px;font-size:16px}
  .cd-list .day{font-weight:800;color:var(--acc);font-size:14px;text-transform:uppercase;letter-spacing:.04em}
  .cd-list b{color:#0b1a3f}
  .cd-bar{}
  .track{height:14px;border-radius:999px;background:#e6edf5;overflow:hidden}
  .track i{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#2fddbe,var(--acc))}
  .cd-legend{display:flex;justify-content:space-between;margin-top:8px;font-size:13px;font-weight:700;color:#56637f}
  .cd-legend span:nth-child(2){color:#12805c}
  /* chain */
  .chain{flex:1;display:flex;flex-direction:column;justify-content:center}
  .blk{display:grid;grid-template-columns:62px 1fr auto 28px;align-items:center;gap:10px;border:1.5px solid #e2e8f0;border-radius:14px;padding:13px 12px;background:#fff}
  .blk .n{font:800 14px ui-monospace,"DejaVu Sans Mono",monospace;color:#7e8aa3}
  .blk .ev b{font:800 15px ui-monospace,"DejaVu Sans Mono",monospace;color:#0b1a3f;display:block}
  .blk .ev em{font-style:normal;font-size:12.5px;font-weight:700;color:#c62f3a}
  .blk .hash{font:700 13px ui-monospace,"DejaVu Sans Mono",monospace;background:#eef3f8;border-radius:7px;padding:4px 8px;color:#17306d}
  .blk .mk{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;background:#e3f5ee;color:#12805c}
  .blk.bad{border-color:#f2b8bd;background:#fff6f7}.blk.bad .mk{background:#fdecee;color:#c62f3a}.blk.bad .hash{background:#fdecee;color:#9b2530}
  .blk.off{opacity:.55;border-style:dashed}.blk.off .mk{background:#eef2f7;color:#7e8aa3}
  .link{height:30px;display:grid;place-items:center;color:var(--acc)}
  .link.broken{color:#c62f3a}
  .verify{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
  .verify .btn{display:inline-flex;gap:8px;align-items:center;background:#0e1f4d;color:#fff;border-radius:999px;padding:11px 16px;font-size:14.5px;font-weight:800}
  .verify .res{display:inline-flex;gap:7px;align-items:center;color:#c62f3a;font-weight:800;font-size:15px}
  /* course */
  .crs{flex:1;display:flex;flex-direction:column;justify-content:space-evenly}
  .crs div{display:grid;grid-template-columns:28px 1fr auto;align-items:center;gap:10px;border-radius:12px;background:#f6f8fb;padding:10px 12px;font-size:16.5px}
  .crs .mk{width:26px;height:26px;border-radius:50%;display:grid;place-items:center;background:#e3f5ee;color:#12805c}
  .crs .active .mk{background:#fff1df;color:#b45309}
  .crs b{color:#0b1a3f}.crs .v{font-weight:800;color:var(--acc);font-size:15px}
  .cert{display:flex;align-items:center;justify-content:space-between;gap:14px;border-radius:18px;padding:16px 18px;background:linear-gradient(135deg,var(--soft),#fff);border:1.5px solid #dbe5f2}
  .cert div{display:grid;gap:4px}.cert .aw{color:var(--acc)}
  .cert b{font-size:22px;color:#0b1a3f}.cert span{font-size:14px;font-weight:700;color:#56637f}
  .cert .iso{justify-self:start;background:#0e1f4d;color:#fff;border-radius:8px;padding:4px 9px;font-size:13px;margin-top:4px}
  .cert svg{border-radius:8px;box-shadow:0 0 0 6px #fff,0 10px 24px -12px rgba(14,31,77,.5)}
  /* timeline */
  .tl{display:grid;grid-template-columns:repeat(3,1fr);position:relative;padding-top:20px;margin-bottom:6px}
  .tl::before{content:"";position:absolute;left:8px;right:8px;top:27px;height:3px;background:linear-gradient(90deg,#cfd9e6,#c62f3a)}
  .tl div{position:relative;display:grid;gap:3px;padding-right:8px}
  .tl .pt{width:17px;height:17px;border-radius:50%;background:#fff;border:3px solid #8a97b0;margin-top:-1px;margin-bottom:8px}
  .tl .end .pt{border-color:#c62f3a;background:#c62f3a}
  .tl b{font-size:18px;color:#0b1a3f}.tl span{font-size:14px;font-weight:600;color:#56637f}
  .tl .end b{color:#c62f3a}
  .mig{list-style:none;flex:1;display:flex;flex-direction:column;justify-content:space-evenly}
  .mig li{display:flex;align-items:center;gap:12px;background:#f6f8fb;border-radius:12px;padding:10px 12px;font-size:17px}
  .mig .num{width:28px;height:28px;border-radius:50%;background:var(--acc);color:#fff;display:grid;place-items:center;font-weight:800;font-size:14px;flex:none}
  .mig b{color:#0b1a3f}
  /* bars */
  .legend{display:grid;gap:6px}
  .legend span{display:inline-flex;gap:9px;align-items:center;font-size:14.5px;font-weight:700;color:#2b3653}
  .legend i{width:14px;height:14px;border-radius:4px;flex:none}
  .chart{display:block;font-family:M,sans-serif;margin-top:4px}
  .save{margin-top:auto;display:flex;align-items:center;gap:14px;background:#e3f5ee;border-radius:16px;padding:10px 16px}
  .save b{font-size:40px;font-weight:800;color:#0e6b4c;letter-spacing:-.03em}
  .save span{font-size:15px;font-weight:700;color:#0e6b4c;line-height:1.35}
  /* checklist */
  .ckg{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:4px;flex:1}
  .ckg div{position:relative;display:flex;gap:11px;align-items:flex-start;border:1.5px solid #e6edf5;border-radius:16px;padding:14px 12px;background:#fff}
  .ckg .ib{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;background:var(--soft);color:var(--acc);flex:none}
  .ckg .tx{display:grid;gap:3px;padding-right:18px}
  .ckg b{font-size:17px;color:#0b1a3f}.ckg .tx span{font-size:13.5px;font-weight:600;color:#56637f;line-height:1.35}
  .ckg .ok{position:absolute;right:10px;top:10px;width:22px;height:22px;border-radius:50%;display:grid;place-items:center;background:#12805c;color:#fff}
  </style></head><body>${icons}
  <div class="bg"></div><div class="b b1"></div><div class="b b2"></div><div class="b b3"></div><div class="grid"></div><div class="ring"></div><div class="topline"></div>
  <div class="left">
    <span class="chip"><span class="ib">${ic(p.icon || 'book', 18)}</span>${esc(trPt(p.category, L))}</span>
    <h1>${esc(trPt(p.title, L))}</h1>
    <p class="hook">${T(spec.hook, L)}</p>
    <div class="foot"><img src="${logo}" alt=""><span class="sep"></span><span class="meta"><b>${T(ui.kind, L)} · ${p.read} ${T(ui.read, L)}</b>alicerce360.com.br</span></div>
  </div>
  <div class="panel">${INFO[spec.info.type](spec.info, L)}</div>
  </body></html>`;
}

/* reduz a fonte do título (e depois da chamada) até tudo caber na coluna */
async function fit(page) {
  return page.evaluate(() => {
    const left = document.querySelector('.left'), h1 = document.querySelector('h1'), hook = document.querySelector('.hook'), panel = document.querySelector('.panel');
    const over = (el) => el.scrollHeight > el.clientHeight + 1;
    let fs = 52;
    while ((over(left) || h1.getBoundingClientRect().height > 4 * fs * 1.08 + 2) && fs > 34) { fs -= 1; h1.style.fontSize = fs + 'px'; }
    let hs = 23;
    while (over(left) && hs > 17) { hs -= 1; hook.style.fontSize = hs + 'px'; }
    let ps = 1;
    while (over(panel) && ps > 0.8) { ps -= 0.02; panel.style.zoom = ps; }
    return { title: fs, hook: hs, panel: Math.round(ps * 100) / 100, leftOver: over(left), panelOver: over(panel) };
  });
}

const only = process.argv.slice(2);
const browser = await chromium.launch(process.env.CHROME ? { executablePath: process.env.CHROME } : {});
const page = await browser.newPage({ viewport: { width: 1200, height: 630 }, deviceScaleFactor: 1 });
let problems = 0;
for (const p of posts) {
  if (only.length && !only.includes(p.slug)) continue;
  for (const L of LANGS) {
    await page.setContent(coverHtml(p, L), { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);
    const r = await fit(page);
    await page.screenshot({ path: path.join(OUT, `${p.slug}${L === 'pt' ? '' : '.' + L}.png`) });
    if (r.leftOver || r.panelOver) problems++;
    console.log(`capa ${p.slug} [${L}] título ${r.title}px · chamada ${r.hook}px · painel ${r.panel}${r.leftOver || r.panelOver ? '  ⚠ texto não coube' : ''}`);
  }
}
await browser.close();
if (problems) process.exitCode = 1;
