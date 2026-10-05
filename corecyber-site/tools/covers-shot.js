// Fotografa as capas das matérias (chamado por tools/covers.py; lê a lista de tarefas do stdin).
let pw;
try { pw = require('playwright'); } catch (e) {
  pw = require((process.env.PLAYWRIGHT_PATH || '/opt/node22/lib/node_modules/playwright'));
}
const jobs = JSON.parse(require('fs').readFileSync(0, 'utf8'));
(async () => {
  const browser = await pw.chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1200, height: 675 }, deviceScaleFactor: 1.5 });
  for (const j of jobs) {
    await page.goto(j.html, { waitUntil: 'load' });
    await page.waitForSelector('body[data-ready="1"]', { timeout: 15000 });
    await page.screenshot({ path: j.png, type: 'png' });
  }
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
