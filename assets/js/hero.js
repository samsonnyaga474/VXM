/**
 * VXM Cinematic Scroll-Driven Hero
 * Lightweight canvas particles + GSAP ScrollTrigger sequence
 * Respects prefers-reduced-motion
 */

(function () {
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ---------- Canvas ambient particles ----------
  const canvas = document.getElementById('heroCanvas');
  if (canvas) {
    const ctx = canvas.getContext('2d');
    let w, h, particles = [];
    const COUNT = reduced ? 20 : 60;

    function resize() {
      w = canvas.width = window.innerWidth;
      h = canvas.height = window.innerHeight;
    }

    function createParticles() {
      particles = [];
      for (let i = 0; i < COUNT; i++) {
        particles.push({
          x: Math.random() * w,
          y: Math.random() * h,
          r: Math.random() * 1.8 + 0.4,
          vx: (Math.random() - 0.5) * 0.3,
          vy: (Math.random() - 0.5) * 0.3,
          a: Math.random() * 0.4 + 0.1
        });
      }
    }

    function draw() {
      ctx.clearRect(0, 0, w, h);
      for (const p of particles) {
        p.x += p.vx;
        p.y += p.vy;
        if (p.x < 0 || p.x > w) p.vx *= -1;
        if (p.y < 0 || p.y > h) p.vy *= -1;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(56, 189, 248, ${p.a})`;
        ctx.fill();
      }
      // subtle connecting lines
      if (!reduced) {
        for (let i = 0; i < particles.length; i++) {
          for (let j = i + 1; j < particles.length; j++) {
            const a = particles[i], b = particles[j];
            const dx = a.x - b.x, dy = a.y - b.y;
            const dist = Math.sqrt(dx * dx + dy * dy);
            if (dist < 120) {
              ctx.beginPath();
              ctx.moveTo(a.x, a.y);
              ctx.lineTo(b.x, b.y);
              ctx.strokeStyle = `rgba(56, 189, 248, ${0.08 * (1 - dist / 120)})`;
              ctx.stroke();
            }
          }
        }
      }
      requestAnimationFrame(draw);
    }

    resize();
    createParticles();
    draw();
    window.addEventListener('resize', () => { resize(); createParticles(); });
  }

  // ---------- Scroll-driven text sequence ----------
  if (typeof gsap === 'undefined' || reduced) {
    // Fallback: simple fade-in
    ['heroTag', 'heroTitle', 'heroSub', 'heroCta'].forEach((id, i) => {
      const el = document.getElementById(id);
      if (el) {
        el.style.opacity = '1';
        el.style.transform = 'none';
        el.style.transition = `opacity 0.6s ${i * 0.1}s`;
      }
    });
    return;
  }

  gsap.registerPlugin(ScrollTrigger);

  const tl = gsap.timeline({
    scrollTrigger: {
      trigger: '#hero',
      start: 'top top',
      end: '+=180%',
      pin: true,
      scrub: 0.8,
      anticipatePin: 1
    }
  });

  // Stage 1 — Intro
  tl.to('#heroTag', { opacity: 1, y: 0, duration: 0.4 }, 0)
    .to('#heroTitle', { opacity: 1, y: 0, duration: 0.5 }, 0.1)
    .to('#heroSub', { opacity: 1, y: 0, duration: 0.4 }, 0.25)
    .to('#heroCta', { opacity: 1, y: 0, duration: 0.4 }, 0.4);

  // Stage 2 — Shift messaging (digital environment)
  tl.to('#heroTitle', {
    opacity: 0.15,
    scale: 0.92,
    filter: 'blur(4px)',
    duration: 0.5
  }, 1.0)
    .to('#heroSub', { opacity: 0, duration: 0.3 }, 1.0)
    .to('#heroCta', { opacity: 0, duration: 0.3 }, 1.0)
    .to('#heroTag', { opacity: 0, duration: 0.3 }, 1.0);

  // Stage 3 — New headline appears
  tl.set('#heroTitle', {
    text: 'WORK. GROW. EARN.',
    filter: 'blur(0px)',
    scale: 1,
    y: 20
  }, 1.5)
    .to('#heroTitle', { opacity: 1, y: 0, duration: 0.5 }, 1.5)
    .set('#heroSub', {
      text: 'Tasks become earnings. Referrals become growth. Your wallet becomes progress.',
      y: 15
    }, 1.7)
    .to('#heroSub', { opacity: 1, y: 0, duration: 0.4 }, 1.7);

  // Stage 4 — Fade toward main site
  tl.to('#scrollHint', { opacity: 0, duration: 0.3 }, 2.2)
    .to('#heroTitle', { opacity: 0.4, duration: 0.4 }, 2.4)
    .to('#heroSub', { opacity: 0.3, duration: 0.4 }, 2.4);

})();
