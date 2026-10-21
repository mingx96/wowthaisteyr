/* Optional homepage motion: native scrolling and media work without this file. */
(() => {
  'use strict';
  const init = () => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const galleries = [];

    document.querySelectorAll('[data-home-gallery]').forEach(gallery => {
      const track = gallery.querySelector('[data-home-gallery-track]');
      const slides = [...gallery.querySelectorAll('[data-home-gallery-slide]')];
      const controls = gallery.querySelector('[data-home-gallery-controls]');
      const previous = gallery.querySelector('[data-home-gallery-prev]');
      const next = gallery.querySelector('[data-home-gallery-next]');
      const status = gallery.querySelector('[data-home-gallery-status]');
      if (!track || !controls || !previous || !next || !status || slides.length < 2) return;

      let maximum = 0;
      let positions = [];
      let frame = 0;
      const pauseFilms = () => gallery.querySelectorAll('[data-home-film]').forEach(film => film.pause());
      const update = () => {
        frame = 0;
        const position = Math.max(0, Math.min(maximum, track.scrollLeft));
        const overflow = maximum > 1;
        const atStart = position <= 1;
        const atEnd = position >= maximum - 1;
        // Keep keyboard navigation available when the active arrow becomes disabled.
        if ((!overflow && controls.contains(document.activeElement))
          || (atStart && document.activeElement === previous)
          || (atEnd && document.activeElement === next)) track.focus({ preventScroll: true });
        controls.hidden = !overflow;
        previous.disabled = !overflow || atStart;
        next.disabled = !overflow || atEnd;
        // Several slide starts can share the clamped end position on wide screens.
        const index = !overflow || atStart ? 0 : atEnd ? slides.length - 1
          : positions.reduce((best, offset, candidate) =>
            Math.abs(offset - position) < Math.abs(positions[best] - position) ? candidate : best, 0);
        const label = `Moment ${index + 1} von ${slides.length}`;
        if (status.textContent !== label) status.textContent = label;
        if (index > 0) pauseFilms();
      };
      const measure = () => {
        maximum = Math.max(0, track.scrollWidth - track.clientWidth);
        const first = slides[0].getBoundingClientRect().left;
        positions = slides.map(slide => Math.min(maximum, slide.getBoundingClientRect().left - first));
        update();
      };
      const move = direction => {
        const position = Math.max(0, Math.min(maximum, track.scrollLeft));
        const target = direction > 0
          ? positions.find(offset => offset > position + 1)
          : [...positions].reverse().find(offset => offset < position - 1);
        if (target === undefined) return;
        pauseFilms();
        track.scrollTo({ left: target, behavior: reducedMotion.matches ? 'instant' : 'smooth' });
        update();
      };
      previous.addEventListener('click', () => move(-1));
      next.addEventListener('click', () => move(1));
      track.addEventListener('scroll', () => {
        if (!frame) frame = window.requestAnimationFrame(update);
      }, { passive: true });
      if ('ResizeObserver' in window) {
        const resize = new ResizeObserver(measure);
        resize.observe(track);
        slides.forEach(slide => resize.observe(slide));
      } else window.addEventListener('resize', measure);
      galleries.push(() => {
        // Stop an in-flight smooth scroll when the visitor requests less motion.
        if (reducedMotion.matches) track.scrollTo({ left: track.scrollLeft, behavior: 'instant' });
        measure();
      });
      measure();
    });

    const films = [...document.querySelectorAll('[data-home-film]')];
    const pauseAllFilms = () => films.forEach(film => film.pause());
    if (films.length) {
      if ('IntersectionObserver' in window) {
        const visibility = new IntersectionObserver(entries => {
          entries.forEach(entry => { if (!entry.isIntersecting) entry.target.pause(); });
        });
        films.forEach(film => visibility.observe(film));
      }
      document.addEventListener('visibilitychange', () => { if (document.hidden) pauseAllFilms(); });
      window.addEventListener('pagehide', pauseAllFilms);
    }

    const animations = new Set();
    let reveals;
    if (!reducedMotion.matches && 'IntersectionObserver' in window && 'animate' in Element.prototype) {
      reveals = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (!entry.isIntersecting || reducedMotion.matches) return;
          reveals.unobserve(entry.target);
          const animation = entry.target.animate([
            { opacity: 0, transform: 'translateY(12px)' },
            { opacity: 1, transform: 'translateY(0)' },
          ], { duration: 350, easing: 'ease-out' });
          animations.add(animation);
          animation.finished.then(() => animations.delete(animation), () => animations.delete(animation));
        });
      }, { threshold: 0.15 });
      document.querySelectorAll('[data-home-reveal]').forEach(element => reveals.observe(element));
    }
    reducedMotion.addEventListener('change', () => {
      galleries.forEach(refresh => refresh());
      if (reducedMotion.matches) {
        reveals?.disconnect();
        animations.forEach(animation => animation.cancel());
        animations.clear();
      }
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
