// Gera as capas das matérias (1200x675) e a imagem de compartilhamento (1200x630) com as fotos do próprio site.
// Uso: NODE_PATH=<node_modules com playwright-core> node imggen/covers.mjs
import { chromium } from "playwright-core";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.dirname(HERE);
const A = (p) => "file://" + path.join(ROOT, "assets", "img", p);
const PHOTOS = {
  "lavoura-fileiras": A("solucoes/vista-lavoura-fileiras-agronegocio.webp"),
  "aperto-maos": A("atuacao/aperto-maos-negocio-fechado-garantia-real.webp"),
  "estrada-porteira": A("atuacao/estrada-propriedade-rural-recuperacao-judicial.webp"),
  "graos-colhidos": A("solucoes/produtor-rural-graos-colhidos-cpr.webp"),
  "milho-produtor": A("solucoes/produtor-rural-plantacao-milho-comercializacao.webp"),
  "trator": A("solucoes/trator-implemento-agricola-credito-investimento.webp"),
  "pulverizacao": A("solucoes/pulverizacao-aerea-defensivos-agricolas-lavoura.webp"),
  "vinhedo-trator": A("formulario/vinhedo-trator-por-do-sol-credito-agronegocio.jpg"),
  "hero": A("hero/produtor-rural-credito-agronegocio.jpg"),
};
const POS = { "aperto-maos": "50% 35%", "graos-colhidos": "50% 25%", "milho-produtor": "50% 20%", hero: "50% 15%" };
const LOGO = A("logo/bs-agro-logo-white.png");
const FONTS = `@font-face{font-family:Raleway;font-weight:600;src:url(file://${path.join(HERE, "fonts", "raleway-latin-600-normal.woff2")})}
@font-face{font-family:Roboto;font-weight:500;src:url(file://${path.join(HERE, "fonts", "roboto-latin-500-normal.woff2")})}`;
const esc = (s) => s.replace(/&/g, "&amp;").replace(/</g, "&lt;");

function page({ w, h, photo, kicker, title, foot }) {
  return `<!doctype html><html><head><meta charset="utf-8"><style>${FONTS}
*{margin:0;box-sizing:border-box}html,body{width:${w}px;height:${h}px;overflow:hidden;background:#08201a}
.bg{position:absolute;inset:0;background:url(${PHOTOS[photo]}) center/cover;background-position:${POS[photo] || "50% 50%"};transform:scale(1.03)}
.ov{position:absolute;inset:0;background:linear-gradient(100deg,rgba(8,32,26,.94) 0%,rgba(13,53,39,.82) 42%,rgba(13,53,39,.25) 75%,rgba(16,19,26,.15) 100%)}
.ov2{position:absolute;inset:0;background:linear-gradient(0deg,rgba(8,32,26,.85) 0%,rgba(8,32,26,0) 40%)}
.frame{position:absolute;inset:28px;border:1px solid rgba(237,214,143,.28);border-radius:28px}
.c{position:absolute;left:84px;top:0;bottom:0;width:${w * 0.62}px;display:flex;flex-direction:column;justify-content:center;gap:26px}
.k{align-self:flex-start;font:500 17px/1 Roboto,sans-serif;letter-spacing:.16em;color:#08201a;background:#d4af37;padding:11px 18px;border-radius:99px}
h1{font:600 ${title.length > 42 ? 58 : 64}px/1.08 Raleway,sans-serif;letter-spacing:-.02em;color:#fbf9f4;text-wrap:balance}
.line{width:88px;height:3px;border-radius:3px;background:linear-gradient(90deg,#d4af37,#edd68f)}
.f{position:absolute;left:84px;right:84px;bottom:66px;display:flex;align-items:center;justify-content:space-between}
.f img{height:34px}.f span{font:500 17px/1 Roboto,sans-serif;letter-spacing:.08em;color:rgba(251,249,244,.75)}
</style></head><body><div class="bg"></div><div class="ov"></div><div class="ov2"></div><div class="frame"></div>
<div class="c"><span class="k">${esc(kicker)}</span><h1>${esc(title)}</h1><span class="line"></span></div>
<div class="f"><img src="${LOGO}" alt=""><span>${esc(foot)}</span></div></body></html>`;
}

const posts = JSON.parse(fs.readFileSync(path.join(ROOT, "content", "posts.json"), "utf8"));
const jobs = posts.map((p) => ({ out: `capa-${p.slug}.jpg`, w: 1200, h: 675, photo: p.cover_photo, kicker: p.kicker, title: p.cover_headline, foot: "bsagro.agr.br · Matérias" }));
jobs.push({ out: "og-bs-agro-capital.jpg", w: 1200, h: 630, photo: "hero", kicker: "CRÉDITO ESTRUTURADO PARA O AGRO", title: "Capital para fortalecer quem produz.", foot: "bsagro.agr.br" });
jobs.push({ out: "og-conformidade.jpg", w: 1200, h: 630, photo: "vinhedo-trator", kicker: "LGPD · COMPLIANCE · ESG", title: "Transparência e integridade em cada operação.", foot: "bsagro.agr.br" });
jobs.push({ out: "og-area-do-cliente.jpg", w: 1200, h: 630, photo: "trator", kicker: "ÁREA DO CLIENTE", title: "Solicite, envie documentos e acompanhe seu crédito.", foot: "bsagro.agr.br" });

const exe = process.env.CHROMIUM || "/opt/pw-browsers/chromium-1194/chrome-linux/chrome";
const proxy = process.env.HTTPS_PROXY ? { server: process.env.HTTPS_PROXY } : undefined;
const browser = await chromium.launch({ executablePath: exe, proxy, args: ["--allow-file-access-from-files"] });
for (const j of jobs) {
  const pg = await browser.newPage({ viewport: { width: j.w, height: j.h }, deviceScaleFactor: 1 });
  const tmp = path.join(HERE, "out", "_tmp.html");
  fs.writeFileSync(tmp, page(j));
  await pg.goto("file://" + tmp, { waitUntil: "networkidle" });
  await pg.evaluate(() => document.fonts.ready);
  await pg.screenshot({ path: path.join(HERE, "out", j.out), type: "jpeg", quality: 86 });
  await pg.close();
  console.log("ok", j.out);
}
await browser.close();
fs.rmSync(path.join(HERE, "out", "_tmp.html"), { force: true });
