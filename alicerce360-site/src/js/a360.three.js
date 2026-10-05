/* =====================================================================
   Alicerce360 · cenas 3D (módulo ES carregado sob demanda pelo núcleo)
   - hero: alicerce + anéis do logo, reage ao mouse e à rolagem
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
  const scenes = { hero: initHero, 'paper-hand': initPaperHand, 'paper-parallax': initPaperParallax };
  document.querySelectorAll('[data-3d]').forEach((el) => {
    const fn = scenes[el.getAttribute('data-3d')];
    if (!fn || el.__a3) return;
    el.__a3 = true;
    try { fn(el); } catch (err) { console.warn('[Alicerce360] cena 3D falhou:', err); }
  });
}
