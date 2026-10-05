/* =====================================================================
   Alicerce360 · cenas 3D (módulo ES carregado sob demanda pelo núcleo)
   - hero: alicerce + anéis do logo, reage ao mouse e à rolagem
   - emblem: anéis do logo + objeto temático no cabeçalho das páginas internas
   - paper-hand: papel amassado controlado pela mão (MediaPipe, opcional)
   - paper-parallax: papéis em profundidade real (câmera em perspectiva)
   ===================================================================== */
import * as THREE from 'https://cdnjs.cloudflare.com/ajax/libs/three.js/0.170.0/three.module.min.js';

const MP_VER = '0.10.14';
const MP_BASE = `https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@${MP_VER}`;
const HAND_MODEL = 'https://storage.googleapis.com/mediapipe-models/hand_landmarker/hand_landmarker/float16/1/hand_landmarker.task';
let A3 = null;

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
/* laço de animação que pausa fora da tela, com aba oculta ou movimento reduzido */
function runLoop(host, tick) {
  let visible = false, raf = 0, last = performance.now();
  const frame = (now) => {
    const dt = Math.min(0.05, (now - last) / 1000); last = now;
    tick(dt, now / 1000);
    raf = (visible && !document.hidden && !A3.motionOff()) ? requestAnimationFrame(frame) : 0;
  };
  const kick = () => { if (!raf) { last = performance.now(); raf = requestAnimationFrame(frame); } };
  new IntersectionObserver((e) => { visible = e[0].isIntersecting; if (visible) kick(); }, { rootMargin: '100px' }).observe(host);
  document.addEventListener('visibilitychange', kick);
  document.addEventListener('a3:a11y', () => { tick(0, performance.now() / 1000); kick(); });
  return { kick, once: () => tick(0, performance.now() / 1000) };
}
const lerp = (a, b, t) => a + (b - a) * t;
const clamp = (v, a, b) => Math.max(a, Math.min(b, v));

/* ------------------------------------------------------------ HERO */
function crescentShape(a0, sweep, R, W) {
  const s = new THREE.Shape(), N = 48, outer = [], inner = [];
  for (let i = 0; i <= N; i++) {
    const t = i / N, th = a0 + t * sweep, w = W * Math.pow(Math.sin(Math.PI * t), 0.85) + 0.02;
    const r = R + t * 0.18;
    outer.push([Math.cos(th) * (r + w / 2), Math.sin(th) * (r + w / 2)]);
    inner.push([Math.cos(th) * (r - w / 2), Math.sin(th) * (r - w / 2)]);
  }
  s.moveTo(outer[0][0], outer[0][1]);
  outer.forEach(p => s.lineTo(p[0], p[1]));
  inner.reverse().forEach(p => s.lineTo(p[0], p[1]));
  s.closePath();
  return s;
}
function houseShape() {
  const s = new THREE.Shape();
  s.moveTo(-0.46, -0.62); s.lineTo(-0.46, 0.18); s.lineTo(0, 0.68); s.lineTo(0.46, 0.18); s.lineTo(0.46, -0.62); s.closePath();
  const door = new THREE.Path();
  door.moveTo(-0.15, -0.62); door.lineTo(-0.15, 0.02); door.lineTo(0, 0.17); door.lineTo(0.15, 0.02); door.lineTo(0.15, -0.62); door.closePath();
  s.holes.push(door);
  return s;
}
function initHero(host) {
  const renderer = makeRenderer(host);
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(32, 1, 0.1, 100);
  camera.position.set(0, 0.35, 9.2);
  scene.add(new THREE.HemisphereLight(0xffffff, 0xcfe3ff, 1.25));
  const key = new THREE.DirectionalLight(0xffffff, 2.1); key.position.set(-4, 6, 6); scene.add(key);
  const rim = new THREE.PointLight(0x2fddbe, 18, 20); rim.position.set(3.5, -2, 3); scene.add(rim);
  const warm = new THREE.PointLight(0xff9a1f, 10, 18); warm.position.set(3, 3, 2); scene.add(warm);

  const root = new THREE.Group(); scene.add(root);
  /* anéis do logo (meias-luas em 3D) */
  const ring = new THREE.Group(); root.add(ring);
  const colors = [0x2fddbe, 0xff9a1f, 0x6b3be6, 0x1f8bf0];
  colors.forEach((c, i) => {
    const g = new THREE.ExtrudeGeometry(crescentShape(i * Math.PI / 2 + 0.3, Math.PI * 0.92, 2.05, 0.78), { depth: 0.22, bevelEnabled: true, bevelThickness: 0.06, bevelSize: 0.05, bevelSegments: 4, curveSegments: 48 });
    g.translate(0, 0, -0.11 + i * 0.03);
    const m = new THREE.MeshPhysicalMaterial({ color: c, roughness: 0.32, metalness: 0.05, clearcoat: 0.8, clearcoatRoughness: 0.25, emissive: c, emissiveIntensity: 0.06 });
    ring.add(new THREE.Mesh(g, m));
  });
  const halo = new THREE.Mesh(new THREE.TorusGeometry(2.62, 0.05, 16, 160), new THREE.MeshStandardMaterial({ color: 0x0e1f4d, roughness: 0.4 }));
  ring.add(halo);
  /* alicerce: bloco + casa + selo de verificação */
  const core = new THREE.Group(); root.add(core);
  const side = new THREE.MeshStandardMaterial({ color: 0x2457d6, roughness: 0.38, metalness: 0.1 });
  const side2 = new THREE.MeshStandardMaterial({ color: 0x3b2cb8, roughness: 0.38, metalness: 0.1 });
  const top = new THREE.MeshStandardMaterial({ color: 0x6fe6ea, roughness: 0.25, metalness: 0.05, emissive: 0x2fddbe, emissiveIntensity: 0.12 });
  const base = new THREE.Mesh(new THREE.BoxGeometry(1.7, 0.55, 1.25), [side2, side, top, side, side, side2]);
  base.position.y = -0.62; core.add(base);
  const house = new THREE.Mesh(new THREE.ExtrudeGeometry(houseShape(), { depth: 0.42, bevelEnabled: true, bevelThickness: 0.04, bevelSize: 0.035, bevelSegments: 3 }),
    new THREE.MeshPhysicalMaterial({ color: 0x2f7cf0, roughness: 0.25, clearcoat: 0.6 }));
  house.position.set(0, 0.24, -0.25); core.add(house);
  const badge = new THREE.Group();
  badge.add(new THREE.Mesh(new THREE.CylinderGeometry(0.34, 0.34, 0.1, 48).rotateX(Math.PI / 2), new THREE.MeshPhysicalMaterial({ color: 0x18bec0, roughness: 0.25, clearcoat: 1 })));
  const ck = new THREE.CatmullRomCurve3([new THREE.Vector3(-0.15, 0.01, 0.07), new THREE.Vector3(-0.04, -0.1, 0.07), new THREE.Vector3(0.17, 0.13, 0.07)], false, 'catmullrom', 0);
  badge.add(new THREE.Mesh(new THREE.TubeGeometry(ck, 24, 0.035, 8, false), new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.3 })));
  badge.add(new THREE.Mesh(new THREE.TorusGeometry(0.36, 0.035, 12, 48), new THREE.MeshStandardMaterial({ color: 0x0e1f4d })));
  badge.position.set(0.72, 0.42, 0.3); core.add(badge);
  /* blocos flutuantes (o alicerce sendo montado) */
  const cubes = [];
  const cubeGeo = new THREE.BoxGeometry(0.22, 0.22, 0.22);
  for (let i = 0; i < 14; i++) {
    const m = new THREE.Mesh(cubeGeo, new THREE.MeshStandardMaterial({ color: colors[i % 4], roughness: 0.35 }));
    const a = Math.random() * Math.PI * 2, r = 2.9 + Math.random() * 0.9;
    m.userData = { a, r, s: 0.15 + Math.random() * 0.25, y: (Math.random() - 0.5) * 2.6, ph: Math.random() * 6 };
    m.scale.setScalar(0.5 + Math.random() * 0.7);
    root.add(m); cubes.push(m);
  }
  root.scale.setScalar(0.92);

  let tx = 0, ty = 0, scrollK = 0;
  window.addEventListener('pointermove', (e) => { tx = (e.clientX / innerWidth - 0.5); ty = (e.clientY / innerHeight - 0.5); }, { passive: true });
  window.addEventListener('scroll', () => { scrollK = Math.min(1, scrollY / 900); }, { passive: true });
  const resize = () => fit(renderer, camera, host);
  new ResizeObserver(resize).observe(host); resize();
  let first = true;
  const loop = runLoop(host, (dt, time) => {
    const still = A3.motionOff();
    ring.rotation.z = still ? 0.2 : ring.rotation.z - dt * 0.18;
    root.rotation.y = lerp(root.rotation.y, tx * 0.6 + (still ? 0 : Math.sin(time * 0.4) * 0.08), 0.06);
    root.rotation.x = lerp(root.rotation.x, ty * 0.35 + scrollK * 0.3, 0.06);
    core.position.y = still ? 0 : Math.sin(time * 1.2) * 0.06;
    badge.rotation.y = still ? 0 : Math.sin(time * 1.6) * 0.5;
    cubes.forEach((c) => {
      const u = c.userData, a = u.a + (still ? 0 : time * u.s);
      c.position.set(Math.cos(a) * u.r, u.y + (still ? 0 : Math.sin(time + u.ph) * 0.15), Math.sin(a) * u.r * 0.45 - 0.8);
      c.rotation.set(time * u.s * 2, time * u.s * 3, 0);
    });
    renderer.render(scene, camera);
    if (first) { first = false; host.classList.add('is-3d'); }
  });
  loop.once();
}

/* ------------------------------------------------------------ EMBLEMA (cabeçalho das páginas internas)
   Anéis do logo + um objeto 3D temático por página (data-emblem), com a mesma
   reação a mouse e rolagem do hero da página inicial. */
const PAL = { teal: 0x2fddbe, teal2: 0x18bec0, blue: 0x2f7cf0, blue2: 0x1f8bf0, purple: 0x6b3be6, orange: 0xff9a1f, navy: 0x0e1f4d, white: 0xf7fafc, silver: 0xc9d6e6, green: 0x22b573, gold: 0xf2b544 };
const pm = (color, o = {}) => new THREE.MeshPhysicalMaterial({ color, roughness: 0.32, metalness: 0.05, clearcoat: 0.6, clearcoatRoughness: 0.3, ...o });
const sm = (color, o = {}) => new THREE.MeshStandardMaterial({ color, roughness: 0.4, ...o });
function rrShape(w, h, r) {
  const s = new THREE.Shape(), x = -w / 2, y = -h / 2;
  s.moveTo(x + r, y); s.lineTo(x + w - r, y); s.quadraticCurveTo(x + w, y, x + w, y + r);
  s.lineTo(x + w, y + h - r); s.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
  s.lineTo(x + r, y + h); s.quadraticCurveTo(x, y + h, x, y + h - r);
  s.lineTo(x, y + r); s.quadraticCurveTo(x, y, x + r, y);
  return s;
}
function extrude(shape, depth, mat, bevel = 0.03) {
  const g = new THREE.ExtrudeGeometry(shape, { depth, bevelEnabled: bevel > 0, bevelThickness: bevel, bevelSize: bevel, bevelSegments: 3, curveSegments: 14 });
  g.translate(0, 0, -depth / 2);
  return new THREE.Mesh(g, mat);
}
const slab = (w, h, d, r, mat) => extrude(rrShape(w, h, r), d, mat);
function bar(w, h, color, x, y, z) { const m = slab(w, h, 0.02, Math.min(w, h) / 2.2, sm(color)); m.position.set(x + w / 2, y, z); return m; }
function checkBadge(r = 0.34, color = PAL.teal2) {
  const b = new THREE.Group(), k = r / 0.34;
  b.add(new THREE.Mesh(new THREE.CylinderGeometry(r, r, 0.1 * k, 48).rotateX(Math.PI / 2), pm(color, { clearcoat: 1 })));
  const ck = new THREE.CatmullRomCurve3([new THREE.Vector3(-0.15 * k, 0.01 * k, 0.07 * k), new THREE.Vector3(-0.04 * k, -0.1 * k, 0.07 * k), new THREE.Vector3(0.17 * k, 0.13 * k, 0.07 * k)], false, 'catmullrom', 0);
  b.add(new THREE.Mesh(new THREE.TubeGeometry(ck, 24, 0.035 * k, 8, false), sm(0xffffff, { roughness: 0.3 })));
  b.add(new THREE.Mesh(new THREE.TorusGeometry(r * 1.06, 0.035 * k, 12, 48), sm(PAL.navy)));
  return b;
}
function canvasTex(w, h, draw) {
  const c = document.createElement('canvas'); c.width = w; c.height = h;
  draw(c.getContext('2d'), w, h);
  const t = new THREE.CanvasTexture(c); t.colorSpace = THREE.SRGBColorSpace; t.anisotropy = 4;
  return t;
}
const bob = (t, still, sp = 1.2, a = 0.06, ph = 0) => (still ? 0 : Math.sin(t * sp + ph) * a);
const ease = (x) => x * x * (3 - 2 * x);

const EMBLEMS = {
  /* alicerce: bloco + casa + selo (Sobre) */
  house() {
    const g = new THREE.Group();
    const side = sm(0x2457d6, { roughness: 0.38, metalness: 0.1 }), side2 = sm(0x3b2cb8, { roughness: 0.38, metalness: 0.1 });
    const top = sm(0x6fe6ea, { roughness: 0.25, emissive: PAL.teal, emissiveIntensity: 0.12 });
    const base = new THREE.Mesh(new THREE.BoxGeometry(1.7, 0.55, 1.25), [side2, side, top, side, side, side2]);
    base.position.y = -0.62; g.add(base);
    const house = extrude(houseShape(), 0.42, pm(PAL.blue, { roughness: 0.25 }), 0.04);
    house.position.set(0, 0.24, -0.04); g.add(house);
    const badge = checkBadge(); badge.position.set(0.72, 0.42, 0.3); g.add(badge);
    g.rotation.set(0.12, -0.35, 0);
    g.userData.tick = (t, still) => { badge.rotation.y = still ? 0 : Math.sin(t * 1.6) * 0.5; house.position.y = 0.24 + bob(t, still, 1.4, 0.05); };
    return g;
  },
  /* camadas da plataforma (Intranet) */
  layers() {
    const g = new THREE.Group(), s = [];
    [PAL.purple, PAL.blue, PAL.teal].forEach((c, i) => {
      const m = slab(1.8, 1.25, 0.14, 0.2, pm(c, { emissive: c, emissiveIntensity: 0.06 }));
      m.rotation.x = -Math.PI / 2; g.add(m); s.push(m);
    });
    const top = s[2];
    const c1 = slab(0.55, 0.36, 0.06, 0.07, pm(PAL.white)); c1.position.set(-0.4, 0.15, 0.12); top.add(c1);
    const c2 = slab(0.55, 0.36, 0.06, 0.07, pm(PAL.white)); c2.position.set(0.38, -0.2, 0.12); top.add(c2);
    const c3 = new THREE.Mesh(new THREE.CylinderGeometry(0.16, 0.16, 0.1, 32).rotateX(Math.PI / 2), pm(PAL.orange)); c3.position.set(0.45, 0.28, 0.13); top.add(c3);
    g.rotation.set(0.62, -0.62, 0);
    g.userData.tick = (t, still) => s.forEach((m, i) => { m.position.y = (i - 1) * (0.5 + (still ? 0 : Math.sin(t * 1.3) * 0.08)); });
    return g;
  },
  /* tela com protótipo (Tour de telas) */
  screen() {
    const g = new THREE.Group();
    g.add(slab(2.3, 1.5, 0.1, 0.12, pm(PAL.navy)));
    const tex = canvasTex(640, 400, (c, w, h) => {
      c.fillStyle = '#f4f7fb'; c.fillRect(0, 0, w, h);
      c.fillStyle = '#0e1f4d'; c.fillRect(0, 0, w, 46);
      ['#2fddbe', '#ff9a1f', '#6b3be6'].forEach((col, i) => { c.fillStyle = col; c.beginPath(); c.arc(28 + i * 22, 23, 7, 0, 7); c.fill(); });
      c.fillStyle = '#e6edf5'; c.fillRect(0, 46, 120, h - 46);
      for (let i = 0; i < 6; i++) { c.fillStyle = i === 1 ? '#c9f2ec' : '#d5dfeb'; roundRect(c, 16, 70 + i * 40, 88, 20, 8); c.fill(); }
      const card = (x, y, cw, ch, col) => { c.fillStyle = '#fff'; roundRect(c, x, y, cw, ch, 14); c.fill(); c.fillStyle = col; roundRect(c, x + 16, y + 18, cw * 0.5, 14, 7); c.fill(); c.fillStyle = '#e2e8f0'; roundRect(c, x + 16, y + 44, cw - 32, 10, 5); c.fill(); roundRect(c, x + 16, y + 62, cw * 0.6, 10, 5); c.fill(); };
      card(140, 66, 230, 110, '#2fddbe'); card(386, 66, 230, 110, '#6b3be6');
      c.fillStyle = '#fff'; roundRect(c, 140, 192, 476, 186, 14); c.fill();
      [0.55, 0.8, 0.45, 0.95, 0.7, 0.6, 0.85].forEach((v, i) => { const gr = c.createLinearGradient(0, 360, 0, 360 - v * 140); gr.addColorStop(0, '#1f6feb'); gr.addColorStop(1, '#2fddbe'); c.fillStyle = gr; roundRect(c, 170 + i * 62, 360 - v * 140, 34, v * 140, 8); c.fill(); });
    });
    const scr = new THREE.Mesh(new THREE.PlaneGeometry(2.14, 1.34), new THREE.MeshBasicMaterial({ map: tex, toneMapped: false }));
    scr.position.z = 0.09; g.add(scr);
    const neck = new THREE.Mesh(new THREE.CylinderGeometry(0.07, 0.09, 0.5, 20), pm(PAL.silver, { metalness: 0.4 })); neck.position.set(0, -0.98, -0.06); g.add(neck);
    const foot = new THREE.Mesh(new THREE.CylinderGeometry(0.46, 0.52, 0.07, 40), pm(PAL.silver, { metalness: 0.4 })); foot.position.set(0, -1.24, -0.06); g.add(foot);
    const pop = new THREE.Group(); pop.add(slab(0.8, 0.46, 0.06, 0.1, pm(0xffffff)));
    pop.add(bar(0.42, 0.08, PAL.teal2, -0.3, 0.08, 0.05)); pop.add(bar(0.56, 0.06, 0xd5dfeb, -0.3, -0.08, 0.05));
    pop.position.set(1.05, 0.62, 0.42); g.add(pop);
    const bdg = checkBadge(0.26); bdg.position.set(-1.1, -0.55, 0.4); g.add(bdg);
    g.position.y = 0.12; g.scale.setScalar(0.88); g.rotation.y = -0.18;
    g.userData.tick = (t, still) => { pop.position.y = 0.62 + bob(t, still, 1.5, 0.07); bdg.rotation.y = still ? 0 : Math.sin(t * 1.4) * 0.5; };
    return g;
  },
  /* pilhas de moedas + seta de economia (Planos, Política comercial) */
  coins() {
    const g = new THREE.Group(), stacks = [];
    const geo = new THREE.CylinderGeometry(0.34, 0.34, 0.11, 44);
    const face = pm(0xffc65a, { metalness: 0.35, roughness: 0.28, emissive: PAL.orange, emissiveIntensity: 0.08 }), edge = pm(0xe89a1c, { metalness: 0.4, roughness: 0.3 });
    [[-0.85, 2], [0, 4], [0.85, 6]].forEach(([x, n], i) => {
      const st = new THREE.Group(); st.position.x = x;
      for (let k = 0; k < n; k++) { const m = new THREE.Mesh(geo, [edge, face, face]); m.position.y = -0.95 + k * 0.125; m.rotation.y = k * 0.7; st.add(m); }
      g.add(st); stacks.push(st);
    });
    const curve = new THREE.CatmullRomCurve3([new THREE.Vector3(-1.3, -0.35, 0.55), new THREE.Vector3(-0.45, -0.05, 0.6), new THREE.Vector3(0.35, 0.2, 0.6), new THREE.Vector3(1.15, 0.78, 0.55)]);
    const arrowMat = pm(PAL.teal2, { emissive: PAL.teal, emissiveIntensity: 0.15 });
    g.add(new THREE.Mesh(new THREE.TubeGeometry(curve, 48, 0.06, 12, false), arrowMat));
    const head = new THREE.Mesh(new THREE.ConeGeometry(0.16, 0.34, 24), arrowMat);
    const end = curve.getPoint(1), tan = curve.getTangent(1);
    head.position.copy(end).addScaledVector(tan, 0.12); head.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), tan); g.add(head);
    g.rotation.set(0.28, -0.3, 0); g.position.y = 0.15;
    g.userData.tick = (t, still) => { stacks.forEach((s, i) => { s.position.y = bob(t, still, 1.6, 0.05, i * 0.9); }); arrowMat.emissiveIntensity = still ? 0.15 : 0.12 + (Math.sin(t * 2.4) + 1) * 0.12; };
    return g;
  },
  /* servidores com LEDs (Hospedagem) */
  server() {
    const g = new THREE.Group(), units = [], leds = [];
    for (let i = 0; i < 3; i++) {
      const u = new THREE.Group(); u.position.y = (i - 1) * 0.56;
      u.add(slab(1.9, 0.46, 1.1, 0.08, pm(0x16306e, { roughness: 0.35 })));
      const plate = slab(1.78, 0.34, 0.04, 0.07, pm(0x23489c)); plate.position.z = 0.58; u.add(plate);
      [-0.55, -0.25].forEach((x) => { [0.06, -0.06].forEach((y) => { const l = new THREE.Mesh(new THREE.BoxGeometry(0.24, 0.03, 0.02), sm(0x8aa4cf)); l.position.set(x, y, 0.61); u.add(l); }); });
      [PAL.teal, PAL.green, PAL.orange].forEach((c, k) => { const l = new THREE.Mesh(new THREE.SphereGeometry(0.045, 16, 12), new THREE.MeshBasicMaterial({ color: c, toneMapped: false })); l.position.set(0.48 + k * 0.14, 0, 0.62); u.add(l); leds.push(l); });
      g.add(u); units.push(u);
    }
    const sh = checkBadge(0.3); sh.position.set(1.0, 0.95, 0.55); g.add(sh);
    g.rotation.set(0.32, -0.55, 0);
    g.userData.tick = (t, still) => {
      units[1].position.z = still ? 0 : Math.max(0, Math.sin(t * 0.9)) * 0.28;
      leds.forEach((l, i) => { l.visible = still || Math.sin(t * (3 + (i % 3)) + i * 1.7) > -0.35; });
      sh.rotation.y = still ? 0 : Math.sin(t * 1.4) * 0.5;
    };
    return g;
  },
  /* pessoas conectadas (Clientes) */
  people() {
    const g = new THREE.Group(), ps = [];
    const person = (c, s) => { const p = new THREE.Group(); const head = new THREE.Mesh(new THREE.SphereGeometry(0.22, 32, 24), pm(c)); head.position.y = 0.32; p.add(head); const body = new THREE.Mesh(new THREE.CapsuleGeometry(0.3, 0.3, 8, 24), pm(c)); body.position.y = -0.32; p.add(body); p.scale.setScalar(s); return p; };
    [[PAL.teal2, -0.82, -0.12, -0.25, 0.85], [PAL.purple, 0.82, -0.12, -0.25, 0.85], [PAL.blue, 0, 0.0, 0.3, 1.12]].forEach(([c, x, y, z, s]) => { const p = person(c, s); p.position.set(x, y, z); g.add(p); ps.push(p); });
    const arc = new THREE.CatmullRomCurve3([new THREE.Vector3(-0.82, 0.3, -0.25), new THREE.Vector3(-0.42, 0.85, 0.05), new THREE.Vector3(0, 0.95, 0.3), new THREE.Vector3(0.42, 0.85, 0.05), new THREE.Vector3(0.82, 0.3, -0.25)]);
    const arcMat = sm(PAL.orange, { emissive: PAL.orange, emissiveIntensity: 0.2 });
    g.add(new THREE.Mesh(new THREE.TubeGeometry(arc, 60, 0.025, 8, false), arcMat));
    const dot = new THREE.Mesh(new THREE.SphereGeometry(0.07, 16, 12), new THREE.MeshBasicMaterial({ color: PAL.orange, toneMapped: false })); g.add(dot);
    g.position.y = -0.05;
    g.userData.tick = (t, still) => { ps.forEach((p, i) => { p.position.y = (i === 2 ? 0 : -0.12) + bob(t, still, 1.3, 0.05, i * 1.2); }); dot.position.copy(arc.getPoint(still ? 0.5 : (Math.sin(t * 0.9) + 1) / 2)); };
    return g;
  },
  /* chave (Área do Cliente) */
  key() {
    const g = new THREE.Group(), mat = pm(PAL.gold, { metalness: 0.55, roughness: 0.25 });
    const bow = new THREE.Mesh(new THREE.TorusGeometry(0.42, 0.13, 20, 48), mat); bow.position.x = -0.75; g.add(bow);
    const gem = new THREE.Mesh(new THREE.CylinderGeometry(0.2, 0.2, 0.12, 32).rotateX(Math.PI / 2), pm(PAL.teal2)); gem.position.x = -0.75; g.add(gem);
    const shaft = new THREE.Mesh(new THREE.CylinderGeometry(0.09, 0.09, 1.5, 24).rotateZ(Math.PI / 2), mat); shaft.position.x = 0.38; g.add(shaft);
    [[0.75, 0.24], [0.98, 0.17], [1.08, 0.3]].forEach(([x, h]) => { const t = new THREE.Mesh(new THREE.BoxGeometry(0.12, h, 0.12), mat); t.position.set(x, -h / 2 - 0.04, 0); g.add(t); });
    const card = slab(1.4, 0.85, 0.06, 0.1, pm(0xffffff)); card.position.set(0.1, -0.15, -0.55); card.rotation.z = 0.12; g.add(card);
    card.add(bar(0.5, 0.09, PAL.blue, -0.5, 0.18, 0.05)); card.add(bar(0.8, 0.07, 0xd5dfeb, -0.5, 0, 0.05)); card.add(bar(0.6, 0.07, 0xd5dfeb, -0.5, -0.16, 0.05));
    g.rotation.z = 0.35; g.position.y = 0.05;
    g.userData.tick = (t, still) => { g.rotation.x = still ? 0 : Math.sin(t * 0.8) * 0.35; };
    return g;
  },
  /* balões de conversa (Contato) */
  chat() {
    const g = new THREE.Group();
    const bubble = (w, h, r, right) => {
      const s = new THREE.Shape(), x = -w / 2, y = -h / 2, a = right ? 0.58 : 0.12, b = right ? 0.88 : 0.42, tip = right ? 0.82 : 0.12;
      s.moveTo(x + r, y); s.lineTo(x + w * a, y); s.lineTo(x + w * tip, y - 0.3); s.lineTo(x + w * b, y); s.lineTo(x + w - r, y);
      s.quadraticCurveTo(x + w, y, x + w, y + r); s.lineTo(x + w, y + h - r); s.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
      s.lineTo(x + r, y + h); s.quadraticCurveTo(x, y + h, x, y + h - r); s.lineTo(x, y + r); s.quadraticCurveTo(x, y, x + r, y);
      return s;
    };
    const big = extrude(bubble(1.9, 1.15, 0.3, false), 0.24, pm(PAL.teal2, { emissive: PAL.teal, emissiveIntensity: 0.08 }), 0.05);
    big.position.set(-0.2, 0.28, 0.1); g.add(big);
    const dots = [-0.55, -0.2, 0.15].map((x) => { const d = new THREE.Mesh(new THREE.SphereGeometry(0.11, 24, 16), sm(0xffffff, { roughness: 0.3 })); d.position.set(x, 0.28, 0.3); g.add(d); return d; });
    const small = extrude(bubble(1.15, 0.66, 0.2, true), 0.18, pm(PAL.navy), 0.04);
    small.position.set(0.72, -0.72, -0.3); g.add(small);
    small.add(bar(0.6, 0.07, 0x8aa4cf, -0.35, 0.1, 0.13)); small.add(bar(0.42, 0.07, PAL.orange, -0.35, -0.08, 0.13));
    g.rotation.y = -0.25;
    g.userData.tick = (t, still) => { dots.forEach((d, i) => { d.position.y = 0.28 + (still ? 0 : Math.max(0, Math.sin(t * 4 - i * 0.7)) * 0.12); }); small.position.y = -0.72 + bob(t, still, 1.2, 0.05, 1); };
    return g;
  },
  /* livro aberto que folheia (Matérias) */
  book() {
    const g = new THREE.Group();
    const pageTex = (title) => canvasTex(256, 340, (c, w, h) => { c.fillStyle = '#fbfcfe'; c.fillRect(0, 0, w, h); c.fillStyle = title; roundRect(c, 24, 30, 150, 18, 9); c.fill(); c.fillStyle = '#dbe3ee'; for (let i = 0; i < 9; i++) { roundRect(c, 24, 76 + i * 26, i % 4 === 3 ? 120 : 208, 10, 5); c.fill(); } });
    const leftTex = pageTex('#2fddbe'), rightTex = pageTex('#6b3be6'), flipTex = pageTex('#ff9a1f');
    const half = (tex, sign, cover) => {
      const pv = new THREE.Group();
      const cv = slab(1.08, 1.46, 0.05, 0.06, pm(cover)); cv.position.set(sign * 0.54, 0, -0.06); pv.add(cv);
      const pg = new THREE.Mesh(new THREE.BoxGeometry(1.0, 1.38, 0.06), [sm(0xeef2f7), sm(0xeef2f7), sm(0xeef2f7), sm(0xeef2f7), new THREE.MeshStandardMaterial({ map: tex, roughness: 0.6 }), sm(0xeef2f7)]);
      pg.position.set(sign * 0.52, 0, 0); pv.add(pg);
      pv.rotation.y = sign * -0.32; return pv;
    };
    g.add(half(leftTex, -1, PAL.navy)); g.add(half(rightTex, 1, PAL.navy));
    const flip = new THREE.Group();
    const fp = new THREE.Mesh(new THREE.PlaneGeometry(1.0, 1.38), new THREE.MeshStandardMaterial({ map: flipTex, roughness: 0.6, side: THREE.DoubleSide }));
    fp.position.set(0.5, 0, 0.04); flip.add(fp); g.add(flip);
    const rib = new THREE.Mesh(new THREE.BoxGeometry(0.1, 0.7, 0.01), sm(PAL.orange)); rib.position.set(0.05, -0.9, 0.08); g.add(rib);
    g.rotation.set(0.45, 0, 0); g.position.y = 0.05; g.scale.setScalar(1.1);
    g.userData.tick = (t, still) => {
      const ph = still ? 0 : (t % 4.5) / 4.5, k = ph < 0.55 ? 0 : ease(Math.min(1, (ph - 0.55) / 0.4));
      flip.rotation.y = -0.32 - k * (Math.PI - 0.64);
    };
    return g;
  },
  /* escudo (Central de Confiança) */
  shield() {
    const g = new THREE.Group();
    const sh = (k) => { const s = new THREE.Shape(); s.moveTo(0, 0.9 * k); s.quadraticCurveTo(0.42 * k, 0.66 * k, 0.8 * k, 0.66 * k); s.lineTo(0.8 * k, 0.05 * k); s.quadraticCurveTo(0.76 * k, -0.62 * k, 0, -1.0 * k); s.quadraticCurveTo(-0.76 * k, -0.62 * k, -0.8 * k, 0.05 * k); s.lineTo(-0.8 * k, 0.66 * k); s.quadraticCurveTo(-0.42 * k, 0.66 * k, 0, 0.9 * k); return s; };
    g.add(extrude(sh(1), 0.26, pm(PAL.blue, { roughness: 0.25 }), 0.05));
    const inner = extrude(sh(0.78), 0.06, pm(PAL.teal2, { emissive: PAL.teal, emissiveIntensity: 0.1 }), 0.02); inner.position.z = 0.17; g.add(inner);
    const ck = new THREE.CatmullRomCurve3([new THREE.Vector3(-0.32, 0.02, 0.24), new THREE.Vector3(-0.08, -0.22, 0.24), new THREE.Vector3(0.36, 0.28, 0.24)], false, 'catmullrom', 0);
    g.add(new THREE.Mesh(new THREE.TubeGeometry(ck, 32, 0.075, 12, false), sm(0xffffff, { roughness: 0.3 })));
    g.userData.tick = (t, still) => { g.rotation.y = still ? -0.2 : Math.sin(t * 0.8) * 0.4; };
    return g;
  },
  /* cadeado que abre e fecha (Privacidade) */
  lock() {
    const g = new THREE.Group();
    const body = slab(1.3, 1.02, 0.5, 0.16, pm(PAL.blue, { roughness: 0.28 })); body.position.y = -0.34; g.add(body);
    const metal = pm(PAL.silver, { metalness: 0.65, roughness: 0.22 });
    const sk = new THREE.Group();
    const arcM = new THREE.Mesh(new THREE.TorusGeometry(0.4, 0.1, 18, 48, Math.PI), metal); arcM.position.y = 0.42; sk.add(arcM);
    [-0.4, 0.4].forEach((x) => { const l = new THREE.Mesh(new THREE.CylinderGeometry(0.1, 0.1, 0.5, 20), metal); l.position.set(x, 0.17, 0); sk.add(l); });
    g.add(sk);
    const hole = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.12, 0.06, 28).rotateX(Math.PI / 2), sm(PAL.navy)); hole.position.set(0, -0.26, 0.29); g.add(hole);
    const slot = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.26, 0.06), sm(PAL.navy)); slot.position.set(0, -0.42, 0.29); g.add(slot);
    const bdg = checkBadge(0.28); bdg.position.set(0.72, -0.72, 0.4); g.add(bdg);
    g.position.y = 0.1; g.rotation.y = -0.25;
    g.userData.tick = (t, still) => { const ph = still ? 0 : (t % 5) / 5, k = ph < 0.6 ? 0 : Math.sin((ph - 0.6) / 0.4 * Math.PI); sk.position.y = k * 0.28; sk.rotation.y = k * 0.6; bdg.rotation.y = still ? 0 : Math.sin(t * 1.5) * 0.5; };
    return g;
  },
  /* cookie com gotas (Política de Cookies) */
  cookie() {
    const g = new THREE.Group();
    const dough = (r, c) => { const m = new THREE.Mesh(new THREE.CylinderGeometry(r, r * 0.97, 0.24, 64).rotateX(Math.PI / 2), [pm(0xc98f4c, { roughness: 0.7, clearcoat: 0 }), pm(c, { roughness: 0.65, clearcoat: 0 }), pm(c, { roughness: 0.65, clearcoat: 0 })]); return m; };
    const main = new THREE.Group(); main.add(dough(0.9, 0xe7b878));
    const chip = new THREE.DodecahedronGeometry(0.09), choc = pm(0x5a3520, { roughness: 0.5 });
    [[-0.4, 0.35], [0.1, 0.5], [0.45, 0.15], [-0.15, 0.05], [-0.5, -0.25], [0.2, -0.35], [0.55, -0.4], [-0.1, -0.6], [0.35, 0.55]].forEach(([x, y], i) => { const c = new THREE.Mesh(chip, choc); c.position.set(x, y, 0.13); c.rotation.set(i, i * 2, 0); c.scale.setScalar(0.8 + (i % 3) * 0.2); main.add(c); });
    g.add(main);
    const sm2 = dough(0.45, 0xeec48a); sm2.position.set(-0.95, -0.7, -0.4); g.add(sm2);
    const bdg = checkBadge(0.3); bdg.position.set(0.8, -0.68, 0.35); g.add(bdg);
    g.userData.tick = (t, still) => { main.rotation.z = still ? 0 : t * 0.25; main.rotation.y = still ? -0.2 : Math.sin(t * 0.9) * 0.3; sm2.position.y = -0.7 + bob(t, still, 1.4, 0.06, 2); };
    return g;
  },
  /* crachá do titular (LGPD) */
  id() {
    const g = new THREE.Group();
    const card = slab(2.0, 1.28, 0.08, 0.14, pm(0xffffff, { roughness: 0.35 })); g.add(card);
    const head = new THREE.Mesh(new THREE.CylinderGeometry(0.34, 0.34, 0.05, 40).rotateX(Math.PI / 2), pm(PAL.purple)); head.position.set(-0.5, 0.1, 0.07); g.add(head);
    const face = new THREE.Mesh(new THREE.SphereGeometry(0.11, 24, 16), sm(0xffffff)); face.position.set(-0.5, 0.17, 0.11); g.add(face);
    const sh = new THREE.Mesh(new THREE.SphereGeometry(0.19, 24, 16, 0, Math.PI * 2, 0, Math.PI / 2), sm(0xffffff)); sh.position.set(-0.5, -0.12, 0.09); sh.scale.set(1, 0.7, 0.4); g.add(sh);
    g.add(bar(0.75, 0.1, PAL.navy, 0.0, 0.26, 0.07)); g.add(bar(0.65, 0.07, 0xd5dfeb, 0.0, 0.07, 0.07)); g.add(bar(0.5, 0.07, 0xd5dfeb, 0.0, -0.08, 0.07));
    g.add(bar(1.6, 0.1, PAL.teal2, -0.8, -0.44, 0.07));
    const clip = slab(0.36, 0.14, 0.1, 0.05, pm(PAL.silver, { metalness: 0.5 })); clip.position.set(0, 0.7, 0); g.add(clip);
    const lockB = new THREE.Group(); lockB.add(slab(0.42, 0.34, 0.16, 0.06, pm(PAL.orange)));
    const ar = new THREE.Mesh(new THREE.TorusGeometry(0.12, 0.035, 12, 32, Math.PI), pm(PAL.silver, { metalness: 0.6 })); ar.position.y = 0.17; lockB.add(ar);
    lockB.position.set(0.85, -0.55, 0.35); g.add(lockB);
    g.rotation.set(0.1, -0.3, 0.06);
    g.userData.tick = (t, still) => { g.rotation.y = still ? -0.3 : -0.3 + Math.sin(t * 0.8) * 0.25; lockB.position.y = -0.55 + bob(t, still, 1.6, 0.06); };
    return g;
  },
  /* balança (Compliance) */
  scale() {
    const g = new THREE.Group(), gold = pm(PAL.gold, { metalness: 0.55, roughness: 0.25 });
    const base = new THREE.Mesh(new THREE.CylinderGeometry(0.55, 0.66, 0.14, 48), pm(PAL.navy)); base.position.y = -1.08; g.add(base);
    const pillar = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.09, 1.55, 20), gold); pillar.position.y = -0.25; g.add(pillar);
    const knob = new THREE.Mesh(new THREE.SphereGeometry(0.11, 24, 16), gold); knob.position.y = 0.6; g.add(knob);
    const beam = new THREE.Mesh(new THREE.BoxGeometry(2.0, 0.07, 0.07), gold); beam.position.y = 0.5; g.add(beam);
    const sides = [-1, 1].map((sgn, i) => {
      const s = new THREE.Group();
      const str = new THREE.Mesh(new THREE.CylinderGeometry(0.012, 0.012, 0.7, 8), sm(0x8aa4cf)); str.position.y = -0.35; s.add(str);
      const pan = new THREE.Mesh(new THREE.CylinderGeometry(0.4, 0.28, 0.07, 40), pm(i ? PAL.teal2 : PAL.purple)); pan.position.y = -0.72; s.add(pan);
      g.add(s); return s;
    });
    const docM = slab(0.34, 0.42, 0.05, 0.04, pm(0xffffff)); docM.rotation.x = -1.2; docM.position.y = -0.58; sides[0].add(docM);
    const bdg = checkBadge(0.18); bdg.position.y = -0.48; sides[1].add(bdg);
    g.userData.tick = (t, still) => {
      const a = still ? 0.04 : Math.sin(t * 0.9) * 0.12;
      beam.rotation.z = a;
      sides.forEach((s, i) => { const sg = i ? 1 : -1; s.position.set(sg * 0.95 * Math.cos(a), 0.5 + sg * 0.95 * Math.sin(a), 0); });
      bdg.rotation.y = still ? 0 : Math.sin(t * 1.5) * 0.5;
    };
    return g;
  },
  /* documento assinado (Termos de Uso) */
  doc() {
    const g = new THREE.Group();
    const s = new THREE.Shape(); s.moveTo(-0.72, -0.98); s.lineTo(0.72, -0.98); s.lineTo(0.72, 0.6); s.lineTo(0.36, 0.98); s.lineTo(-0.72, 0.98); s.closePath();
    const back = extrude(s, 0.08, pm(0xe8eef6), 0.02); back.position.set(-0.22, 0.14, -0.3); back.rotation.z = 0.1; g.add(back);
    g.add(extrude(s, 0.08, pm(0xffffff), 0.02));
    const f = new THREE.Shape(); f.moveTo(0.36, 0.98); f.lineTo(0.36, 0.62); f.lineTo(0.72, 0.6); f.closePath();
    const fold = extrude(f, 0.02, pm(0xd5dfeb), 0); fold.position.z = 0.07; g.add(fold);
    g.add(bar(0.75, 0.1, PAL.teal2, -0.55, 0.62, 0.07));
    [[1.1, 0.36], [0.95, 0.2], [1.12, 0.04], [0.7, -0.12]].forEach(([w, y]) => g.add(bar(w, 0.06, 0xdbe3ee, -0.55, y, 0.07)));
    const sig = new THREE.CatmullRomCurve3([new THREE.Vector3(-0.5, -0.55, 0.07), new THREE.Vector3(-0.35, -0.42, 0.07), new THREE.Vector3(-0.22, -0.62, 0.07), new THREE.Vector3(-0.05, -0.45, 0.07), new THREE.Vector3(0.1, -0.6, 0.07), new THREE.Vector3(0.3, -0.5, 0.07)]);
    g.add(new THREE.Mesh(new THREE.TubeGeometry(sig, 60, 0.022, 8, false), sm(PAL.navy)));
    const pen = new THREE.Group();
    const bodyP = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.06, 0.9, 20), pm(PAL.navy)); bodyP.position.y = 0.55; pen.add(bodyP);
    const tip = new THREE.Mesh(new THREE.ConeGeometry(0.06, 0.16, 20).rotateX(Math.PI), pm(PAL.gold, { metalness: 0.6 })); tip.position.y = 0.04; pen.add(tip);
    pen.rotation.z = -0.55; g.add(pen);
    const bdg = checkBadge(0.26); bdg.position.set(0.66, -0.82, 0.25); g.add(bdg);
    g.rotation.y = -0.22;
    g.userData.tick = (t, still) => { const p = sig.getPoint(still ? 1 : (Math.sin(t * 0.9) + 1) / 2); pen.position.set(p.x, p.y, 0.25); bdg.rotation.y = still ? 0 : Math.sin(t * 1.4) * 0.5; };
    return g;
  },
  /* planeta com brotos (ESG) */
  leaf() {
    const g = new THREE.Group();
    const globe = new THREE.Group(); g.add(globe);
    globe.add(new THREE.Mesh(new THREE.SphereGeometry(0.66, 48, 32), pm(PAL.blue2, { roughness: 0.3 })));
    globe.add(new THREE.Mesh(new THREE.SphereGeometry(0.675, 18, 12), new THREE.MeshBasicMaterial({ color: PAL.teal, wireframe: true, transparent: true, opacity: 0.55 })));
    const ls = new THREE.Shape(); ls.moveTo(0, 0); ls.quadraticCurveTo(0.34, 0.26, 0, 0.82); ls.quadraticCurveTo(-0.34, 0.26, 0, 0);
    const sprout = new THREE.Group(); sprout.position.y = 0.62; g.add(sprout);
    const stem = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.035, 0.3, 12), sm(PAL.green)); stem.position.y = 0.12; sprout.add(stem);
    const l1 = extrude(ls, 0.04, pm(PAL.green), 0.015); l1.position.y = 0.25; l1.rotation.z = -0.75; sprout.add(l1);
    const l2 = extrude(ls, 0.04, pm(PAL.teal2), 0.015); l2.position.y = 0.25; l2.rotation.z = 0.75; l2.scale.setScalar(0.8); sprout.add(l2);
    const orbit = new THREE.Mesh(new THREE.TorusGeometry(1.08, 0.02, 10, 120), sm(PAL.orange)); orbit.rotation.set(1.25, 0.2, 0); g.add(orbit);
    const sat = new THREE.Mesh(new THREE.SphereGeometry(0.09, 20, 14), new THREE.MeshBasicMaterial({ color: PAL.orange, toneMapped: false })); g.add(sat);
    g.position.y = -0.15;
    const ax = new THREE.Vector3();
    g.userData.tick = (t, still) => {
      globe.rotation.y = still ? 0.4 : t * 0.35;
      sprout.rotation.z = still ? 0 : Math.sin(t * 1.3) * 0.08;
      const a = still ? 0.8 : t * 0.9; ax.set(Math.cos(a) * 1.08, Math.sin(a) * 1.08, 0).applyEuler(orbit.rotation); sat.position.copy(ax);
    };
    return g;
  },
  /* símbolo de acessibilidade (Acessibilidade) */
  access() {
    const g = new THREE.Group();
    const ring = new THREE.Mesh(new THREE.TorusGeometry(0.98, 0.1, 20, 90), pm(PAL.blue)); g.add(ring);
    const disc = new THREE.Mesh(new THREE.CylinderGeometry(0.88, 0.88, 0.08, 64).rotateX(Math.PI / 2), pm(0xffffff, { roughness: 0.35 })); disc.position.z = -0.06; g.add(disc);
    const mat = pm(PAL.navy), fig = new THREE.Group(); g.add(fig);
    const head = new THREE.Mesh(new THREE.SphereGeometry(0.15, 28, 20), mat); head.position.set(0, 0.5, 0.08); fig.add(head);
    const arms = new THREE.Mesh(new THREE.CapsuleGeometry(0.055, 0.95, 6, 16).rotateZ(Math.PI / 2), mat); arms.position.set(0, 0.22, 0.08); fig.add(arms);
    const torso = new THREE.Mesh(new THREE.CapsuleGeometry(0.075, 0.36, 6, 16), mat); torso.position.set(0, -0.02, 0.08); fig.add(torso);
    [-1, 1].forEach((sg) => { const l = new THREE.Mesh(new THREE.CapsuleGeometry(0.06, 0.42, 6, 16), mat); l.position.set(sg * 0.14, -0.45, 0.08); l.rotation.z = sg * 0.32; fig.add(l); });
    g.userData.tick = (t, still) => { ring.rotation.z = still ? 0 : t * 0.4; arms.rotation.z = still ? 0 : Math.sin(t * 2) * 0.12; g.rotation.y = still ? -0.2 : Math.sin(t * 0.7) * 0.35; };
    return g;
  },
};

function initEmblem(host) {
  const kind = host.getAttribute('data-emblem');
  const build = EMBLEMS[kind] || EMBLEMS.house;
  const renderer = makeRenderer(host);
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(32, 1, 0.1, 100);
  camera.position.set(0, 0.2, 9.2);
  scene.add(new THREE.HemisphereLight(0xffffff, 0xcfe3ff, 1.3));
  const key = new THREE.DirectionalLight(0xffffff, 2.1); key.position.set(-4, 6, 6); scene.add(key);
  const rim = new THREE.PointLight(0x2fddbe, 16, 20); rim.position.set(3.5, -2, 3); scene.add(rim);
  const warm = new THREE.PointLight(0xff9a1f, 9, 18); warm.position.set(3, 3, 2); scene.add(warm);

  const root = new THREE.Group(); scene.add(root);
  const ring = new THREE.Group(); root.add(ring);
  const colors = [PAL.teal, PAL.orange, PAL.purple, PAL.blue2];
  colors.forEach((c, i) => {
    const g = new THREE.ExtrudeGeometry(crescentShape(i * Math.PI / 2 + 0.3, Math.PI * 0.92, 2.18, 0.36), { depth: 0.14, bevelEnabled: true, bevelThickness: 0.05, bevelSize: 0.04, bevelSegments: 3, curveSegments: 48 });
    g.translate(0, 0, -0.7 + i * 0.02);
    ring.add(new THREE.Mesh(g, pm(c, { clearcoat: 0.8, clearcoatRoughness: 0.25, emissive: c, emissiveIntensity: 0.06 })));
  });
  const halo = new THREE.Mesh(new THREE.TorusGeometry(2.6, 0.03, 12, 160), sm(PAL.navy)); halo.position.z = -0.7; ring.add(halo);
  const obj = build(); obj.scale.multiplyScalar(1.28); root.add(obj);
  const cubes = [], cubeGeo = new THREE.BoxGeometry(0.2, 0.2, 0.2);
  for (let i = 0; i < 8; i++) {
    const m = new THREE.Mesh(cubeGeo, sm(colors[i % 4], { roughness: 0.35 }));
    m.userData = { a: (i / 8) * Math.PI * 2, r: 2.7 + (i % 3) * 0.25, s: 0.18 + (i % 4) * 0.06, y: ((i * 37) % 10) / 10 * 2.4 - 1.2, ph: i * 1.3 };
    m.scale.setScalar(0.55 + (i % 3) * 0.25);
    root.add(m); cubes.push(m);
  }
  root.scale.setScalar(0.94);

  let tx = 0, ty = 0, scrollK = 0;
  window.addEventListener('pointermove', (e) => { tx = (e.clientX / innerWidth - 0.5); ty = (e.clientY / innerHeight - 0.5); }, { passive: true });
  window.addEventListener('scroll', () => { scrollK = Math.min(1, scrollY / 700); }, { passive: true });
  const resize = () => fit(renderer, camera, host);
  new ResizeObserver(resize).observe(host); resize();
  let first = true;
  const loop = runLoop(host, (dt, time) => {
    const still = A3.motionOff();
    ring.rotation.z = still ? 0.2 : ring.rotation.z - dt * 0.16;
    root.rotation.y = lerp(root.rotation.y, tx * 0.55 + (still ? 0 : Math.sin(time * 0.4) * 0.06), 0.06);
    root.rotation.x = lerp(root.rotation.x, ty * 0.3 + scrollK * 0.35, 0.06);
    obj.position.y = (obj.userData.y0 ??= obj.position.y) + bob(time, still, 1.1, 0.05);
    if (obj.userData.tick) obj.userData.tick(time, still);
    cubes.forEach((c) => {
      const u = c.userData, a = u.a + (still ? 0 : time * u.s);
      c.position.set(Math.cos(a) * u.r, u.y + (still ? 0 : Math.sin(time + u.ph) * 0.15), Math.sin(a) * u.r * 0.45 - 0.9);
      c.rotation.set(time * u.s * 2, time * u.s * 3, 0);
    });
    renderer.render(scene, camera);
    if (first) { first = false; host.classList.add('is-3d'); }
  });
  loop.once();
}

/* ------------------------------------------------------------ PAPEL */
function paperTexture(kind) {
  const W = 768, H = 1086, c = document.createElement('canvas');
  c.width = W; c.height = H;
  const g = c.getContext('2d');
  const t = (k) => A3.t(k);
  if (kind === 'ok') {
    g.fillStyle = '#ffffff'; g.fillRect(0, 0, W, H);
    const gr = g.createLinearGradient(0, 0, W, 0); gr.addColorStop(0, '#2fddbe'); gr.addColorStop(0.5, '#1f6feb'); gr.addColorStop(1, '#6b3be6');
    g.fillStyle = gr; g.fillRect(0, 0, W, 22);
    g.fillStyle = '#0e1f4d'; g.font = '800 54px Manrope, Arial, sans-serif'; g.fillText(t('p.ok1'), 60, 130);
    g.fillStyle = '#0a7a80'; g.font = '700 32px Manrope, Arial, sans-serif'; g.fillText(t('p.ok2'), 60, 182);
    g.fillStyle = '#e2e8f0'; for (let i = 0; i < 12; i++) g.fillRect(60, 250 + i * 46, (i % 4 === 3 ? 420 : 640), 16);
    g.fillStyle = '#e3f5ee'; roundRect(g, 60, 830, 470, 70, 35); g.fill();
    g.fillStyle = '#12805c'; g.font = '800 30px Manrope, Arial, sans-serif'; g.fillText('✓ ' + t('p.ok3'), 86, 876);
    g.fillStyle = '#e8f0fe'; roundRect(g, 60, 920, 470, 70, 35); g.fill();
    g.fillStyle = '#1f6feb'; g.fillText('✓ ' + t('p.ok4'), 86, 966);
    qr(g, 580, 830, 140);
  } else {
    g.fillStyle = '#fbf7ec'; g.fillRect(0, 0, W, H);
    for (let i = 0; i < 2200; i++) { g.fillStyle = `rgba(120,100,60,${Math.random() * 0.05})`; g.fillRect(Math.random() * W, Math.random() * H, 2, 2); }
    g.fillStyle = '#3b3b3b'; g.font = '800 58px "Times New Roman", serif'; g.fillText(t('p.doc1') + ' POP-07', 60, 130);
    g.font = 'italic 34px "Times New Roman", serif'; g.fillStyle = '#6b6b6b'; g.fillText(t('p.doc2') + ' · rev. 1 (2019)', 60, 184);
    g.fillStyle = 'rgba(60,60,60,.35)'; for (let i = 0; i < 14; i++) g.fillRect(60, 250 + i * 44, (i % 5 === 4 ? 380 : 640) - Math.random() * 60, 12);
    g.save(); g.translate(430, 820); g.rotate(-0.22);
    g.strokeStyle = 'rgba(198,47,58,.85)'; g.lineWidth = 8; roundRect(g, -10, -70, 330, 110, 12); g.stroke();
    g.fillStyle = 'rgba(198,47,58,.85)'; g.font = '900 44px Arial, sans-serif'; g.fillText(t('p.doc3'), 8, 4); g.restore();
    g.fillStyle = '#8a6d3b'; g.font = 'italic 32px "Comic Sans MS", cursive'; g.fillText(t('p.doc4'), 60, 1000);
    g.strokeStyle = 'rgba(139,94,52,.35)'; g.lineWidth = 10; g.beginPath(); g.arc(610, 230, 70, 0.3, 5.6); g.stroke();
  }
  const tex = new THREE.CanvasTexture(c);
  tex.colorSpace = THREE.SRGBColorSpace; tex.anisotropy = 4;
  return tex;
}
function roundRect(g, x, y, w, h, r) { g.beginPath(); g.moveTo(x + r, y); g.arcTo(x + w, y, x + w, y + h, r); g.arcTo(x + w, y + h, x, y + h, r); g.arcTo(x, y + h, x, y, r); g.arcTo(x, y, x + w, y, r); g.closePath(); }
function qr(g, x, y, s) {
  g.fillStyle = '#fff'; g.fillRect(x, y, s, s); g.fillStyle = '#0b1a3f';
  const n = 21, k = s / n;
  for (let i = 0; i < n; i++) for (let j = 0; j < n; j++) if (((i * 7 + j * 13 + i * j) % 5) < 2) g.fillRect(x + i * k, y + j * k, k, k);
  [[0, 0], [n - 7, 0], [0, n - 7]].forEach(([a, b]) => { g.fillRect(x + a * k, y + b * k, 7 * k, 7 * k); g.fillStyle = '#fff'; g.fillRect(x + (a + 1) * k, y + (b + 1) * k, 5 * k, 5 * k); g.fillStyle = '#0b1a3f'; g.fillRect(x + (a + 2) * k, y + (b + 2) * k, 3 * k, 3 * k); });
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
  gr.addColorStop(0, `rgba(14,31,77,${opacity})`); gr.addColorStop(1, 'rgba(14,31,77,0)');
  g.fillStyle = gr; g.fillRect(0, 0, 128, 128);
  const m = new THREE.Mesh(new THREE.PlaneGeometry(size, size), new THREE.MeshBasicMaterial({ map: new THREE.CanvasTexture(c), transparent: true, depthWrite: false }));
  return m;
}
function paperLights(scene) {
  scene.add(new THREE.HemisphereLight(0xffffff, 0xdfe7f2, 1.4));
  const k = new THREE.DirectionalLight(0xffffff, 2.4); k.position.set(-3, 4, 5); scene.add(k);
  const f = new THREE.DirectionalLight(0xbfe9ff, 0.8); f.position.set(4, -2, 3); scene.add(f);
}

/* ---------- papel controlado pela mão ---------- */
function initPaperHand(host) {
  const card = host.closest('.paper-card') || host.parentNode;
  const ui = {
    status: host.querySelector('.ps-status span'), meter: host,
    cam: card.querySelector('[data-cam]'), range: card.querySelector('[data-crumple]'),
    vbox: host.querySelector('.ps-video')
  };
  const renderer = makeRenderer(host);
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(34, 1, 0.1, 50);
  camera.position.set(0, 0, 7.4);
  paperLights(scene);
  let tex = paperTexture('ok');
  const paper = makePaper(2.3, 3.25, 11);
  const mesh = new THREE.Mesh(paper.geo, paperMaterial(tex));
  scene.add(mesh);
  const sh = shadowBlob(4.2, 0.22); sh.position.set(0.2, -0.3, -0.8); scene.add(sh);
  let target = 0.85, cur = 0.85, rotT = { x: 0, y: 0 }, done = false, handOn = false;
  const setStatus = (k) => { if (ui.status) ui.status.textContent = A3.t(k); };
  setStatus('p.drag');
  /* arrastar (sem câmera) */
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
  function syncRange() { if (ui.range) ui.range.value = Math.round((1 - target) * 100); if (ui.range) ui.range.style.setProperty('--p', ((1 - target) * 100) + '%'); loop.kick(); }
  if (ui.range) ui.range.addEventListener('input', () => { target = 1 - ui.range.value / 100; ui.range.style.setProperty('--p', ui.range.value + '%'); loop.kick(); });
  /* câmera + MediaPipe */
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
      if (ui.cam) { ui.cam.setAttribute('aria-pressed', 'true'); ui.cam.querySelector('span') && (ui.cam.querySelector('span').textContent = A3.t('p.camoff')); }
      loop.kick();
    } catch (err) {
      camOff(); setStatus('p.nocam');
    }
  }
  function camOff() {
    if (stream) stream.getTracks().forEach((tr) => tr.stop());
    stream = null; handOn = false;
    if (ui.vbox) ui.vbox.classList.remove('is-on');
    if (ui.cam) { ui.cam.setAttribute('aria-pressed', 'false'); ui.cam.querySelector('span') && (ui.cam.querySelector('span').textContent = A3.t('p.camon')); }
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
    g.fillStyle = '#2fddbe';
    lm.forEach((p) => { g.beginPath(); g.arc(p.x * vcanvas.width, p.y * vcanvas.height, 3, 0, 7); g.fill(); });
    setStatus('p.hand');
  }
  const resize = () => fit(renderer, camera, host);
  new ResizeObserver(resize).observe(host); resize();
  let first = true;
  const loop = runLoop(host, (dt, time) => {
    trackHand();
    cur = A3.motionOff() ? target : lerp(cur, target, 0.12);
    paper.crumple(cur);
    host.style.setProperty('--open', (1 - cur).toFixed(3));
    mesh.rotation.y = lerp(mesh.rotation.y, rotT.y + (A3.motionOff() ? 0 : Math.sin(time * 0.6) * 0.06 * cur), 0.08);
    mesh.rotation.x = lerp(mesh.rotation.x, rotT.x, 0.08);
    mesh.rotation.z = cur * 0.5;
    if (cur < 0.03 && !done) { done = true; setStatus('p.done'); }
    if (cur > 0.2) done = false;
    renderer.render(scene, camera);
    if (first) { first = false; host.classList.add('is-3d'); }
    if (handOn || Math.abs(cur - target) > 0.002) loop.kick();
  });
  document.addEventListener('a3:lang', () => { tex.dispose(); tex = paperTexture('ok'); mesh.material.map = tex; mesh.material.needsUpdate = true; setStatus(handOn ? 'p.show' : 'p.drag'); loop.once(); });
  loop.once();
}

/* ---------- papéis com paralaxe real ---------- */
function noteTexture() {
  const c = document.createElement('canvas'); c.width = c.height = 512;
  const g = c.getContext('2d');
  g.fillStyle = '#ffe680'; g.fillRect(0, 0, 512, 512);
  g.fillStyle = 'rgba(0,0,0,.05)'; g.fillRect(0, 0, 512, 60);
  g.fillStyle = '#5a4a12'; g.font = '700 46px "Comic Sans MS", cursive';
  wrap(g, A3.t('p.note'), 40, 150, 440, 60);
  return Object.assign(new THREE.CanvasTexture(c), { colorSpace: THREE.SRGBColorSpace });
}
function oldTexture() {
  const c = document.createElement('canvas'); c.width = 768; c.height = 1086;
  const g = c.getContext('2d');
  g.fillStyle = '#f3ead2'; g.fillRect(0, 0, 768, 1086);
  g.fillStyle = 'rgba(80,60,30,.35)'; for (let i = 0; i < 16; i++) g.fillRect(60, 200 + i * 44, 600 - (i % 3) * 90, 11);
  g.fillStyle = '#5c4a2a'; g.font = '800 50px "Times New Roman", serif'; g.fillText('POLÍTICA', 60, 120);
  g.font = 'italic 34px "Times New Roman", serif'; g.fillText(A3.t('p.old'), 60, 960);
  g.strokeStyle = 'rgba(92,74,42,.55)'; g.lineWidth = 3; g.beginPath(); g.moveTo(420, 1000); g.bezierCurveTo(470, 940, 520, 1040, 600, 980); g.stroke();
  return Object.assign(new THREE.CanvasTexture(c), { colorSpace: THREE.SRGBColorSpace });
}
function wrap(g, text, x, y, mw, lh) {
  const words = String(text).split(' '); let line = '';
  words.forEach((w) => { const t = line + w + ' '; if (g.measureText(t).width > mw && line) { g.fillText(line, x, y); line = w + ' '; y += lh; } else line = t; });
  g.fillText(line, x, y);
}
function initPaperParallax(host) {
  const card = host.closest('.paper-card') || host.parentNode;
  const renderer = makeRenderer(host);
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(30, 1, 0.1, 60);
  camera.position.set(0, 0, 9);
  paperLights(scene);
  /* camadas em profundidade real */
  const back = new THREE.Mesh(new THREE.PlaneGeometry(2.5, 3.5), new THREE.MeshStandardMaterial({ map: oldTexture(), roughness: 1 }));
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
  /* partículas em várias profundidades (pista de profundidade) */
  const pg = new THREE.BufferGeometry(), pts = [];
  for (let i = 0; i < 160; i++) pts.push((Math.random() - 0.5) * 9, (Math.random() - 0.5) * 6, -4 + Math.random() * 7);
  pg.setAttribute('position', new THREE.Float32BufferAttribute(pts, 3));
  scene.add(new THREE.Points(pg, new THREE.PointsMaterial({ color: 0x18bec0, size: 0.04, transparent: true, opacity: 0.55 })));

  let mx = 0, my = 0, crT = 0.6, crC = 0.6, flat = false;
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
    const still = A3.motionOff();
    cam.x = lerp(cam.x, mx * 2.1, still ? 1 : 0.07); cam.y = lerp(cam.y, -my * 1.4, still ? 1 : 0.07);
    camera.position.set(cam.x, cam.y, 9); camera.lookAt(0, 0, 0);
    crC = still ? crT : lerp(crC, crT, 0.08);
    mid.crumple(crC);
    midMesh.rotation.z = crC * 0.25 - 0.05;
    note.rotation.y = still ? 0 : Math.sin(time * 1.3) * 0.08;
    renderer.render(scene, camera);
    if (first) { first = false; host.classList.add('is-3d'); }
    if (Math.abs(cam.x - mx * 2.1) > 0.002 || Math.abs(cam.y + my * 1.4) > 0.002 || Math.abs(crC - crT) > 0.002) loop.kick();
  });
  document.addEventListener('a3:lang', () => {
    midTex.dispose(); okTex.dispose(); midTex = paperTexture('old'); okTex = paperTexture('ok');
    midMesh.material.map = flat ? okTex : midTex; midMesh.material.needsUpdate = true;
    note.material.map.dispose(); note.material.map = noteTexture(); note.material.needsUpdate = true;
    back.material.map.dispose(); back.material.map = oldTexture(); back.material.needsUpdate = true;
    loop.once();
  });
  loop.once();
}

/* ------------------------------------------------------------ entrada */
export function init(api) {
  A3 = api;
  const test = document.createElement('canvas');
  if (!(test.getContext('webgl2') || test.getContext('webgl'))) return;
  const scenes = { hero: initHero, emblem: initEmblem, 'paper-hand': initPaperHand, 'paper-parallax': initPaperParallax };
  document.querySelectorAll('[data-3d]').forEach((el) => {
    const fn = scenes[el.getAttribute('data-3d')];
    if (!fn || el.__a3) return;
    el.__a3 = true;
    try { fn(el); } catch (err) { console.warn('[Alicerce360] cena 3D falhou:', err); }
  });
}
