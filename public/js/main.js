/* ============================================================
   LFS — Lusaka Fitness Squad | main.js
   Global JavaScript — nav, scroll, cart, forms, animations.
   ============================================================ */

'use strict';

/* ─────────────────────────────────────────────────────────────
   1. CONSTANTS & STATE
───────────────────────────────────────────────────────────── */
const LFS = {
  cart: {
    items: [],
    count: 0,
  },
  state: {
    navOpen: false,
    scrolled: false,
  },
};

/* ─────────────────────────────────────────────────────────────
   2. UTILITY HELPERS
───────────────────────────────────────────────────────────── */

/**
 * Shorthand querySelector
 * @param {string} selector
 * @param {Document|Element} [ctx=document]
 */
const $ = (selector, ctx = document) => ctx.querySelector(selector);

/**
 * Shorthand querySelectorAll — returns array
 * @param {string} selector
 * @param {Document|Element} [ctx=document]
 */
const $$ = (selector, ctx = document) => [...ctx.querySelectorAll(selector)];

/**
 * Show a toast notification
 * @param {string} message
 * @param {'default'|'red'|'orange'} [type='default']
 * @param {number} [duration=3000]
 */
function showToast(message, type = 'default', duration = 3000) {
  // Create if doesn't exist
  let toast = $('#lfs-toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'lfs-toast';
    toast.className = 'lfs-toast';
    document.body.appendChild(toast);
  }

  // Reset classes, set type
  toast.className = 'lfs-toast';
  if (type !== 'default') toast.classList.add(type);

  toast.textContent = message;
  // Trigger reflow then animate in
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      toast.classList.add('show');
    });
  });

  clearTimeout(toast._timeout);
  toast._timeout = setTimeout(() => {
    toast.classList.remove('show');
  }, duration);
}

/* ─────────────────────────────────────────────────────────────
   3. NAVIGATION
───────────────────────────────────────────────────────────── */
function initNav() {
  const nav         = $('.lfs-nav') || $('nav');
  const hamburger   = $('.lfs-nav__hamburger');
  const mobileMenu  = $('.lfs-nav__mobile');

  if (!nav) return;

  // Scroll shrink
  window.addEventListener('scroll', () => {
    const scrolled = window.scrollY > 60;
    if (scrolled !== LFS.state.scrolled) {
      LFS.state.scrolled = scrolled;
      nav.classList.toggle('scrolled', scrolled);
    }
  }, { passive: true });

  // Hamburger toggle
  if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', () => {
      LFS.state.navOpen = !LFS.state.navOpen;
      hamburger.classList.toggle('open', LFS.state.navOpen);
      mobileMenu.classList.toggle('open', LFS.state.navOpen);
    });
  }

  // Close mobile menu on link click
  $$('a', mobileMenu || document).forEach(link => {
    link.addEventListener('click', () => {
      if (LFS.state.navOpen) {
        LFS.state.navOpen = false;
        hamburger?.classList.remove('open');
        mobileMenu?.classList.remove('open');
      }
    });
  });

  // Smooth scroll for same-page anchor links
  $$('a[href*="#"]').forEach(link => {
    link.addEventListener('click', (e) => {
      const href = link.getAttribute('href') || '';
      const isSamePage = !href.startsWith('http') && (href.startsWith('#') || href.startsWith('/#'));
      const hashPart = href.split('#')[1];
      const target = hashPart ? document.getElementById(hashPart) : null;
      if (isSamePage && target) {
        e.preventDefault();
        const navHeight = nav.offsetHeight;
        const top = target.getBoundingClientRect().top + window.scrollY - navHeight;
        window.scrollTo({ top, behavior: 'smooth' });
      }
    });
  });
}

/* ─────────────────────────────────────────────────────────────
   4. SCROLL-REVEAL OBSERVER
───────────────────────────────────────────────────────────── */
function initScrollReveal() {
  const revealEls = $$('[data-reveal]');
  if (!revealEls.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('revealed');
        observer.unobserve(entry.target); // fire once
      }
    });
  }, { threshold: 0.12 });

  revealEls.forEach(el => observer.observe(el));
}

/* ─────────────────────────────────────────────────────────────
   5. CART
───────────────────────────────────────────────────────────── */
function initCart() {
  // FAB button
  const fab = $('.lfs-cart-fab');
  if (fab) {
    fab.addEventListener('click', viewCart);
  }

  // Wire up any existing add buttons with class .product-card__add
  $$('.product-card__add').forEach(btn => {
    btn.addEventListener('click', () => addToCart(btn));
  });
}

/**
 * Add an item to the cart
 * @param {HTMLElement} btn — the add button element
 */
function addToCart(btn) {
  // Get product name from nearest card
  const card  = btn.closest('.product-card') || btn.closest('[class*="product"]');
  const name  = card ? (card.querySelector('.product-card__name, .font-bold')?.textContent?.trim() ?? 'Item') : 'Item';
  const price = card ? (card.querySelector('.product-card__price, .font-[\'Bebas_Neue\']')?.textContent?.trim() ?? '') : '';

  LFS.cart.items.push({ name, price });
  LFS.cart.count++;

  // Update FAB badge
  const countEl = $('.lfs-cart-fab__count') ?? $('#cartCount');
  if (countEl) {
    countEl.textContent = LFS.cart.count;
    countEl.style.display = 'flex';
  }

  // Visual feedback on button
  const icon = btn.querySelector('i') || btn;
  const original = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-check"></i>';
  btn.classList.add('added');
  btn.disabled = true;

  setTimeout(() => {
    btn.innerHTML = original;
    btn.classList.remove('added');
    btn.disabled = false;
  }, 1200);

  showToast(`${name} added to cart`, 'default', 2500);
}

/**
 * Show cart summary
 */
function viewCart() {
  if (LFS.cart.count === 0) {
    showToast('Your cart is empty. Browse our official LFS regalia!', 'orange', 3000);
    return;
  }

  const itemLines = LFS.cart.items
    .reduce((acc, item) => {
      const existing = acc.find(i => i.name === item.name);
      if (existing) {
        existing.qty++;
      } else {
        acc.push({ name: item.name, price: item.price, qty: 1 });
      }
      return acc;
    }, [])
    .map(i => `• ${i.name} × ${i.qty}  ${i.price}`)
    .join('\n');

  alert(
    `🛒 LFS Cart (${LFS.cart.count} item${LFS.cart.count > 1 ? 's' : ''})\n\n` +
    `${itemLines}\n\n` +
    `To complete your purchase, contact:\n` +
    `LFS Treasurer: Mucha Dhlamini\n` +
    `📞 +260 962 333 651`
  );
}

/* ─────────────────────────────────────────────────────────────
   6. CONTACT FORM
───────────────────────────────────────────────────────────── */
function initContactForm() {
  const form     = $('.lfs-form:not([data-native-submit])') || $('section#contact form:not([data-native-submit])');
  const submitBtn = form
    ? form.querySelector('button[type="submit"], .btn-submit, button:last-of-type')
    : $('[onclick*="handleSubmit"]');

  if (submitBtn) {
    // Remove inline onclick if present
    submitBtn.removeAttribute('onclick');
    submitBtn.addEventListener('click', (e) => {
      e.preventDefault();
      handleSubmit(submitBtn, form);
    });
  }
}

/**
 * Handle contact form submit
 * @param {HTMLElement} btn
 * @param {HTMLElement|null} form
 */
function handleSubmit(btn, form = null) {
  // Simple validation
  const inputs = form
    ? $$('input, textarea, select', form).filter(el => el.required && !el.value.trim())
    : [];

  if (inputs.length) {
    inputs[0].focus();
    showToast('Please fill in all required fields.', 'red', 3000);
    return;
  }

  // Simulate send
  const originalHTML = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending…';
  btn.disabled = true;

  setTimeout(() => {
    btn.innerHTML = '<i class="fas fa-check mr-2"></i> Message Sent!';
    btn.style.background = 'var(--dark-green)';
    showToast('Your message has been sent. We\'ll be in touch!', 'default', 4000);

    setTimeout(() => {
      btn.innerHTML = originalHTML;
      btn.style.background = '';
      btn.disabled = false;
      if (form) form.reset();
    }, 2500);
  }, 1400);
}

/* ─────────────────────────────────────────────────────────────
   7. ACTIVE NAV LINK ON SCROLL
───────────────────────────────────────────────────────────── */
function initActiveLinks() {
  const sections = $$('section[id]');
  const navLinks = $$('.lfs-nav__links a, nav ul a');

  if (!sections.length || !navLinks.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const id = entry.target.id;
        navLinks.forEach(link => {
          const href = link.getAttribute('href');
          link.classList.toggle('active', href === `#${id}`);
          if (href === `#${id}`) {
            link.style.color = 'var(--green-bright)';
          } else {
            link.style.color = '';
          }
        });
      }
    });
  }, { threshold: 0.45 });

  sections.forEach(s => observer.observe(s));
}

/* ─────────────────────────────────────────────────────────────
   8. HERO COUNTER ANIMATION
───────────────────────────────────────────────────────────── */
function animateCounters() {
  const counters = $$('[data-count]');
  if (!counters.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const el     = entry.target;
      const target = parseInt(el.dataset.count, 10);
      const suffix = el.dataset.suffix ?? '';
      const duration = 1200;
      const start  = performance.now();

      const tick = (now) => {
        const progress = Math.min((now - start) / duration, 1);
        const ease = 1 - Math.pow(1 - progress, 3); // easeOutCubic
        el.textContent = Math.floor(ease * target) + suffix;
        if (progress < 1) requestAnimationFrame(tick);
      };

      requestAnimationFrame(tick);
      observer.unobserve(el);
    });
  }, { threshold: 0.5 });

  counters.forEach(el => observer.observe(el));
}

/* ─────────────────────────────────────────────────────────────
   9. FLAG STRIPE BUILDER (inject dynamically if needed)
───────────────────────────────────────────────────────────── */
function buildFlagStripes() {
  $$('.flag-stripe').forEach(stripe => {
    if (stripe.children.length) return; // already has children
    ['', '', '', ''].forEach(() => {
      const span = document.createElement('span');
      stripe.appendChild(span);
    });
  });
}

/* ─────────────────────────────────────────────────────────────
   10. KEYBOARD ACCESSIBILITY
───────────────────────────────────────────────────────────── */
function initA11y() {
  // Close mobile menu on Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && LFS.state.navOpen) {
      LFS.state.navOpen = false;
      $('.lfs-nav__hamburger')?.classList.remove('open');
      $('.lfs-nav__mobile')?.classList.remove('open');
    }
  });

  // Focus-visible polyfill — add keyboard nav class
  document.addEventListener('keydown', () => {
    document.body.classList.add('keyboard-nav');
  });
  document.addEventListener('mousedown', () => {
    document.body.classList.remove('keyboard-nav');
  });
}

/* ─────────────────────────────────────────────────────────────
   11. SATELLITE CARD RIPPLE (micro-interaction)
───────────────────────────────────────────────────────────── */
function initRipple() {
  $$('.satellite-card, .btn, .product-card__add').forEach(el => {
    el.addEventListener('click', function (e) {
      const rect   = this.getBoundingClientRect();
      const ripple = document.createElement('span');
      const size   = Math.max(rect.width, rect.height);
      const x = e.clientX - rect.left - size / 2;
      const y = e.clientY - rect.top  - size / 2;

      ripple.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        top: ${y}px;
        left: ${x}px;
        background: rgba(255,255,255,0.18);
        border-radius: 50%;
        pointer-events: none;
        transform: scale(0);
        animation: ripple-anim 0.5s ease-out forwards;
      `;

      // Inject keyframe if not present
      if (!document.getElementById('ripple-style')) {
        const style = document.createElement('style');
        style.id = 'ripple-style';
        style.textContent = `
          @keyframes ripple-anim {
            to { transform: scale(2.5); opacity: 0; }
          }
        `;
        document.head.appendChild(style);
      }

      // Card must be relative for ripple to work
      const pos = getComputedStyle(this).position;
      if (pos === 'static') this.style.position = 'relative';
      this.style.overflow = 'hidden';
      this.appendChild(ripple);
      ripple.addEventListener('animationend', () => ripple.remove());
    });
  });
}

/* ─────────────────────────────────────────────────────────────
   12. PUBLIC API (for inline HTML onclick compatibility)
───────────────────────────────────────────────────────────── */
window.addToCart    = (btn)       => addToCart(btn);
window.viewCart     = ()          => viewCart();
window.handleSubmit = (btn, form) => handleSubmit(btn, form);
window.showToast    = showToast;

/* ─────────────────────────────────────────────────────────────
   13. BOOT
───────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  initNav();
  initScrollReveal();
  initCart();
  initContactForm();
  initActiveLinks();
  animateCounters();
  buildFlagStripes();
  initA11y();

  // Ripple slight delay so DOM is painted
  requestAnimationFrame(initRipple);

  console.log(
    '%c LFS · Lusaka Fitness Squad %c We\'re In This Together ',
    'background:#1e3a2a;color:#7ecb93;font-weight:bold;padding:4px 8px;',
    'background:#4a7c59;color:#fff;padding:4px 8px;'
  );
});
