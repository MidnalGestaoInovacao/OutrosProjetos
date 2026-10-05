/* =====================================================================
   CoreCyber · cenas 3D (módulo ES carregado sob demanda pelo núcleo)
   - hero: o "C" octogonal do logo, anéis, núcleo e escudo que intercepta ameaças
   - globe: globo pontilhado com ataques convergindo e sendo bloqueados no Brasil
   - paper-hand: relatório amassado controlado pela mão (MediaPipe, opcional)
   - paper-parallax: papéis em profundidade real (câmera em perspectiva)
   ===================================================================== */
import * as THREE from 'https://cdnjs.cloudflare.com/ajax/libs/three.js/0.170.0/three.module.min.js';

const MP_VER = '0.10.14';
const MP_BASE = `https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@${MP_VER}`;
const HAND_MODEL = 'https://storage.googleapis.com/mediapipe-models/hand_landmarker/hand_landmarker/float16/1/hand_landmarker.task';
const COL = { navy: 0x002141, petrol: 0x005783, blue: 0x0090ad, cyan: 0x19c3d6, ice: 0xb8f6fb, red: 0xff5b5b, amber: 0xf59f45, green: 0x3ddc97 };
/* máscara de continentes: linhas x colunas, passo em graus, bits em base64 (gerada com global-land-mask) */
const LAND = '72,144,2.5,AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAD8B/wAAAAAAAAAAAAAAAAAAH/////gAQAzgAYAAAAAAAAAM/////+AD+AAAAfAAAAAAAAB//+f//+AAgAB8J/8AcAAAAAD/f/Af/+AAAAHBP//8IAAgfgP/u/4P/8AAHgOP/////8Bx/////98P/4AB/9/////////9/////8+P+PgB///////////J//////+HwHgP///////////B////+D8DgABP//////////8AfD//+D+AAACP/////////3AAeA///z/AAAHHv///////wPADgA/////wAAPP////////8OUIAAH////wAAL/////////8MAAAAH///+4AAD/////////8AAAAAD////YAAB/////////9AAAAAD///+AAAP//X//////uAAAAAD///wAAAP3///////8IAAAAAD///gAAAPXv//////sYAAAAAB///AAAAP/P//////t4AAAAAB//+AAAAP+AP/////jgAAAAAAf/8AAAAP////////iAAAAAAAP9MAAADf////////gAAAAAAAP4EAAAA/////////AAAAAAAAF4OAAAB/////H///gAAAAIAAB93AAAB/////H//8AAAAAEAAA/h8AAB////+B8f4gAAAAAAAAP4BAAB///98B4fwgAAAAAAAAB4AAAB////wBwHwwAAAAAAAAAZ/AAB////4AwFx4AAAAAAAAAP/gAA////4A4EiYAAAAAAAAAB/4AAf///wAIbD4AAAAAAAAAB/8AAMP//wAAPHAAAAAAAAAAD/8AAAH//AAAHfUAAAAAAAABH//gAAH/+AAAH/3SAAAAAAAAH//4AAD/8AAAD/1+wAAAAAAAH//8AAB/8AAAB9hfoAAAAAAAD//8AAB/8AAAAP6fOAAAAAAAD//8AAB/+AAAAADMgAAAAAAAB//4AAB/+QAAAAPsAAAAAAAAA//4AAD//4AAAAf+AwAAAAAAAf/4AAD/5wAAAA/+ABAAAAAAAP/wAAB/xyAAAH//BgAAAAAAAf/wAAB/5gAAAH//gAAAAAAAAf+AAAA/wgAAAH//wAAAAAAAAf+AAAA/gAAAAH//wAAAAAAAAf8AAAAfgAAAAD//wAAAAAAAAf4AAAAfAAAAADx/gEAAAAAAA/wAAAAAAAAAAAAfAGAAAAAAA/gAAAAAAAAAAAAOAHAAAAAAA/AAAAAAAAAAAAADAOQAAAAAA+AAAAAAAAAAAAADAcAAAAAAA8AAAAAAAAAAAAAAA4AAAAAAB8AAAAAAAAEAAAAAAAAAAAAAA5gAAAAAAAAAAAAAAAAAAAAAA8AEAAAAAAAAAAAAAAAAAAAAAIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAgAAAAAAAAAAAAAAAAAAAAAADgAAAAAAAAAAAAAAAAAAAAAAGAAAAAAAeAH/8/8AAAAAAAAA/AAAAAAT/8/////8AAAAAAcA/AAAf///////////wAAAX8///AAB////////////wAB/////+AB/////////////AA//////4Dn/////////////AAH/////8P//////////////A8f//////////////////////////////////////////////////////////////////////';
let CC = null;

/* ------------------------------------------------------------ utilidades */
function makeRenderer(host) {
  const r = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
  r.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
  r.outputColorSpace = THREE.SRGBColorSpace;
  r.toneMapping = THREE.ACESFilmicToneMapping;
  r.toneMappingExposure = 1.05;
  r.domElement.setAttribute('aria-hidden', 'true');
  host.appendChild(r.domElement);
  return r;
}
function fit(renderer, camera, host) {
  const w = host.clientWidth || 300, h = host.clientHeight || 300;
  renderer.setSize(w, h, false);
  camera.aspect = w / h;
  camera.updateProjectionMatrix();
}
/* laço que pausa fora da tela, com aba oculta ou movimento reduzido */
function runLoop(host, tick) {
  let visible = false, raf = 0, last = performance.now();
  const frame = (now) => {
    const dt = Math.min(0.05, (now - last) / 1000); last = now;
    tick(dt, now / 1000);
    raf = (visible && !document.hidden && !CC.motionOff()) ? requestAnimationFrame(frame) : 0;
  };
  const kick = () => { if (!raf) { last = performance.now(); raf = requestAnimationFrame(frame); } };
  new IntersectionObserver((e) => { visible = e[0].isIntersecting; if (visible) kick(); }, { rootMargin: '100px' }).observe(host);
  document.addEventListener('visibilitychange', kick);
  document.addEventListener('cc:a11y', () => { tick(0, performance.now() / 1000); kick(); });
  return { kick, once: () => tick(0, performance.now() / 1000) };
}
const lerp = (a, b, t) => a + (b - a) * t;
const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
function glowTexture(rgb) {
  const c = document.createElement('canvas'); c.width = c.height = 128;
  const g = c.getContext('2d'), gr = g.createRadialGradient(64, 64, 2, 64, 64, 64);
  gr.addColorStop(0, `rgba(${rgb},1)`); gr.addColorStop(0.25, `rgba(${rgb},.45)`); gr.addColorStop(1, `rgba(${rgb},0)`);
  g.fillStyle = gr; g.fillRect(0, 0, 128, 128);
  const t = new THREE.CanvasTexture(c); t.colorSpace = THREE.SRGBColorSpace; return t;
}

/* ------------------------------------------------------------ HERO: logo 3D + escudo */
/* contorno do "C" octogonal: octógono externo com abertura à direita e furo circular */
function cShape(R, r, gap) {
  const A = R * Math.cos(Math.PI / 8), s = new THREE.Shape(), N = 160;
  const rOct = (phi) => { const k = Math.round(phi / (Math.PI / 4)) * (Math.PI / 4); return A / Math.cos(phi - k); };
  const a0 = gap, a1 = Math.PI * 2 - gap;
  for (let i = 0; i <= N; i++) {
    const phi = a0 + (a1 - a0) * i / N, rr = rOct(phi);
    const x = Math.cos(phi) * rr, y = Math.sin(phi) * rr;
    if (i === 0) s.moveTo(x, y); else s.lineTo(x, y);
  }
  for (let i = 0; i <= N; i++) {
    const phi = a1 - (a1 - a0) * i / N;
    s.lineTo(Math.cos(phi) * r, Math.sin(phi) * r);
  }
  s.closePath();
  return s;
}
function gradientColors(geo, R) {
  const p = geo.attributes.position, cols = [], cA = new THREE.Color(COL.navy), cB = new THREE.Color(COL.petrol), cC = new THREE.Color(COL.cyan), tmp = new THREE.Color();
  for (let i = 0; i < p.count; i++) {
    const t = clamp((p.getX(i) + p.getY(i)) / (2.4 * R) + 0.5, 0, 1);
    if (t < 0.55) tmp.copy(cA).lerp(cB, t / 0.55); else tmp.copy(cB).lerp(cC, (t - 0.55) / 0.45);
    cols.push(tmp.r, tmp.g, tmp.b);
  }
  geo.setAttribute('color', new THREE.Float32BufferAttribute(cols, 3));
}
function arc(radius, tube, from, len, color, emissive) {
  const g = new THREE.TorusGeometry(radius, tube, 14, 96, len);
  const m = new THREE.Mesh(g, new THREE.MeshPhysicalMaterial({ color, roughness: 0.28, metalness: 0.15, clearcoat: 0.8, emissive: emissive || 0x000000, emissiveIntensity: emissive ? 0.35 : 0 }));
  m.rotation.z = from; m.scale.z = 0.7;
  return m;
}
function initHero(host) {
  const renderer = makeRenderer(host);
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(32, 1, 0.1, 100);
  camera.position.set(0, 0.2, 10.5);
  scene.add(new THREE.HemisphereLight(0xffffff, 0xcfe6f2, 1.2));
  const key = new THREE.DirectionalLight(0xffffff, 2.2); key.position.set(-4, 6, 6); scene.add(key);
  const rim = new THREE.PointLight(COL.cyan, 22, 20); rim.position.set(3.5, 2.5, 3); scene.add(rim);
  const low = new THREE.PointLight(COL.blue, 10, 18); low.position.set(-3, -3, 2); scene.add(low);

  const root = new THREE.Group(); scene.add(root);
  /* "C" octogonal */
  const R = 2.2;
  const cg = new THREE.ExtrudeGeometry(cShape(R, 1.52, 0.42), { depth: 0.46, bevelEnabled: true, bevelThickness: 0.08, bevelSize: 0.07, bevelSegments: 4, curveSegments: 12 });
  cg.translate(0, 0, -0.23); gradientColors(cg, R);
  const shell = new THREE.Mesh(cg, new THREE.MeshPhysicalMaterial({ vertexColors: true, roughness: 0.3, metalness: 0.25, clearcoat: 0.9, clearcoatRoughness: 0.2 }));
  root.add(shell);
  /* anéis (como no logo): externos e internos em partes, girando em sentidos opostos */
  const ringO = new THREE.Group(), ringI = new THREE.Group(); root.add(ringO); root.add(ringI);
  ringO.add(arc(1.18, 0.1, Math.PI * 0.53, Math.PI * 0.93, COL.navy));
  ringO.add(arc(1.18, 0.1, Math.PI * 1.5, Math.PI * 0.38, COL.navy));
  ringO.add(arc(1.18, 0.1, Math.PI * 0.07, Math.PI * 0.42, COL.cyan, COL.cyan));
  ringI.add(arc(0.84, 0.085, Math.PI * 0.55, Math.PI * 0.85, COL.petrol));
  ringI.add(arc(0.84, 0.085, Math.PI * 0.03, Math.PI * 0.44, COL.cyan, COL.cyan));
  /* satélite (ponto do logo) */
  const sat = new THREE.Mesh(new THREE.SphereGeometry(0.15, 32, 20), new THREE.MeshPhysicalMaterial({ color: COL.blue, roughness: 0.2, clearcoat: 1, emissive: COL.cyan, emissiveIntensity: 0.3 }));
  ringO.add(sat); sat.position.set(Math.cos(Math.PI * 0.07) * 1.18, Math.sin(Math.PI * 0.07) * 1.18, 0);
  /* núcleo */
  const core = new THREE.Mesh(new THREE.SphereGeometry(0.52, 48, 32), new THREE.MeshPhysicalMaterial({ color: 0x0a6a96, roughness: 0.18, metalness: 0.1, clearcoat: 1, emissive: COL.cyan, emissiveIntensity: 0.22 }));
  root.add(core);
  const halo = new THREE.Sprite(new THREE.SpriteMaterial({ map: glowTexture('25,195,214'), transparent: true, depthWrite: false, opacity: 0.55, blending: THREE.AdditiveBlending }));
  halo.scale.set(2.6, 2.6, 1); root.add(halo);
  /* escudo */
  const SR = 3.05;
  const shieldMat = new THREE.MeshBasicMaterial({ color: COL.cyan, wireframe: true, transparent: true, opacity: 0.07, depthWrite: false });
  const shield = new THREE.Mesh(new THREE.IcosahedronGeometry(SR, 3), shieldMat); scene.add(shield);
  const ripples = [];
  const rippleGeo = new THREE.RingGeometry(0.05, 0.12, 40);
  function ripple(pos) {
    const m = new THREE.Mesh(rippleGeo, new THREE.MeshBasicMaterial({ color: COL.cyan, transparent: true, opacity: 0.9, side: THREE.DoubleSide, depthWrite: false, blending: THREE.AdditiveBlending }));
    m.position.copy(pos); m.lookAt(pos.clone().multiplyScalar(2)); m.userData.t = 0;
    scene.add(m); ripples.push(m);
  }
  /* ameaças: chegam de fora, batem no escudo, ficam neutralizadas (ciano) e se afastam */
  const threats = [], tGeo = new THREE.OctahedronGeometry(0.11, 0);
  function spawn(t, first) {
    const dir = new THREE.Vector3(Math.random() * 2 - 1, (Math.random() * 2 - 1) * 0.7, Math.random() * 0.8 - 0.2).normalize();
    t.position.copy(dir).multiplyScalar(first ? 4 + Math.random() * 4 : 7.5 + Math.random() * 2);
    t.userData.v = dir.clone().multiplyScalar(-(1.1 + Math.random() * 1.3));
    t.userData.hit = false; t.userData.life = 1;
    t.material.color.setHex(Math.random() < 0.7 ? COL.red : COL.amber); t.material.emissive.setHex(t.material.color.getHex());
    t.material.opacity = 1; t.scale.setScalar(0.8 + Math.random() * 0.6);
  }
  for (let i = 0; i < 11; i++) {
    const m = new THREE.Mesh(tGeo, new THREE.MeshStandardMaterial({ color: COL.red, emissive: COL.red, emissiveIntensity: 0.5, roughness: 0.4, transparent: true }));
    spawn(m, true); scene.add(m); threats.push(m);
  }
  /* poeira de dados */
  const pg = new THREE.BufferGeometry(), pts = [];
  for (let i = 0; i < 220; i++) { const v = new THREE.Vector3().randomDirection().multiplyScalar(3.4 + Math.random() * 3); pts.push(v.x, v.y, v.z * 0.6 - 1); }
  pg.setAttribute('position', new THREE.Float32BufferAttribute(pts, 3));
  const dust = new THREE.Points(pg, new THREE.PointsMaterial({ color: COL.blue, size: 0.035, transparent: true, opacity: 0.6 })); scene.add(dust);

  let tx = 0, ty = 0, scrollK = 0, hits = 0;
  window.addEventListener('pointermove', (e) => { tx = (e.clientX / innerWidth - 0.5); ty = (e.clientY / innerHeight - 0.5); }, { passive: true });
  window.addEventListener('scroll', () => { scrollK = Math.min(1, scrollY / 900); }, { passive: true });
  const out = host.parentNode && host.parentNode.querySelector('[data-blocked]');
  const resize = () => fit(renderer, camera, host);
  new ResizeObserver(resize).observe(host); resize();
  let first = true;
  const loop = runLoop(host, (dt, time) => {
    const still = CC.motionOff();
    ringO.rotation.z = still ? 0 : ringO.rotation.z - dt * 0.35;
    ringI.rotation.z = still ? 0 : ringI.rotation.z + dt * 0.55;
    root.rotation.y = lerp(root.rotation.y, tx * 0.7 + (still ? 0 : Math.sin(time * 0.4) * 0.12), 0.06);
    root.rotation.x = lerp(root.rotation.x, ty * 0.4 + scrollK * 0.35, 0.06);
    core.scale.setScalar(still ? 1 : 1 + Math.sin(time * 2.2) * 0.035);
    halo.material.opacity = still ? 0.5 : 0.45 + Math.sin(time * 2.2) * 0.12;
    shield.rotation.y += still ? 0 : dt * 0.05;
    shieldMat.opacity = lerp(shieldMat.opacity, 0.07, 0.05);
    threats.forEach((t) => {
      if (still) { t.visible = false; return; }
      t.visible = true;
      t.position.addScaledVector(t.userData.v, dt);
      t.rotation.x += dt * 2; t.rotation.y += dt * 3;
      const d = t.position.length();
      if (!t.userData.hit && d < SR) {
        t.userData.hit = true; hits++;
        const n = t.position.clone().normalize();
        ripple(n.clone().multiplyScalar(SR));
        t.userData.v.reflect(n).multiplyScalar(0.55);
        t.material.color.setHex(COL.cyan); t.material.emissive.setHex(COL.cyan);
        shieldMat.opacity = 0.2;
        if (out) out.textContent = CC.num ? CC.num(hits) : hits;
      }
      if (t.userData.hit) { t.userData.life -= dt * 0.7; t.material.opacity = Math.max(0, t.userData.life); if (t.userData.life <= 0) spawn(t); }
      if (d > 11) spawn(t);
    });
    for (let i = ripples.length - 1; i >= 0; i--) {
      const r = ripples[i]; r.userData.t += dt;
      r.scale.setScalar(1 + r.userData.t * 9); r.material.opacity = Math.max(0, 0.9 - r.userData.t * 1.3);
      if (r.userData.t > 0.7) { scene.remove(r); r.material.dispose(); ripples.splice(i, 1); }
    }
    dust.rotation.y = still ? 0 : time * 0.02;
    renderer.render(scene, camera);
    if (first) { first = false; host.classList.add('is-3d'); }
  });
  loop.once();
}

/* ------------------------------------------------------------ GLOBO de ameaças */
function ringGlow() {
  const c = document.createElement('canvas'); c.width = c.height = 256;
  const g = c.getContext('2d'), gr = g.createRadialGradient(128, 128, 0, 128, 128, 128);
  gr.addColorStop(0, 'rgba(25,195,214,0)'); gr.addColorStop(0.8, 'rgba(25,195,214,0)'); gr.addColorStop(0.86, 'rgba(25,195,214,.38)'); gr.addColorStop(1, 'rgba(25,195,214,0)');
  g.fillStyle = gr; g.fillRect(0, 0, 256, 256);
  const t = new THREE.CanvasTexture(c); t.colorSpace = THREE.SRGBColorSpace; return t;
}
function landPoints(radius) {
  const [rows, cols, step, b64] = LAND.split(',');
  const R = +rows, Cn = +cols, st = +step, bytes = Uint8Array.from(atob(b64), (c) => c.charCodeAt(0));
  const pts = [];
  for (let r = 0; r < R; r++) {
    const lat = 90 - st / 2 - r * st;
    const perRow = Math.max(1, Math.round(Cn * Math.cos(lat * Math.PI / 180)));
    for (let k = 0; k < perRow; k++) {
      const lon = -180 + (k + 0.5) * 360 / perRow;
      const c = Math.min(Cn - 1, Math.floor((lon + 180) / st)), bit = r * Cn + c;
      if ((bytes[bit >> 3] >> (7 - (bit & 7))) & 1) pts.push(ll(lat, lon, radius));
    }
  }
  return pts;
}
function ll(lat, lon, r) {
  const phi = (90 - lat) * Math.PI / 180, th = (lon + 180) * Math.PI / 180;
  return new THREE.Vector3(-r * Math.sin(phi) * Math.cos(th), r * Math.cos(phi), r * Math.sin(phi) * Math.sin(th));
}
function initGlobe(host) {
  const renderer = makeRenderer(host);
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(30, 1, 0.1, 100);
  camera.position.set(0, 0, 11);
  scene.add(new THREE.AmbientLight(0xffffff, 1.2));
  const dl = new THREE.DirectionalLight(0xffffff, 1.6); dl.position.set(-3, 4, 5); scene.add(dl);
  const R = 2.4;
  const world = new THREE.Group(); scene.add(world);
  world.add(new THREE.Mesh(new THREE.SphereGeometry(R * 0.995, 64, 48), new THREE.MeshBasicMaterial({ color: 0xf1f8fb, toneMapped: false })));
  const atm = new THREE.Sprite(new THREE.SpriteMaterial({ map: ringGlow(), transparent: true, opacity: 0.9, depthWrite: false, toneMapped: false }));
  atm.scale.set(R * 2.35, R * 2.35, 1); scene.add(atm); atm.renderOrder = -1;
  /* continentes pontilhados */
  const lp = landPoints(R * 1.002), pos = [];
  lp.forEach((v) => pos.push(v.x, v.y, v.z));
  const lg = new THREE.BufferGeometry(); lg.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
  world.add(new THREE.Points(lg, new THREE.PointsMaterial({ color: COL.petrol, size: 0.05, transparent: true, opacity: 0.9, toneMapped: false })));
  /* Brasil (Brasília) com cúpula de proteção */
  const BR = ll(-15.8, -47.9, R);
  const beacon = new THREE.Mesh(new THREE.SphereGeometry(0.07, 20, 14), new THREE.MeshBasicMaterial({ color: COL.cyan }));
  beacon.position.copy(BR); world.add(beacon);
  const dome = new THREE.Mesh(new THREE.SphereGeometry(0.62, 32, 16, 0, Math.PI * 2, 0, Math.PI / 2), new THREE.MeshBasicMaterial({ color: COL.cyan, transparent: true, opacity: 0.16, side: THREE.DoubleSide, depthWrite: false }));
  dome.position.copy(BR); dome.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), BR.clone().normalize()); world.add(dome);
  const domeWire = new THREE.Mesh(dome.geometry, new THREE.MeshBasicMaterial({ color: COL.cyan, wireframe: true, transparent: true, opacity: 0.25 }));
  domeWire.position.copy(BR); domeWire.quaternion.copy(dome.quaternion); world.add(domeWire);
  /* origens de ataque (ilustrativas) */
  const origins = [[40.7, -74], [34, -118.2], [19.4, -99.1], [25.8, -80.2], [4.7, -74.1], [-12, -77], [-33.4, -70.6], [-34.6, -58.4], [6.5, 3.4], [-26.2, 28], [51.5, -0.1], [40.4, -3.7], [38.7, -9.1], [30, 31.2], [55.7, 37.6], [49.3, -123.1]];
  const arcs = [];
  origins.forEach((o, i) => {
    const A = ll(o[0], o[1], R), mid = A.clone().add(BR).multiplyScalar(0.5);
    const h = 0.6 + A.distanceTo(BR) * 0.32; mid.normalize().multiplyScalar(R + h);
    const end = BR.clone().normalize().multiplyScalar(R + 0.58);
    const curve = new THREE.QuadraticBezierCurve3(A, mid, end);
    const N = 64, cp = curve.getPoints(N), arr = new Float32Array((N + 1) * 3);
    cp.forEach((p, k) => { arr[k * 3] = p.x; arr[k * 3 + 1] = p.y; arr[k * 3 + 2] = p.z; });
    const g = new THREE.BufferGeometry(); g.setAttribute('position', new THREE.BufferAttribute(arr, 3)); g.setDrawRange(0, 0);
    const line = new THREE.Line(g, new THREE.LineBasicMaterial({ color: i % 3 === 0 ? COL.amber : COL.red, transparent: true, opacity: 0.75 }));
    world.add(line);
    const pk = new THREE.Mesh(new THREE.SphereGeometry(0.045, 12, 8), new THREE.MeshBasicMaterial({ color: COL.red }));
    world.add(pk);
    const dot = new THREE.Mesh(new THREE.SphereGeometry(0.035, 10, 8), new THREE.MeshBasicMaterial({ color: COL.amber }));
    dot.position.copy(A); world.add(dot);
    arcs.push({ curve, line, pk, N, t: -(i * 0.37) % 3, sp: 0.28 + Math.random() * 0.2 });
  });
  const ringGeo = new THREE.RingGeometry(0.05, 0.1, 32), rings = [];
  function flash() {
    const m = new THREE.Mesh(ringGeo, new THREE.MeshBasicMaterial({ color: COL.cyan, transparent: true, opacity: 0.9, side: THREE.DoubleSide, depthWrite: false }));
    m.position.copy(BR.clone().normalize().multiplyScalar(R + 0.02)); m.lookAt(BR.clone().multiplyScalar(2)); m.userData.t = 0;
    world.add(m); rings.push(m);
  }
  /* inclinação para o Brasil ficar de frente */
  const baseY = -0.62, baseX = -0.22;
  world.rotation.set(baseX, baseY, 0);
  let mx = 0, my = 0, blocked = 0;
  const counter = host.parentNode && host.parentNode.querySelector('[data-blocked]');
  host.addEventListener('pointermove', (e) => { const r = host.getBoundingClientRect(); mx = ((e.clientX - r.left) / r.width - 0.5); my = ((e.clientY - r.top) / r.height - 0.5); });
  host.addEventListener('pointerleave', () => { mx = 0; my = 0; });
  const resize = () => fit(renderer, camera, host);
  new ResizeObserver(resize).observe(host); resize();
  let first = true;
  const loop = runLoop(host, (dt, time) => {
    const still = CC.motionOff();
    world.rotation.y = lerp(world.rotation.y, baseY + mx * 0.9 + (still ? 0 : Math.sin(time * 0.15) * 0.25), 0.04);
    world.rotation.x = lerp(world.rotation.x, baseX + my * 0.5, 0.04);
    arcs.forEach((a) => {
      if (still) { a.line.geometry.setDrawRange(0, a.N + 1); a.pk.visible = false; return; }
      a.t += dt * a.sp;
      const p = clamp(a.t, 0, 1);
      a.line.geometry.setDrawRange(0, Math.floor(p * a.N) + 1);
      a.line.material.opacity = a.t > 1 ? Math.max(0, 0.75 - (a.t - 1) * 1.5) : 0.75;
      a.pk.visible = a.t > 0 && a.t <= 1;
      if (a.pk.visible) a.pk.position.copy(a.curve.getPoint(p));
      if (a.t > 1 && !a.done) { a.done = true; blocked++; flash(); if (counter) counter.textContent = CC.num ? CC.num(blocked) : blocked; }
      if (a.t > 1.6) { a.t = -Math.random() * 1.5; a.done = false; }
    });
    for (let i = rings.length - 1; i >= 0; i--) {
      const r = rings[i]; r.userData.t += dt;
      r.scale.setScalar(1 + r.userData.t * 7); r.material.opacity = Math.max(0, 0.9 - r.userData.t * 1.4);
      if (r.userData.t > 0.65) { world.remove(r); r.material.dispose(); rings.splice(i, 1); }
    }
    dome.material.opacity = 0.14 + (still ? 0 : Math.sin(time * 3) * 0.04);
    renderer.render(scene, camera);
    if (first) { first = false; host.classList.add('is-3d'); }
  });
  loop.once();
}

/* ------------------------------------------------------------ PAPEL */
function roundRect(g, x, y, w, h, r) { g.beginPath(); g.moveTo(x + r, y); g.arcTo(x + w, y, x + w, y + h, r); g.arcTo(x + w, y + h, x, y + h, r); g.arcTo(x, y + h, x, y, r); g.arcTo(x, y, x + w, y, r); g.closePath(); }
function qr(g, x, y, s) {
  g.fillStyle = '#fff'; g.fillRect(x, y, s, s); g.fillStyle = '#04192f';
  const n = 21, k = s / n;
  for (let i = 0; i < n; i++) for (let j = 0; j < n; j++) if (((i * 7 + j * 13 + i * j) % 5) < 2) g.fillRect(x + i * k, y + j * k, k, k);
  [[0, 0], [n - 7, 0], [0, n - 7]].forEach(([a, b]) => { g.fillRect(x + a * k, y + b * k, 7 * k, 7 * k); g.fillStyle = '#fff'; g.fillRect(x + (a + 1) * k, y + (b + 1) * k, 5 * k, 5 * k); g.fillStyle = '#04192f'; g.fillRect(x + (a + 2) * k, y + (b + 2) * k, 3 * k, 3 * k); });
}
function paperTexture(kind) {
  const W = 768, H = 1086, c = document.createElement('canvas');
  c.width = W; c.height = H;
  const g = c.getContext('2d');
  const t = (k) => CC.t(k);
  if (kind === 'ok') {
    g.fillStyle = '#ffffff'; g.fillRect(0, 0, W, H);
    const gr = g.createLinearGradient(0, 0, W, 0); gr.addColorStop(0, '#002141'); gr.addColorStop(0.55, '#0090ad'); gr.addColorStop(1, '#19c3d6');
    g.fillStyle = gr; g.fillRect(0, 0, W, 22);
    /* selo octogonal */
    g.save(); g.translate(650, 110); g.fillStyle = '#005783'; g.beginPath();
    for (let i = 0; i < 8; i++) { const a = Math.PI / 8 + i * Math.PI / 4; g.lineTo(Math.cos(a) * 46, Math.sin(a) * 46); } g.closePath(); g.fill();
    g.strokeStyle = '#19c3d6'; g.lineWidth = 6; g.beginPath(); g.arc(0, 0, 24, 0.4, 5.6); g.stroke(); g.fillStyle = '#19c3d6'; g.beginPath(); g.arc(0, 0, 10, 0, 7); g.fill(); g.restore();
    g.fillStyle = '#04192f'; g.font = '800 50px Manrope, Arial, sans-serif'; g.fillText(t('p.ok1'), 60, 120);
    g.fillStyle = '#00708a'; g.font = '700 30px Manrope, Arial, sans-serif'; g.fillText(t('p.ok2'), 60, 170);
    /* gráfico de risco caindo */
    g.strokeStyle = '#dde6ef'; g.lineWidth = 2; for (let i = 0; i < 5; i++) { g.beginPath(); g.moveTo(60, 250 + i * 50); g.lineTo(700, 250 + i * 50); g.stroke(); }
    g.strokeStyle = '#0090ad'; g.lineWidth = 8; g.lineJoin = 'round'; g.beginPath();
    [[60, 262], [160, 270], [260, 300], [360, 330], [460, 380], [560, 420], [700, 440]].forEach(([x, y], i) => (i ? g.lineTo(x, y) : g.moveTo(x, y))); g.stroke();
    /* barras de severidade */
    [['#b3261e', 120], ['#d64000', 260], ['#c99a1a', 380], ['#245ea8', 520]].forEach(([cl, w], i) => { g.fillStyle = cl; roundRect(g, 60, 520 + i * 52, w, 30, 15); g.fill(); g.fillStyle = '#eef2f7'; roundRect(g, 70 + w, 520 + i * 52, 620 - w, 30, 15); g.fill(); });
    g.fillStyle = '#e2e8f0'; for (let i = 0; i < 3; i++) g.fillRect(60, 760 + i * 36, 420 - i * 60, 14);
    g.fillStyle = '#e3f5ee'; roundRect(g, 60, 870, 470, 66, 33); g.fill();
    g.fillStyle = '#12805c'; g.font = '800 28px Manrope, Arial, sans-serif'; g.fillText('✓ ' + t('p.ok3'), 86, 914);
    g.fillStyle = '#e0f5f8'; roundRect(g, 60, 950, 470, 66, 33); g.fill();
    g.fillStyle = '#00708a'; g.fillText('✓ ' + t('p.ok4'), 86, 994);
    qr(g, 580, 870, 140);
  } else {
    g.fillStyle = '#fbf8f0'; g.fillRect(0, 0, W, H);
    for (let i = 0; i < 2400; i++) { g.fillStyle = `rgba(120,100,60,${Math.random() * 0.05})`; g.fillRect(Math.random() * W, Math.random() * H, 2, 2); }
    g.fillStyle = '#3b3b3b'; g.font = '800 54px "Times New Roman", serif'; g.fillText(t('p.doc1'), 60, 128);
    g.font = 'italic 32px "Times New Roman", serif'; g.fillStyle = '#6b6b6b'; g.fillText(t('p.doc2') + ' · pág. 1/240', 60, 180);
    g.font = '24px "Courier New", monospace'; g.fillStyle = 'rgba(60,60,60,.75)';
    ['CVE-2021-44228  CRÍTICA  srv-legado', 'CVE-2017-0144   CRÍTICA  fs-01', 'TLS 1.0 habilitado  ALTA  vpn', 'Porta 3389 exposta  ALTA  ???', 'Cabeçalhos ausentes  MÉDIA  portal'].forEach((l, i) => g.fillText(l, 60, 260 + i * 44));
    g.fillStyle = 'rgba(60,60,60,.32)'; for (let i = 0; i < 9; i++) g.fillRect(60, 500 + i * 40, (i % 4 === 3 ? 360 : 640) - Math.random() * 60, 11);
    g.save(); g.translate(400, 900); g.rotate(-0.2);
    g.strokeStyle = 'rgba(198,47,58,.85)'; g.lineWidth = 8; roundRect(g, -10, -70, 330, 110, 12); g.stroke();
    g.fillStyle = 'rgba(198,47,58,.85)'; g.font = '900 42px Arial, sans-serif'; g.fillText(t('p.doc3'), 4, 4); g.restore();
    g.fillStyle = '#8a6d3b'; g.font = 'italic 30px "Comic Sans MS", cursive'; g.fillText(t('p.doc4'), 60, 1010);
    g.strokeStyle = 'rgba(139,94,52,.35)'; g.lineWidth = 10; g.beginPath(); g.arc(620, 200, 64, 0.3, 5.6); g.stroke();
  }
  const tex = new THREE.CanvasTexture(c);
  tex.colorSpace = THREE.SRGBColorSpace; tex.anisotropy = 4;
  return tex;
}
function makeFolds(seed) {
  let s = seed;
  const rnd = () => { s = (s * 16807) % 2147483647; return s / 2147483647; };
  const f = [];
  for (let i = 0; i < 16; i++) {
    const a = rnd() * Math.PI;
    f.push({ nx: Math.cos(a), ny: Math.sin(a), fq: 0.35 + rnd() * 1.6, ph: rnd() * 10, amp: 0.05 + rnd() * 0.14 });
  }
  return f;
}
/* geometria de papel com função de amassar (0 = liso, 1 = bola) */
function makePaper(w, h, seed) {
  const geo = new THREE.PlaneGeometry(w, h, 72, 100);
  const base = geo.attributes.position.array.slice();
  const folds = makeFolds(seed || 7);
  let last = -1;
  function crumple(c) {
    if (Math.abs(c - last) < 0.0005) return;
    last = c;
    const p = geo.attributes.position.array;
    const shrink = 1 - 0.48 * Math.pow(c, 1.15);
    const ball = c < 0.45 ? 0 : Math.pow((c - 0.45) / 0.55, 1.4);
    for (let i = 0; i < p.length; i += 3) {
      const x = base[i], y = base[i + 1];
      let z = 0;
      for (let k = 0; k < folds.length; k++) {
        const F = folds[k], u = F.fq * (F.nx * x + F.ny * y) + F.ph;
        const fr = u - Math.floor(u);
        z += F.amp * (Math.abs(fr - 0.5) * 2 - 0.5);
      }
      z *= c * 2.2;
      let X = x * shrink, Y = y * shrink;
      const r2 = X * X + Y * Y;
      z -= ball * r2 * 0.55;
      X *= 1 - ball * 0.35; Y *= 1 - ball * 0.35;
      p[i] = X; p[i + 1] = Y; p[i + 2] = z;
    }
    geo.attributes.position.needsUpdate = true;
    geo.computeVertexNormals();
  }
  crumple(0);
  return { geo, crumple };
}
function paperMaterial(tex) {
  return new THREE.MeshStandardMaterial({ map: tex, roughness: 0.92, metalness: 0, side: THREE.DoubleSide, flatShading: true });
}
function shadowBlob(size, opacity) {
  const c = document.createElement('canvas'); c.width = c.height = 128;
  const g = c.getContext('2d'), gr = g.createRadialGradient(64, 64, 4, 64, 64, 62);
  gr.addColorStop(0, `rgba(0,33,65,${opacity})`); gr.addColorStop(1, 'rgba(0,33,65,0)');
  g.fillStyle = gr; g.fillRect(0, 0, 128, 128);
  return new THREE.Mesh(new THREE.PlaneGeometry(size, size), new THREE.MeshBasicMaterial({ map: new THREE.CanvasTexture(c), transparent: true, depthWrite: false }));
}
function paperLights(scene) {
  scene.add(new THREE.HemisphereLight(0xffffff, 0xdfe9f2, 1.4));
  const k = new THREE.DirectionalLight(0xffffff, 2.4); k.position.set(-3, 4, 5); scene.add(k);
  const f = new THREE.DirectionalLight(0xbfefff, 0.8); f.position.set(4, -2, 3); scene.add(f);
}

/* ---------- papel controlado pela mão ---------- */
function initPaperHand(host) {
  const card = host.closest('.paper-card') || host.parentNode;
  const ui = {
    status: host.querySelector('.ps-status span'),
    cam: card.querySelector('[data-cam]'), range: card.querySelector('[data-crumple]'),
    vbox: host.querySelector('.ps-video')
  };
  const renderer = makeRenderer(host);
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(34, 1, 0.1, 50);
  camera.position.set(0, 0, 7.4);
  paperLights(scene);
  let texOk = paperTexture('ok'), texOld = paperTexture('old');
  const paper = makePaper(2.3, 3.25, 11);
  const mesh = new THREE.Mesh(paper.geo, paperMaterial(texOld));
  scene.add(mesh);
  const sh = shadowBlob(4.2, 0.22); sh.position.set(0.2, -0.3, -0.8); scene.add(sh);
  let target = 0.85, cur = 0.85, rotT = { x: 0, y: 0 }, done = false, handOn = false, showing = 'old';
  const setStatus = (k) => { if (ui.status) ui.status.textContent = CC.t(k); };
  setStatus('p.drag');
  let drag = null;
  host.addEventListener('pointerdown', (e) => { drag = { y: e.clientY, t: target }; host.classList.add('is-grabbing'); host.setPointerCapture(e.pointerId); });
  host.addEventListener('pointermove', (e) => {
    if (drag) { target = clamp(drag.t + (e.clientY - drag.y) / 220, 0, 1); syncRange(); }
    if (!handOn) { const r = host.getBoundingClientRect(); rotT.y = ((e.clientX - r.left) / r.width - 0.5) * 0.7; rotT.x = ((e.clientY - r.top) / r.height - 0.5) * 0.5; }
  });
  const end = () => { drag = null; host.classList.remove('is-grabbing'); };
  host.addEventListener('pointerup', end); host.addEventListener('pointercancel', end);
  host.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowUp') { target = clamp(target - 0.1, 0, 1); syncRange(); e.preventDefault(); }
    if (e.key === 'ArrowDown') { target = clamp(target + 0.1, 0, 1); syncRange(); e.preventDefault(); }
  });
  function syncRange() { if (ui.range) { ui.range.value = Math.round((1 - target) * 100); ui.range.style.setProperty('--p', ((1 - target) * 100) + '%'); } loop.kick(); }
  if (ui.range) ui.range.addEventListener('input', () => { target = 1 - ui.range.value / 100; ui.range.style.setProperty('--p', ui.range.value + '%'); loop.kick(); });
  let stream = null, landmarker = null, video = null, vcanvas = null, lastVT = -1;
  async function camOn() {
    try {
      setStatus('p.loading');
      if (!landmarker) {
        const vision = await import(`${MP_BASE}/vision_bundle.mjs`);
        const files = await vision.FilesetResolver.forVisionTasks(`${MP_BASE}/wasm`);
        landmarker = await vision.HandLandmarker.createFromOptions(files, { baseOptions: { modelAssetPath: HAND_MODEL, delegate: 'GPU' }, runningMode: 'VIDEO', numHands: 1 });
      }
      stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480, facingMode: 'user' }, audio: false });
      if (!video) {
        video = document.createElement('video'); video.muted = true; video.playsInline = true; video.setAttribute('aria-hidden', 'true');
        vcanvas = document.createElement('canvas'); vcanvas.width = 320; vcanvas.height = 240;
        ui.vbox.appendChild(video); ui.vbox.appendChild(vcanvas);
      }
      video.srcObject = stream; await video.play();
      ui.vbox.classList.add('is-on'); handOn = true;
      setStatus('p.show');
      if (ui.cam) { ui.cam.setAttribute('aria-pressed', 'true'); const sp = ui.cam.querySelector('span'); if (sp) sp.textContent = CC.t('p.camoff'); }
      loop.kick();
    } catch (err) {
      camOff(); setStatus('p.nocam');
    }
  }
  function camOff() {
    if (stream) stream.getTracks().forEach((tr) => tr.stop());
    stream = null; handOn = false;
    if (ui.vbox) ui.vbox.classList.remove('is-on');
    if (ui.cam) { ui.cam.setAttribute('aria-pressed', 'false'); const sp = ui.cam.querySelector('span'); if (sp) sp.textContent = CC.t('p.camon'); }
  }
  if (ui.cam) ui.cam.addEventListener('click', () => (stream ? (camOff(), setStatus('p.off')) : camOn()));
  function trackHand() {
    if (!stream || !landmarker || !video || video.readyState < 2 || video.currentTime === lastVT) return;
    lastVT = video.currentTime;
    const res = landmarker.detectForVideo(video, performance.now());
    const g = vcanvas.getContext('2d');
    g.drawImage(video, 0, 0, vcanvas.width, vcanvas.height);
    const lm = res && res.landmarks && res.landmarks[0];
    if (!lm) { setStatus('p.show'); return; }
    const d = (a, b) => Math.hypot(lm[a].x - lm[b].x, lm[a].y - lm[b].y);
    const palm = d(0, 9) || 0.001;
    const ratio = [4, 8, 12, 16, 20].reduce((s, i) => s + d(i, 0), 0) / 5 / palm;
    const open = clamp((ratio - 1.12) / (1.85 - 1.12), 0, 1);
    target = 1 - open; syncRange();
    rotT.y = (0.5 - lm[9].x) * 1.1; rotT.x = (lm[9].y - 0.5) * 0.7;
    g.fillStyle = '#19c3d6';
    lm.forEach((p) => { g.beginPath(); g.arc(p.x * vcanvas.width, p.y * vcanvas.height, 3, 0, 7); g.fill(); });
    setStatus('p.hand');
  }
  const resize = () => fit(renderer, camera, host);
  new ResizeObserver(resize).observe(host); resize();
  let first = true;
  const loop = runLoop(host, (dt, time) => {
    trackHand();
    cur = CC.motionOff() ? target : lerp(cur, target, 0.12);
    paper.crumple(cur);
    /* amassado = relatório antigo; aberto = laudo do CoreCyber (troca no meio do caminho) */
    const want = cur < 0.42 ? 'ok' : 'old';
    if (want !== showing) { showing = want; mesh.material.map = want === 'ok' ? texOk : texOld; mesh.material.needsUpdate = true; }
    host.style.setProperty('--open', (1 - cur).toFixed(3));
    mesh.rotation.y = lerp(mesh.rotation.y, rotT.y + (CC.motionOff() ? 0 : Math.sin(time * 0.6) * 0.06 * cur), 0.08);
    mesh.rotation.x = lerp(mesh.rotation.x, rotT.x, 0.08);
    mesh.rotation.z = cur * 0.5;
    if (cur < 0.03 && !done) { done = true; setStatus('p.done'); }
    if (cur > 0.2) done = false;
    renderer.render(scene, camera);
    if (first) { first = false; host.classList.add('is-3d'); }
    if (handOn || Math.abs(cur - target) > 0.002) loop.kick();
  });
  document.addEventListener('cc:lang', () => { texOk.dispose(); texOld.dispose(); texOk = paperTexture('ok'); texOld = paperTexture('old'); mesh.material.map = showing === 'ok' ? texOk : texOld; mesh.material.needsUpdate = true; setStatus(handOn ? 'p.show' : 'p.drag'); loop.once(); });
  loop.once();
}

/* ---------- papéis com paralaxe real ---------- */
function wrap(g, text, x, y, mw, lh) {
  const words = String(text).split(' '); let line = '';
  words.forEach((w) => { const t = line + w + ' '; if (g.measureText(t).width > mw && line) { g.fillText(line, x, y); line = w + ' '; y += lh; } else line = t; });
  g.fillText(line, x, y);
}
function noteTexture() {
  const c = document.createElement('canvas'); c.width = c.height = 512;
  const g = c.getContext('2d');
  g.fillStyle = '#ffe680'; g.fillRect(0, 0, 512, 512);
  g.fillStyle = 'rgba(0,0,0,.05)'; g.fillRect(0, 0, 512, 60);
  g.fillStyle = '#5a4a12'; g.font = '700 46px "Comic Sans MS", cursive';
  wrap(g, CC.t('p.note'), 40, 150, 440, 60);
  return Object.assign(new THREE.CanvasTexture(c), { colorSpace: THREE.SRGBColorSpace });
}
function sheetTexture() {
  const c = document.createElement('canvas'); c.width = 768; c.height = 1086;
  const g = c.getContext('2d');
  g.fillStyle = '#f4f6ef'; g.fillRect(0, 0, 768, 1086);
  g.fillStyle = '#2f5d34'; g.fillRect(0, 0, 768, 70);
  g.fillStyle = '#fff'; g.font = '700 30px Arial, sans-serif'; g.fillText(CC.t('p.oldh'), 30, 46);
  g.strokeStyle = 'rgba(60,80,60,.35)'; g.lineWidth = 2;
  for (let y = 110; y < 1040; y += 38) { g.beginPath(); g.moveTo(20, y); g.lineTo(748, y); g.stroke(); }
  [20, 200, 360, 520, 748].forEach((x) => { g.beginPath(); g.moveTo(x, 72); g.lineTo(x, 1040); g.stroke(); });
  const sev = ['#f4c7c3', '#fce8b2', '#f4c7c3', '#fff', '#b7e1cd', '#fce8b2', '#f4c7c3', '#fff'];
  for (let r = 0; r < 24; r++) {
    g.fillStyle = sev[r % sev.length]; g.fillRect(362, 112 + r * 38, 156, 34);
    g.fillStyle = 'rgba(40,40,40,.55)'; g.fillRect(30, 124 + r * 38, 120 - (r * 13) % 60, 10); g.fillRect(210, 124 + r * 38, 100 - (r * 7) % 50, 10); g.fillRect(530, 124 + r * 38, 160 - (r * 11) % 90, 10);
  }
  g.fillStyle = '#5c4a2a'; g.font = 'italic 28px "Courier New", monospace'; g.fillText(CC.t('p.old'), 30, 1074);
  return Object.assign(new THREE.CanvasTexture(c), { colorSpace: THREE.SRGBColorSpace });
}
function initPaperParallax(host) {
  const card = host.closest('.paper-card') || host.parentNode;
  const renderer = makeRenderer(host);
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(30, 1, 0.1, 60);
  camera.position.set(0, 0, 9);
  paperLights(scene);
  const back = new THREE.Mesh(new THREE.PlaneGeometry(2.5, 3.5), new THREE.MeshStandardMaterial({ map: sheetTexture(), roughness: 1 }));
  back.position.set(-1.25, 0.25, -2.2); back.rotation.z = 0.12; scene.add(back);
  const bsh = shadowBlob(4.4, 0.16); bsh.position.set(-1.0, 0.0, -2.35); scene.add(bsh);
  let midTex = paperTexture('old'), okTex = paperTexture('ok');
  const mid = makePaper(2.3, 3.25, 23);
  const midMesh = new THREE.Mesh(mid.geo, paperMaterial(midTex));
  midMesh.position.set(0.45, -0.1, 0); scene.add(midMesh);
  const msh = shadowBlob(4, 0.24); msh.position.set(0.7, -0.4, -0.7); scene.add(msh);
  const note = new THREE.Mesh(new THREE.PlaneGeometry(1.25, 1.25, 10, 10), new THREE.MeshStandardMaterial({ map: noteTexture(), roughness: 0.85, side: THREE.DoubleSide }));
  const np = note.geometry.attributes.position;
  for (let i = 0; i < np.count; i++) { const y = np.getY(i); np.setZ(i, y < -0.3 ? (y + 0.3) * (y + 0.3) * 0.6 : 0); }
  note.geometry.computeVertexNormals();
  note.position.set(1.75, 1.15, 1.6); note.rotation.z = -0.18; scene.add(note);
  const pin = new THREE.Group();
  pin.add(new THREE.Mesh(new THREE.SphereGeometry(0.13, 24, 16), new THREE.MeshPhysicalMaterial({ color: 0xe2563b, roughness: 0.25, clearcoat: 1 })));
  const needle = new THREE.Mesh(new THREE.ConeGeometry(0.03, 0.4, 12), new THREE.MeshStandardMaterial({ color: 0xb0b8c8, metalness: 0.8, roughness: 0.3 }));
  needle.rotation.x = -Math.PI / 2; needle.position.z = -0.22; pin.add(needle);
  pin.position.set(1.75, 1.62, 1.9); scene.add(pin);
  /* selo CoreCyber flutuando à frente (aparece ao consolidar) */
  const seal = new THREE.Group();
  const octo = new THREE.Shape(); for (let i = 0; i < 8; i++) { const a = Math.PI / 8 + i * Math.PI / 4; i ? octo.lineTo(Math.cos(a) * 0.42, Math.sin(a) * 0.42) : octo.moveTo(Math.cos(a) * 0.42, Math.sin(a) * 0.42); }
  seal.add(new THREE.Mesh(new THREE.ExtrudeGeometry(octo, { depth: 0.08, bevelEnabled: true, bevelThickness: 0.02, bevelSize: 0.02, bevelSegments: 2 }), new THREE.MeshPhysicalMaterial({ color: COL.petrol, clearcoat: 1, roughness: 0.25 })));
  const sr = new THREE.Mesh(new THREE.TorusGeometry(0.22, 0.035, 10, 40, Math.PI * 1.6), new THREE.MeshStandardMaterial({ color: COL.cyan, emissive: COL.cyan, emissiveIntensity: 0.4 })); sr.position.z = 0.11; seal.add(sr);
  const sc = new THREE.Mesh(new THREE.SphereGeometry(0.09, 16, 12), new THREE.MeshStandardMaterial({ color: COL.cyan, emissive: COL.cyan, emissiveIntensity: 0.5 })); sc.position.z = 0.12; seal.add(sc);
  seal.position.set(-1.6, -1.25, 2.2); seal.scale.setScalar(0.001); scene.add(seal);
  const pg = new THREE.BufferGeometry(), pts = [];
  for (let i = 0; i < 160; i++) pts.push((Math.random() - 0.5) * 9, (Math.random() - 0.5) * 6, -4 + Math.random() * 7);
  pg.setAttribute('position', new THREE.Float32BufferAttribute(pts, 3));
  scene.add(new THREE.Points(pg, new THREE.PointsMaterial({ color: COL.blue, size: 0.04, transparent: true, opacity: 0.55 })));

  let mx = 0, my = 0, crT = 0.6, crC = 0.6, flat = false, sealS = 0.001;
  host.addEventListener('pointermove', (e) => { const r = host.getBoundingClientRect(); mx = ((e.clientX - r.left) / r.width - 0.5) * 2; my = ((e.clientY - r.top) / r.height - 0.5) * 2; loop.kick(); });
  host.addEventListener('pointerleave', () => { mx = 0; my = 0; loop.kick(); });
  const gyroBtn = card.querySelector('[data-gyro]');
  function onOri(e) { if (e.gamma == null) return; mx = clamp(e.gamma / 30, -1, 1); my = clamp((e.beta - 40) / 30, -1, 1); loop.kick(); }
  if (gyroBtn) {
    if (!('DeviceOrientationEvent' in window) || !('ontouchstart' in window)) gyroBtn.hidden = true;
    gyroBtn.addEventListener('click', async () => {
      try { if (typeof DeviceOrientationEvent.requestPermission === 'function') { const p = await DeviceOrientationEvent.requestPermission(); if (p !== 'granted') return; } } catch (err) { return; }
      window.addEventListener('deviceorientation', onOri, { passive: true }); gyroBtn.setAttribute('aria-pressed', 'true');
    });
  }
  const flatBtn = card.querySelector('[data-flatten]');
  if (flatBtn) flatBtn.addEventListener('click', () => {
    flat = !flat; crT = flat ? 0 : 0.6;
    midMesh.material.map = flat ? okTex : midTex; midMesh.material.needsUpdate = true;
    flatBtn.setAttribute('aria-pressed', flat ? 'true' : 'false');
    loop.kick();
  });
  const resize = () => fit(renderer, camera, host);
  new ResizeObserver(resize).observe(host); resize();
  let first = true;
  const cam = { x: 0, y: 0 };
  const loop = runLoop(host, (dt, time) => {
    const still = CC.motionOff();
    cam.x = lerp(cam.x, mx * 2.1, still ? 1 : 0.07); cam.y = lerp(cam.y, -my * 1.4, still ? 1 : 0.07);
    camera.position.set(cam.x, cam.y, 9); camera.lookAt(0, 0, 0);
    crC = still ? crT : lerp(crC, crT, 0.08);
    mid.crumple(crC);
    midMesh.rotation.z = crC * 0.25 - 0.05;
    note.rotation.y = still ? 0 : Math.sin(time * 1.3) * 0.08;
    sealS = lerp(sealS, flat ? 1 : 0.001, still ? 1 : 0.1); seal.scale.setScalar(sealS);
    seal.rotation.y = still ? 0 : Math.sin(time * 1.2) * 0.4;
    renderer.render(scene, camera);
    if (first) { first = false; host.classList.add('is-3d'); }
    if (Math.abs(cam.x - mx * 2.1) > 0.002 || Math.abs(cam.y + my * 1.4) > 0.002 || Math.abs(crC - crT) > 0.002 || Math.abs(sealS - (flat ? 1 : 0.001)) > 0.002 || flat) loop.kick();
  });
  document.addEventListener('cc:lang', () => {
    midTex.dispose(); okTex.dispose(); midTex = paperTexture('old'); okTex = paperTexture('ok');
    midMesh.material.map = flat ? okTex : midTex; midMesh.material.needsUpdate = true;
    note.material.map.dispose(); note.material.map = noteTexture(); note.material.needsUpdate = true;
    back.material.map.dispose(); back.material.map = sheetTexture(); back.material.needsUpdate = true;
    loop.once();
  });
  loop.once();
}

/* ------------------------------------------------------------ entrada */
export function init(api) {
  CC = api;
  const test = document.createElement('canvas');
  if (!(test.getContext('webgl2') || test.getContext('webgl'))) return;
  const scenes = { hero: initHero, globe: initGlobe, 'paper-hand': initPaperHand, 'paper-parallax': initPaperParallax };
  document.querySelectorAll('[data-3d]').forEach((el) => {
    const fn = scenes[el.getAttribute('data-3d')];
    if (!fn || el.__cc) return;
    el.__cc = true;
    try { fn(el); } catch (err) { console.warn('[CoreCyber] cena 3D falhou:', err); }
  });
}
