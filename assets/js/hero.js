/**
 * VXM Homepage Motion System — Final Experience Pass
 * Coordinated: Mouse (local) + Scroll (narrative) + Time (ambient)
 * Lightweight RAF. Respects reduced-motion and mobile.
 */
(function () {
  'use strict';
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const isTouch = window.matchMedia('(hover: none) and (pointer: coarse)').matches;
  const fine = window.matchMedia('(pointer: fine)').matches;
  let scrollProgress = 0, targetScroll = 0;
  let mouseX = 0, mouseY = 0, curMX = 0, curMY = 0;
  const core = document.getElementById('vxmCore');
  const layers = document.querySelectorAll('[data-parallax]');
  const hero = document.getElementById('hero');

  const canvas = document.getElementById('vxmCoreCanvas');
  if (canvas && !reduced) {
    const ctx = canvas.getContext('2d');
    let particles = [];
    const COUNT = isTouch ? 18 : 42;
    function resize() {
      const parent = canvas.parentElement;
      if (!parent) return;
      const rect = parent.getBoundingClientRect();
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      canvas.width = Math.floor(rect.width * dpr);
      canvas.height = Math.floor(rect.height * dpr);
      canvas.style.width = rect.width + 'px';
      canvas.style.height = rect.height + 'px';
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }
    function create() {
      particles = [];
      const w = canvas.clientWidth || 400, h = canvas.clientHeight || 400;
      for (let i = 0; i < COUNT; i++) {
        particles.push({ x: Math.random()*w, y: Math.random()*h, r: Math.random()*1.4+0.3, vx: (Math.random()-0.5)*0.18, vy: (Math.random()-0.5)*0.18, a: Math.random()*0.28+0.05 });
      }
    }
    function draw() {
      const w = canvas.clientWidth || 400, h = canvas.clientHeight || 400;
      ctx.clearRect(0, 0, w, h);
      for (const p of particles) {
        p.x += p.vx; p.y += p.vy;
        if (p.x < 0 || p.x > w) p.vx *= -1;
        if (p.y < 0 || p.y > h) p.vy *= -1;
        ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI*2);
        ctx.fillStyle = 'rgba(34,211,238,' + p.a + ')'; ctx.fill();
      }
      for (let i = 0; i < particles.length; i++) {
        for (let j = i+1; j < particles.length; j++) {
          const a = particles[i], b = particles[j];
          const dx = a.x-b.x, dy = a.y-b.y, dist = Math.sqrt(dx*dx+dy*dy);
          if (dist < 90) {
            ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y);
            ctx.strokeStyle = 'rgba(34,211,238,' + (0.05*(1-dist/90)) + ')'; ctx.stroke();
          }
        }
      }
      requestAnimationFrame(draw);
    }
    resize(); create(); draw();
    window.addEventListener('resize', function(){ resize(); create(); }, {passive:true});
  }

  function updateScrollProgress() {
    const y = window.pageYOffset || document.documentElement.scrollTop;
    targetScroll = Math.min(Math.max(y / 1100, 0), 1);
  }
  window.addEventListener('scroll', updateScrollProgress, {passive:true});
  updateScrollProgress();

  if (!reduced && fine && !isTouch) {
    window.addEventListener('mousemove', function(e) {
      if (!hero) return;
      const r = hero.getBoundingClientRect();
      mouseX = (e.clientX - (r.left + r.width/2)) / Math.max(r.width, 1);
      mouseY = (e.clientY - (r.top + r.height/2)) / Math.max(r.height, 1);
    }, {passive:true});
  }

  function tick() {
    scrollProgress += (targetScroll - scrollProgress) * 0.08;
    curMX += (mouseX - curMX) * 0.06;
    curMY += (mouseY - curMY) * 0.06;
    if (core) {
      const rotY = curMX * 14 + scrollProgress * 25;
      const rotX = -curMY * 10 + scrollProgress * -8;
      const tx = curMX * 18 + scrollProgress * -30;
      const ty = curMY * 14 + scrollProgress * 40;
      const scale = 1 - scrollProgress * 0.18;
      const opacity = 1 - scrollProgress * 0.45;
      core.style.transform = 'translate3d('+tx+'px,'+ty+'px,0) rotateX('+rotX+'deg) rotateY('+rotY+'deg) scale('+scale+')';
      core.style.opacity = String(Math.max(opacity, 0.15));
    }
    layers.forEach(function(el) {
      const d = parseFloat(el.getAttribute('data-parallax')) || 1;
      const mx = curMX * 26 * d + scrollProgress * (-12 * d);
      const my = curMY * 18 * d + scrollProgress * (18 * d);
      el.style.transform = 'translate3d('+mx+'px,'+my+'px,0)';
      el.style.opacity = String(Math.max(1 - scrollProgress * 0.7, 0.1));
    });
    requestAnimationFrame(tick);
  }
  if (!reduced) tick();
  else if (core) { core.style.transform = 'none'; core.style.opacity = '1'; }

  const reveals = document.querySelectorAll('.reveal, .story-block');
  if (reveals.length && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          const s = entry.target.closest('[data-story]');
          if (s) s.classList.add('in-view');
          io.unobserve(entry.target);
        }
      });
    }, {threshold:0.08, rootMargin:'0px 0px -8% 0px'});
    reveals.forEach(function(el){
      const rect = el.getBoundingClientRect();
      if (rect.top < window.innerHeight * 0.92 && rect.bottom > 0) {
        el.classList.add('visible');
        const s = el.closest('[data-story]');
        if (s) s.classList.add('in-view');
      } else {
        io.observe(el);
      }
    });
  } else {
    reveals.forEach(function(el){ el.classList.add('visible'); });
  }

  document.querySelectorAll('a[href^="#"]').forEach(function(a) {
    a.addEventListener('click', function(e) {
      const id = this.getAttribute('href');
      if (!id || id.length < 2) return;
      const t = document.querySelector(id);
      if (!t) return;
      e.preventDefault();
      const top = t.getBoundingClientRect().top + window.pageYOffset - 80;
      window.scrollTo({ top: top, behavior: reduced ? 'auto' : 'smooth' });
    });
  });

  const nav = document.getElementById('mainNav');
  if (nav) {
    const onS = function(){ nav.classList.toggle('scrolled', window.scrollY > 24); };
    window.addEventListener('scroll', onS, {passive:true});
    onS();
  }
})();
