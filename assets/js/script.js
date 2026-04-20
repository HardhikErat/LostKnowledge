/**
 * Lost Knowledge — script.js
 * Clean vanilla JS. No frameworks, no build tools.
 */

/* ── Helpers ─────────────────────────────────────── */
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

function esc(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── Mobile nav toggle ────────────────────────────── */
function initNav() {
  const toggle = $('.nav-toggle');
  const links  = $('.nav-links');
  if (!toggle || !links) return;

  toggle.addEventListener('click', () => {
    links.classList.toggle('open');
    toggle.setAttribute('aria-expanded', links.classList.contains('open'));
  });

  document.addEventListener('click', e => {
    if (!links.contains(e.target) && !toggle.contains(e.target)) {
      links.classList.remove('open');
    }
  });
}

/* ── Flash auto-dismiss ───────────────────────────── */
function initAlerts() {
  $$('.alert[data-autohide]').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .4s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 400);
    }, 4500);
  });
}

/* ── Counter animation ────────────────────────────── */
function animateCounter(el) {
  const target = parseInt(el.dataset.target, 10);
  if (isNaN(target)) return;
  const dur = 1400;
  const start = performance.now();
  function step(now) {
    const p = Math.min((now - start) / dur, 1);
    const eased = 1 - Math.pow(1 - p, 3);
    el.textContent = Math.round(eased * target).toLocaleString();
    if (p < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

function initCounters() {
  const els = $$('[data-target]');
  if (!els.length) return;

  const obs = new IntersectionObserver(entries => {
    entries.forEach(en => {
      if (en.isIntersecting) {
        animateCounter(en.target);
        obs.unobserve(en.target);
      }
    });
  }, { threshold: .5 });

  els.forEach(el => obs.observe(el));
}

/* ── Card scroll reveal ───────────────────────────── */
function initReveal() {
  const obs = new IntersectionObserver(entries => {
    entries.forEach((en, i) => {
      if (en.isIntersecting) {
        en.target.style.animationDelay = `${i * 0.05}s`;
        en.target.classList.add('anim-1');
        obs.unobserve(en.target);
      }
    });
  }, { threshold: 0.08 });

  $$('.entry-card, .cat-pill, .hero-mini-card').forEach(el => {
    if (!el.classList.contains('anim-1')) obs.observe(el);
  });
}

/* ── Phone number validation helper ───────────────── */
function isValidIndianPhone(val) {
  // Remove spaces, dashes, parens, plus
  const clean = val.replace(/[\s\-\(\)\+]/g, '');
  return /^[6-9]\d{9}$/.test(clean);
}

function isValidEmail(val) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
}

/**
 * Detect whether an input value looks like a phone number or an email.
 * Returns 'phone', 'email', or 'unknown'.
 */
function detectIdentifierType(val) {
  const cleaned = val.replace(/[\s\-\(\)\+]/g, '');
  // If it's all digits and 10 chars, treat as phone
  if (/^\d{10}$/.test(cleaned)) return 'phone';
  // If it contains @, treat as email
  if (val.includes('@')) return 'email';
  // If it's only digits (but not 10), still treat as phone attempt
  if (/^\d+$/.test(cleaned)) return 'phone';
  return 'email'; // default
}

/* ── Form validation ──────────────────────────────── */
function validateField(input, rules) {
  const val  = input.value.trim();
  const span = input.parentNode.querySelector('.field-error')
    || (() => {
      const s = document.createElement('span');
      s.className = 'field-error';
      input.parentNode.appendChild(s);
      return s;
    })();

  let err = '';
  if (rules.required && !val)                         err = 'This field is required.';
  else if (rules.min && val.length < rules.min)        err = `Minimum ${rules.min} characters.`;
  else if (rules.max && val.length > rules.max)        err = `Maximum ${rules.max} characters.`;
  else if (rules.email && !isValidEmail(val))          err = 'Enter a valid email address.';
  else if (rules.phone && !isValidIndianPhone(val))    err = 'Enter a valid 10-digit Indian mobile number (starts with 6–9).';
  else if (rules.emailOrPhone) {
    const type = detectIdentifierType(val);
    if (type === 'email' && !isValidEmail(val))        err = 'Enter a valid email address.';
    else if (type === 'phone' && !isValidIndianPhone(val)) err = 'Enter a valid 10-digit phone number (starts with 6–9).';
  }
  else if (rules.match) {
    const other = $(rules.match);
    if (other && val !== other.value.trim()) err = rules.matchMsg || 'Fields do not match.';
  } else if (rules.fn) err = rules.fn(val);

  if (err) {
    input.classList.add('error');
    span.textContent = err;
    return false;
  }
  input.classList.remove('error');
  span.textContent = '';
  return true;
}

function clearErrors(form) {
  $$('.error', form).forEach(el => el.classList.remove('error'));
  $$('.field-error', form).forEach(el => { el.textContent = ''; });
}

function liveValidate(input, rules) {
  input.addEventListener('blur', () => validateField(input, rules));
  input.addEventListener('input', () => { if (input.classList.contains('error')) validateField(input, rules); });
}

/* ── Register form ────────────────────────────────── */
function initRegisterForm() {
  const form = $('#registerForm');
  if (!form) return;

  const fields = [
    { el: form.querySelector('[name=username]'),         rules: { required:true, min:3, max:50, fn: v => /^[a-zA-Z0-9_]+$/.test(v) ? '' : 'Only letters, numbers, underscores.' } },
    { el: form.querySelector('[name=email]'),            rules: { required:true, email:true } },
    { el: form.querySelector('[name=phone]'),            rules: { required:true, phone:true } },
    { el: form.querySelector('[name=password]'),         rules: { required:true, min:8, fn: v => /[A-Z]/.test(v) && /[0-9]/.test(v) ? '' : 'Need one uppercase and one number.' } },
    { el: form.querySelector('[name=password_confirm]'), rules: { required:true, match:'#password', matchMsg:'Passwords do not match.' } },
  ];

  fields.forEach(f => { if (f.el) liveValidate(f.el, f.rules); });

  form.addEventListener('submit', e => {
    clearErrors(form);
    const ok = fields.every(f => !f.el || validateField(f.el, f.rules));
    if (!ok) { e.preventDefault(); form.querySelector('.error')?.focus(); }
  });
}

/* ── Login form ───────────────────────────────────── */
function initLoginForm() {
  const form = $('#loginForm');
  if (!form) return;

  const identifier = form.querySelector('[name=identifier]');
  const pass        = form.querySelector('[name=password]');

  // Live validation for identifier
  if (identifier) {
    liveValidate(identifier, { required:true, emailOrPhone:true });
  }

  form.addEventListener('submit', e => {
    clearErrors(form);
    let ok = true;
    if (identifier) ok = validateField(identifier, { required:true, emailOrPhone:true }) & ok;
    if (pass)       ok = validateField(pass,       { required:true }) & ok;
    if (!ok) e.preventDefault();
  });
}

/* ── Knowledge submit form ────────────────────────── */
function initKnowledgeForm() {
  const form = $('#knowledgeForm');
  if (!form) return;

  const fields = [
    { el: form.querySelector('[name=title]'),   rules: { required:true, min:5, max:200 } },
    { el: form.querySelector('[name=summary]'), rules: { required:true, min:10, max:400 } },
    { el: form.querySelector('[name=body]'),    rules: { required:true, min:30 } },
  ];

  // Character counters for capped fields
  fields.forEach(({ el, rules }) => {
    if (!el || !rules.max) return;
    const counter = document.createElement('small');
    counter.style.cssText = 'display:block;text-align:right;margin-top:.2rem';
    el.parentNode.appendChild(counter);
    const update = () => {
      const left = rules.max - el.value.length;
      counter.textContent = `${el.value.length}/${rules.max}`;
      counter.style.color = left < 20 ? 'var(--red-accent)' : 'var(--ink-300)';
    };
    update();
    el.addEventListener('input', update);
  });

  fields.forEach(f => { if (f.el) liveValidate(f.el, f.rules); });

  form.addEventListener('submit', e => {
    clearErrors(form);
    const ok = fields.every(f => !f.el || validateField(f.el, f.rules));
    if (!ok) {
      e.preventDefault();
      form.querySelector('.error')?.scrollIntoView({ behavior:'smooth', block:'center' });
    }
  });
}

/* ── Load entries via REST API ────────────────────── */
function initEntryList() {
  const grid = $('#entriesGrid');
  if (!grid) return;

  let page = 1;
  let category = new URLSearchParams(location.search).get('cat') || '';
  let search = new URLSearchParams(location.search).get('search') || '';
  let debounce;

  const searchInput = $('#searchInput');
  const catSelect   = $('#catSelect');

  // Pre-fill from URL
  if (category && catSelect) catSelect.value = category;
  if (search && searchInput) searchInput.value = search;

  if (searchInput) {
    searchInput.addEventListener('input', () => {
      clearTimeout(debounce);
      debounce = setTimeout(() => { search = searchInput.value.trim(); page = 1; load(); }, 350);
    });
  }

  if (catSelect) {
    catSelect.addEventListener('change', () => { category = catSelect.value; page = 1; load(); });
  }

  window.changePage = n => { page = n; load(); window.scrollTo({top:0, behavior:'smooth'}); };

  function load() {
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:3rem"><div class="spinner"></div></div>';
    const params = new URLSearchParams({ page, per_page:9, status:'approved' });
    if (category) params.set('category', category);
    if (search)   params.set('search', search);
    // Sort from features.js
    if (window.__lkSort && window.__lkSort !== 'newest') params.set('sort', window.__lkSort);
    // Tag from URL
    const urlTag = new URLSearchParams(location.search).get('tag');
    if (urlTag) params.set('tag', urlTag);

    fetch(`/api/knowledge.php?${params}`)
      .then(r => r.json())
      .then(data => {
        if (!data.success) throw new Error(data.error || 'Unknown error');
        renderGrid(data.data, data.meta);
      })
      .catch(err => {
        grid.innerHTML = `
          <div class="empty-state" style="grid-column:1/-1">
            <div class="empty-icon">📭</div>
            <h3>Couldn't load entries</h3>
            <p>${esc(err.message)}</p>
          </div>`;
      });
  }

  function renderGrid(entries, meta) {
    if (!entries?.length) {
      grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1">
        <div class="empty-icon">🔍</div>
        <h3>Nothing found</h3>
        <p>Try a different search or category.</p>
      </div>`;
      $('#pagination').innerHTML = '';
      return;
    }

    grid.innerHTML = entries.map(e => `
      <article class="entry-card" onclick="window.location.href='/entry.php?id=${e.id}'" role="button" tabindex="0">
        ${e.image_path ? `<img src="${esc(e.image_path)}" alt="" class="ec-image">` : ''}
        <div class="ec-top">
          <span class="ec-cat">${esc(e.category_name || 'General')}</span>
        </div>
        <h3 class="ec-title">${esc(e.title)}</h3>
        <p class="ec-summary">${esc(e.summary)}</p>
        <div class="ec-foot">
          <div class="ec-meta">
            <span>${esc(e.username || 'Anonymous')}</span>
            ${e.region ? `<span class="ec-meta-sep">·</span><span>${esc(e.region)}</span>` : ''}
            <span class="ec-meta-sep">·</span>
            <span class="view-count">👁️ ${e.views || 0}</span>
            ${e.comment_count ? `<span class="ec-meta-sep">·</span><span>💬 ${e.comment_count}</span>` : ''}
          </div>
          <div class="ec-votes">
            <button class="vote-btn ${e.user_vote==='up' ? 'up-active' : ''}"
              onclick="event.stopPropagation();castVote(${e.id},'up',this)">
              ▲ <span>${e.votes_up||0}</span>
            </button>
            <button class="vote-btn ${e.user_vote==='down' ? 'down-active' : ''}"
              onclick="event.stopPropagation();castVote(${e.id},'down',this)">
              ▼ <span>${e.votes_down||0}</span>
            </button>
          </div>
        </div>
      </article>`).join('');

    renderPagination(meta);
    initReveal();
  }

  function renderPagination(meta) {
    const el = $('#pagination');
    if (!el || !meta || meta.total_pages <= 1) { if (el) el.innerHTML = ''; return; }

    const { current_page: cur, total_pages: tot } = meta;
    let html = `<button class="page-btn" onclick="changePage(${cur-1})" ${cur===1?'disabled':''}>← Prev</button>`;

    for (let i=1; i<=tot; i++) {
      if (i===1||i===tot||(i>=cur-1&&i<=cur+1)) {
        html += `<button class="page-btn ${i===cur?'current':''}" onclick="changePage(${i})">${i}</button>`;
      } else if (i===cur-2||i===cur+2) {
        html += `<span class="page-btn" style="pointer-events:none;opacity:.4">…</span>`;
      }
    }

    html += `<button class="page-btn" onclick="changePage(${cur+1})" ${cur===tot?'disabled':''}>Next →</button>`;
    el.innerHTML = html;
  }

  load();
}

/* ── Voting ───────────────────────────────────────── */
window.castVote = function(entryId, type, btn) {
  fetch('/api/knowledge.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'vote', entry_id:entryId, vote_type:type})
  })
  .then(r => r.json())
  .then(data => {
    if (!data.success) {
      if (data.error === 'unauthenticated') window.location.href = '/login.html?ref=vote';
      return;
    }
    const card = btn.closest('.entry-card, .vote-panel, .entry-detail-wrap');
    if (!card) return;
    const upBtn   = card.querySelector('.vote-btn:first-of-type');
    const downBtn = card.querySelector('.vote-btn:last-of-type');
    if (upBtn) {
      upBtn.classList.toggle('up-active', data.user_vote === 'up');
      const sp = upBtn.querySelector('span'); if (sp) sp.textContent = data.votes_up;
    }
    if (downBtn) {
      downBtn.classList.toggle('down-active', data.user_vote === 'down');
      const sp = downBtn.querySelector('span'); if (sp) sp.textContent = data.votes_down;
    }
  })
  .catch(() => {});
};

/* ── Confirm dangerous actions ────────────────────── */
function initConfirm() {
  $$('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });
}

/* ── Load site stats into hero ────────────────────── */
function loadStats() {
  fetch('/api/knowledge.php?action=stats')
    .then(r => r.json())
    .then(data => {
      if (data.success && data.stats) {
        const map = {
          'statEntries': data.stats.entries,
          'statUsers':   data.stats.users,
          'statVotes':   data.stats.votes,
        };
        Object.entries(map).forEach(([id, val]) => {
          const el = document.getElementById(id);
          if (el) el.dataset.target = val;
        });
      }
    })
    .catch(() => {});
}

/* ── Floating Chat FAB (injected on every page) ──── */
function initChatFab() {
  // Don't show on the chatbot page itself
  if (window.location.pathname.includes('chatbot.html')) return;

  const fab = document.createElement('a');
  fab.href = '/chatbot.html';
  fab.className = 'chat-fab';
  fab.title = 'Need help? Chat with our bot';
  fab.setAttribute('aria-label', 'Open Help Bot');
  fab.innerHTML = '💬';
  document.body.appendChild(fab);
}

/* ── Dynamic Auth-Aware Nav (for static .html pages) ─ */
function initAuthNav() {
  fetch('/api/auth_check.php')
    .then(r => r.json())
    .then(data => {
      const nav = $('.nav-links');
      if (!nav) return;

      if (data.logged_in) {
        // Remove Register & Sign In links, add Dashboard & Sign Out
        $$('.nav-link', nav).forEach(link => {
          const href = link.getAttribute('href') || '';
          if (href.includes('register.html') || href.includes('login.html')) {
            link.remove();
          }
        });
        // Remove nav-sep if present (no longer needed before auth links)
        const sep = nav.querySelector('.nav-sep');

        // Only add Dashboard/Sign Out if not already present
        if (!nav.querySelector('[href*="dashboard.php"]')) {
          const dashLink = document.createElement('a');
          dashLink.href = '/dashboard.php';
          dashLink.className = 'nav-link';
          dashLink.textContent = 'Dashboard';
          if (sep) sep.after(dashLink);
          else nav.appendChild(dashLink);
        }
        if (!nav.querySelector('[href*="logout.php"]')) {
          const outLink = document.createElement('a');
          outLink.href = '/logout.php';
          outLink.className = 'nav-link';
          outLink.textContent = 'Sign Out';
          // Insert before the Submit Entry CTA if it exists
          const cta = nav.querySelector('.nav-cta');
          if (cta) nav.insertBefore(outLink, cta);
          else nav.appendChild(outLink);
        }
      }
    })
    .catch(() => {}); // silently fail for pages that don't need it
}

/* ── Init ─────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  initNav();
  initAlerts();
  initCounters();
  initReveal();
  initRegisterForm();
  initLoginForm();
  initKnowledgeForm();
  initEntryList();
  initConfirm();
  loadStats();
  initChatFab();
  initAuthNav();
});
