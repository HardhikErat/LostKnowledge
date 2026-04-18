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
  else if (rules.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) err = 'Enter a valid email address.';
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

  const email = form.querySelector('[name=email]');
  const pass  = form.querySelector('[name=password]');

  form.addEventListener('submit', e => {
    clearErrors(form);
    const ok = validateField(email, {required:true, email:true})
             & validateField(pass,  {required:true});
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
  let search = '';
  let debounce;

  const searchInput = $('#searchInput');
  const catSelect   = $('#catSelect');

  // Pre-fill from URL
  if (category && catSelect) catSelect.value = category;

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

    fetch(`/lost-knowledge/api/knowledge.php?${params}`)
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
      <article class="entry-card" onclick="window.location='/lost-knowledge/entry.php?id=${e.id}'" role="button" tabindex="0">
        <div class="ec-top">
          <span class="ec-cat">${esc(e.category_name || 'General')}</span>
        </div>
        <h3 class="ec-title">${esc(e.title)}</h3>
        <p class="ec-summary">${esc(e.summary)}</p>
        <div class="ec-foot">
          <div class="ec-meta">
            <span>${esc(e.username || 'Anonymous')}</span>
            ${e.region ? `<span class="ec-meta-sep">·</span><span>${esc(e.region)}</span>` : ''}
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
  fetch('/lost-knowledge/api/knowledge.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'vote', entry_id:entryId, vote_type:type})
  })
  .then(r => r.json())
  .then(data => {
    if (!data.success) {
      if (data.error === 'unauthenticated') window.location.href = '/lost-knowledge/login.php?ref=vote';
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
  fetch('/lost-knowledge/api/knowledge.php?action=stats')
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
});
