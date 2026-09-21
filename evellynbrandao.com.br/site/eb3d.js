/* ÉVELLYN BRANDÃO · banners 3D interativos (Three.js) — ouro sobre preto
   Uso: <div class="eb-3d" data-eb-3d="hero|page|post"></div> dentro de .eb-hero/.eb-banner */
(function () {
  var els = Array.prototype.slice.call(document.querySelectorAll('[data-eb-3d]'));
  if (!els.length) return;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduce) return;
  try { var tc = document.createElement('canvas'); if (!(tc.getContext('webgl') || tc.getContext('experimental-webgl'))) return; } catch (e) { return; }
  var mobile = 760 > window.innerWidth;
  var s = document.createElement('script');
  s.src = 'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js';
  s.async = true; s.onload = function () { els.forEach(build); };
  document.head.appendChild(s);

  var pointer = { tx: 0, ty: 0 };
  window.addEventListener('pointermove', function (e) { pointer.tx = (e.clientX / window.innerWidth - 0.5) * 2; pointer.ty = (e.clientY / window.innerHeight - 0.5) * 2; }, { passive: true });
  window.addEventListener('deviceorientation', function (e) { if (e.gamma != null) { pointer.tx = Math.max(-1, Math.min(1, e.gamma / 30)); pointer.ty = Math.max(-1, Math.min(1, (e.beta - 45) / 30)); } }, { passive: true });

  function build(el) {
    var variant = el.getAttribute('data-eb-3d') || 'page';
    var renderer;
    try { renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' }); } catch (e) { return; }
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.setClearColor(0x000000, 0);
    el.appendChild(renderer.domElement);

    var scene = new THREE.Scene();
    var camera = new THREE.PerspectiveCamera(42, 1, 0.1, 100);
    camera.position.set(0, 0, 10);

    scene.add(new THREE.AmbientLight(0xffe9b0, 0.35));
    var key = new THREE.DirectionalLight(0xfff3cc, 1.25); key.position.set(4, 7, 6); scene.add(key);
    var rim = new THREE.PointLight(0xd4af37, 1.6, 40); rim.position.set(-7, -3, 5); scene.add(rim);
    var rim2 = new THREE.PointLight(0xf3d77c, 1.0, 40); rim2.position.set(7, -5, -3); scene.add(rim2);
    var back = new THREE.PointLight(0xb8860b, 0.8, 40); back.position.set(0, 6, -6); scene.add(back);

    var gold = new THREE.MeshStandardMaterial({ color: 0xd4af37, metalness: 1, roughness: 0.2, emissive: 0x2c2005, emissiveIntensity: 0.45 });
    var goldDark = new THREE.MeshStandardMaterial({ color: 0x9a7a25, metalness: 1, roughness: 0.35, emissive: 0x1a1203, emissiveIntensity: 0.4 });
    var wire = new THREE.MeshBasicMaterial({ color: 0xf3d77c, wireframe: true, transparent: true, opacity: 0.22 });
    var wire2 = new THREE.MeshBasicMaterial({ color: 0xd4af37, wireframe: true, transparent: true, opacity: 0.12 });

    var group = new THREE.Group(); scene.add(group);
    var spinners = [];

    if (variant === 'hero') {
      var knot = new THREE.Mesh(new THREE.TorusKnotGeometry(1.55, 0.44, mobile ? 120 : 220, mobile ? 16 : 28, 2, 3), gold);
      group.add(knot); spinners.push({ m: knot, x: 0.35, y: 0.55 });
      var ico = new THREE.Mesh(new THREE.IcosahedronGeometry(3.4, 1), wire);
      group.add(ico); spinners.push({ m: ico, x: -0.08, y: 0.12 });
      var r1 = new THREE.Mesh(new THREE.TorusGeometry(4.1, 0.025, 12, 160), goldDark); r1.rotation.x = Math.PI / 2.4; group.add(r1); spinners.push({ m: r1, x: 0.05, y: 0.2, z: 0.1 });
      var r2 = new THREE.Mesh(new THREE.TorusGeometry(4.6, 0.02, 12, 160), gold); r2.rotation.x = Math.PI / 1.7; r2.rotation.y = 0.6; group.add(r2); spinners.push({ m: r2, x: -0.12, y: 0.15 });
      var gems = new THREE.Group();
      for (var g = 0; 7 > g; g++) { var gm = new THREE.Mesh(new THREE.OctahedronGeometry(0.16 + Math.random() * 0.14, 0), gold); var a = g / 7 * Math.PI * 2; gm.position.set(Math.cos(a) * 4.4, Math.sin(a * 1.7) * 1.2, Math.sin(a) * 4.4); gems.add(gm); }
      group.add(gems); spinners.push({ m: gems, x: 0, y: 0.32 });
    } else if (variant === 'post') {
      var r3 = new THREE.Mesh(new THREE.TorusGeometry(3.2, 0.03, 12, 160), gold); r3.rotation.x = Math.PI / 2.2; group.add(r3); spinners.push({ m: r3, x: 0.08, y: 0.25 });
      var r4 = new THREE.Mesh(new THREE.TorusGeometry(3.9, 0.02, 12, 160), goldDark); r4.rotation.x = Math.PI / 1.6; r4.rotation.y = 0.5; group.add(r4); spinners.push({ m: r4, x: -0.1, y: 0.18 });
      var oc = new THREE.Mesh(new THREE.OctahedronGeometry(1.1, 0), gold); group.add(oc); spinners.push({ m: oc, x: 0.3, y: 0.5 });
      var ocw = new THREE.Mesh(new THREE.IcosahedronGeometry(2.1, 1), wire2); group.add(ocw); spinners.push({ m: ocw, x: -0.1, y: 0.1 });
    } else {
      var core = new THREE.Mesh(new THREE.IcosahedronGeometry(1.35, 0), gold); group.add(core); spinners.push({ m: core, x: 0.3, y: 0.45 });
      var shell = new THREE.Mesh(new THREE.IcosahedronGeometry(2.6, 1), wire); group.add(shell); spinners.push({ m: shell, x: -0.1, y: 0.14 });
      var ring = new THREE.Mesh(new THREE.TorusGeometry(3.4, 0.03, 12, 160), goldDark); ring.rotation.x = Math.PI / 2.3; group.add(ring); spinners.push({ m: ring, x: 0.06, y: 0.22 });
      var ring2 = new THREE.Mesh(new THREE.TorusGeometry(3.9, 0.02, 12, 160), gold); ring2.rotation.x = Math.PI / 1.5; ring2.rotation.y = 0.7; group.add(ring2); spinners.push({ m: ring2, x: -0.12, y: 0.16 });
    }

    /* partículas douradas (pólen/poeira de campo) */
    var N = variant === 'hero' ? (mobile ? 500 : 1500) : (mobile ? 300 : 800);
    var pos = new Float32Array(N * 3), base = new Float32Array(N * 3), spd = new Float32Array(N);
    for (var i = 0; N > i; i++) {
      pos[i * 3] = base[i * 3] = (Math.random() - 0.5) * 34;
      pos[i * 3 + 1] = base[i * 3 + 1] = (Math.random() - 0.5) * 20;
      pos[i * 3 + 2] = base[i * 3 + 2] = (Math.random() - 0.5) * 14 - 2;
      spd[i] = 0.2 + Math.random() * 0.8;
    }
    var pg = new THREE.BufferGeometry(); pg.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    var pm = new THREE.PointsMaterial({ color: 0xf3d77c, size: variant === 'hero' ? 0.06 : 0.05, transparent: true, opacity: 0.7, sizeAttenuation: true, depthWrite: false });
    var pts = new THREE.Points(pg, pm); scene.add(pts);

    var mx = 0, my = 0, offX = 0;
    function resize() {
      var w = el.clientWidth || 1, h = el.clientHeight || 1;
      renderer.setSize(w, h, false); camera.aspect = w / h; camera.updateProjectionMatrix();
      var narrow = 1000 > w;
      offX = narrow ? 1.2 : (variant === 'hero' ? 3.1 : 3.6);
      var scale = narrow ? (variant === 'hero' ? 0.5 : 0.42) : 1;
      group.scale.set(scale, scale, scale);
      group.position.x = offX;
      group.position.z = narrow ? -2.5 : 0;
      pm.opacity = narrow ? 0.45 : 0.7;
    }
    resize(); window.addEventListener('resize', resize);

    var visible = true;
    if ('IntersectionObserver' in window) new IntersectionObserver(function (en) { visible = en[0].isIntersecting; }, { threshold: 0 }).observe(el);
    var clock = new THREE.Clock(), scrollY = 0;
    window.addEventListener('scroll', function () { scrollY = window.scrollY; }, { passive: true });

    function tick() {
      requestAnimationFrame(tick);
      if (!visible || document.hidden) return;
      var t = clock.getElapsedTime();
      mx += (pointer.tx - mx) * 0.05; my += (pointer.ty - my) * 0.05;
      spinners.forEach(function (sp) { sp.m.rotation.y += sp.y * 0.008; sp.m.rotation.x += sp.x * 0.008; if (sp.z) sp.m.rotation.z += sp.z * 0.008; });
      group.rotation.y = mx * 0.55; group.rotation.x = my * 0.35 + Math.sin(t * 0.3) * 0.08;
      group.position.y = Math.sin(t * 0.6) * 0.18 - scrollY * 0.0022;
      group.position.x = offX + mx * 0.3;
      var p = pg.attributes.position.array;
      for (var i = 0; N > i; i++) {
        p[i * 3 + 1] = base[i * 3 + 1] + Math.sin(t * spd[i] + i) * 0.35;
        p[i * 3] = base[i * 3] + Math.cos(t * 0.3 * spd[i] + i * 0.7) * 0.25 + mx * 0.6;
      }
      pg.attributes.position.needsUpdate = true;
      pts.rotation.y = t * 0.012;
      camera.position.x = mx * 0.5; camera.position.y = -my * 0.35; camera.lookAt(0, 0, 0);
      renderer.render(scene, camera);
    }
    tick();
  }
})();
