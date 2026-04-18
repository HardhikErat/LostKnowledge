/**
 * Lost Knowledge — features.js
 * New features: dark mode, toasts, back-to-top, notifications,
 * tags, sort, view counter, PDF export, image upload, Leaflet map
 */

/* ══════════════════════════════════════════════════════════════
   1. DARK MODE TOGGLE
   ══════════════════════════════════════════════════════════════ */
function initDarkMode() {
  const saved = localStorage.getItem('lk-theme') || 'dark';
  document.documentElement.setAttribute('data-theme', saved);

  // Inject toggle button into nav
  const nav = document.querySelector('.nav-links');
  if (!nav) return;

  const btn = document.createElement('button');
  btn.className = 'theme-toggle';
  btn.title = 'Toggle light/dark mode';
  btn.innerHTML = saved === 'dark' ? '☀️' : '🌙';
  btn.addEventListener('click', () => {
    const cur = document.documentElement.getAttribute('data-theme');
    const next = cur === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('lk-theme', next);
    btn.innerHTML = next === 'dark' ? '☀️' : '🌙';
  });

  // Insert before first nav link
  const first = nav.firstElementChild;
  if (first) nav.insertBefore(btn, first);
}

/* ══════════════════════════════════════════════════════════════
   2. TOAST NOTIFICATIONS
   ══════════════════════════════════════════════════════════════ */
function getToastContainer() {
  let c = document.querySelector('.toast-container');
  if (!c) {
    c = document.createElement('div');
    c.className = 'toast-container';
    document.body.appendChild(c);
  }
  return c;
}

function showToast(message, type = 'info', duration = 4000) {
  const icons = { success: '✅', error: '❌', info: '✦' };
  const container = getToastContainer();

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `
    <span class="toast-icon">${icons[type] || '✦'}</span>
    <span class="toast-body">${message}</span>
    <button class="toast-close" onclick="this.parentElement.classList.add('toast-out');setTimeout(()=>this.parentElement.remove(),300)">×</button>
  `;
  container.appendChild(toast);

  setTimeout(() => {
    if (toast.parentElement) {
      toast.classList.add('toast-out');
      setTimeout(() => toast.remove(), 300);
    }
  }, duration);
}

// Make global
window.showToast = showToast;

/* ══════════════════════════════════════════════════════════════
   3. BACK-TO-TOP BUTTON
   ══════════════════════════════════════════════════════════════ */
function initBackToTop() {
  const btn = document.createElement('button');
  btn.className = 'back-to-top';
  btn.innerHTML = '↑';
  btn.title = 'Back to top';
  btn.setAttribute('aria-label', 'Scroll to top');
  document.body.appendChild(btn);

  window.addEventListener('scroll', () => {
    btn.classList.toggle('visible', window.scrollY > 400);
  }, { passive: true });

  btn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
}

/* ══════════════════════════════════════════════════════════════
   4. SORT CONTROLS (archive page)
   ══════════════════════════════════════════════════════════════ */
function initSortControls() {
  const filterBar = document.querySelector('.filter-bar');
  if (!filterBar) return;

  // Create sort bar after filter bar
  const sortBar = document.createElement('div');
  sortBar.className = 'sort-bar';
  sortBar.innerHTML = `
    <span class="sort-label">Sort by:</span>
    <button class="sort-btn active" data-sort="newest">Newest</button>
    <button class="sort-btn" data-sort="oldest">Oldest</button>
    <button class="sort-btn" data-sort="most_votes">Most Votes</button>
    <button class="sort-btn" data-sort="most_comments">Most Discussed</button>
    <button class="sort-btn" data-sort="most_views">Most Viewed</button>
  `;
  filterBar.parentNode.insertBefore(sortBar, filterBar.nextSibling);

  // Store current sort
  window.__lkSort = 'newest';

  sortBar.addEventListener('click', (e) => {
    const btn = e.target.closest('.sort-btn');
    if (!btn) return;
    sortBar.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    window.__lkSort = btn.dataset.sort;
    // Trigger reload
    const catSelect = document.getElementById('catSelect');
    if (catSelect) catSelect.dispatchEvent(new Event('change'));
  });
}

/* ══════════════════════════════════════════════════════════════
   5. NOTIFICATION BELL
   ══════════════════════════════════════════════════════════════ */
function initNotificationBell() {
  const nav = document.querySelector('.nav-links');
  if (!nav) return;
  // Only show if logged in (check for sign-out link)
  if (!nav.querySelector('[href*="logout"]')) return;

  const wrap = document.createElement('div');
  wrap.style.cssText = 'position:relative;display:inline-flex';
  wrap.innerHTML = `
    <button class="notif-bell" id="notifBell" title="Notifications">🔔<span class="notif-badge" id="notifBadge" style="display:none">0</span></button>
    <div class="notif-dropdown" id="notifDrop">
      <div class="notif-header">
        <h4>Notifications</h4>
        <button class="notif-mark-read" onclick="markAllRead()">Mark all read</button>
      </div>
      <div id="notifList"><div class="notif-empty">No notifications yet</div></div>
    </div>
  `;

  // Insert before logout link
  const logoutLink = nav.querySelector('[href*="logout"]');
  if (logoutLink) nav.insertBefore(wrap, logoutLink);
  else nav.appendChild(wrap);

  const bell = document.getElementById('notifBell');
  const drop = document.getElementById('notifDrop');

  bell.addEventListener('click', (e) => {
    e.stopPropagation();
    drop.classList.toggle('open');
    if (drop.classList.contains('open')) loadNotifications();
  });

  document.addEventListener('click', (e) => {
    if (!wrap.contains(e.target)) drop.classList.remove('open');
  });

  // Load unread count
  loadNotifCount();
  setInterval(loadNotifCount, 60000);
}

function loadNotifCount() {
  fetch('/lost-knowledge/api/notifications.php?action=count')
    .then(r => r.json())
    .then(d => {
      const badge = document.getElementById('notifBadge');
      if (!badge) return;
      if (d.count > 0) {
        badge.textContent = d.count > 99 ? '99+' : d.count;
        badge.style.display = '';
      } else {
        badge.style.display = 'none';
      }
    }).catch(() => {});
}

function loadNotifications() {
  const list = document.getElementById('notifList');
  if (!list) return;

  fetch('/lost-knowledge/api/notifications.php?action=list')
    .then(r => r.json())
    .then(d => {
      if (!d.success || !d.data?.length) {
        list.innerHTML = '<div class="notif-empty">✦ No notifications yet</div>';
        return;
      }
      const icons = { entry_approved: '✅', entry_rejected: '❌', new_comment: '💬', vote_milestone: '⭐' };
      list.innerHTML = d.data.map(n => `
        <a class="notif-item ${n.is_read ? '' : 'unread'}" href="${n.link || '#'}" onclick="markRead(${n.id})">
          <div class="notif-item-icon">${icons[n.type] || '✦'}</div>
          <div class="notif-item-body">
            <div class="notif-item-text">${n.message}</div>
            <div class="notif-item-time">${timeAgo(n.created_at)}</div>
          </div>
        </a>
      `).join('');
    }).catch(() => {});
}

window.markRead = function(id) {
  fetch('/lost-knowledge/api/notifications.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'read', id })
  }).then(() => loadNotifCount());
};

window.markAllRead = function() {
  fetch('/lost-knowledge/api/notifications.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'read_all' })
  }).then(() => {
    loadNotifCount();
    loadNotifications();
    showToast('All notifications marked as read', 'success');
  });
};

function timeAgo(dateStr) {
  const now = new Date();
  const d = new Date(dateStr);
  const diff = Math.floor((now - d) / 1000);
  if (diff < 60) return 'Just now';
  if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
  if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
  return d.toLocaleDateString();
}

/* ══════════════════════════════════════════════════════════════
   6. TAG INPUT COMPONENT
   ══════════════════════════════════════════════════════════════ */
function initTagInput() {
  const wrap = document.getElementById('tagInputWrap');
  if (!wrap) return;

  const input = wrap.querySelector('.tag-input');
  const hidden = document.getElementById('tagsHidden');
  let tags = [];

  function renderTags() {
    wrap.querySelectorAll('.tag-pill').forEach(t => t.remove());
    tags.forEach((tag, i) => {
      const pill = document.createElement('span');
      pill.className = 'tag-pill';
      pill.innerHTML = `#${tag} <button class="tag-remove" type="button" data-idx="${i}">×</button>`;
      wrap.insertBefore(pill, input);
    });
    if (hidden) hidden.value = tags.join(',');
  }

  function addTag(val) {
    val = val.trim().toLowerCase().replace(/[^a-z0-9\-_ ]/g, '').replace(/\s+/g, '-');
    if (!val || val.length < 2 || tags.includes(val) || tags.length >= 8) return;
    tags.push(val);
    renderTags();
    input.value = '';
  }

  input.addEventListener('keydown', (e) => {
    if ((e.key === 'Enter' || e.key === ',') && input.value.trim()) {
      e.preventDefault();
      addTag(input.value);
    }
    if (e.key === 'Backspace' && !input.value && tags.length) {
      tags.pop();
      renderTags();
    }
  });

  wrap.addEventListener('click', (e) => {
    const rm = e.target.closest('.tag-remove');
    if (rm) { tags.splice(parseInt(rm.dataset.idx), 1); renderTags(); }
    input.focus();
  });
}

/* ══════════════════════════════════════════════════════════════
   7. IMAGE UPLOAD WITH PREVIEW
   ══════════════════════════════════════════════════════════════ */
function initImageUpload() {
  const zone = document.getElementById('imageUploadZone');
  const fileInput = document.getElementById('entryImage');
  const preview = document.getElementById('imagePreview');
  if (!zone || !fileInput) return;

  zone.addEventListener('click', () => fileInput.click());

  zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('dragover'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
  zone.addEventListener('drop', (e) => {
    e.preventDefault();
    zone.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
      fileInput.files = e.dataTransfer.files;
      previewImage(e.dataTransfer.files[0]);
    }
  });

  fileInput.addEventListener('change', () => {
    if (fileInput.files.length) previewImage(fileInput.files[0]);
  });

  function previewImage(file) {
    if (!file.type.startsWith('image/')) { showToast('Please select an image file', 'error'); return; }
    if (file.size > 5 * 1024 * 1024) { showToast('Image must be under 5MB', 'error'); return; }
    const reader = new FileReader();
    reader.onload = (e) => {
      if (preview) {
        preview.src = e.target.result;
        preview.style.display = 'block';
      }
      zone.querySelector('.image-upload-text').textContent = file.name;
    };
    reader.readAsDataURL(file);
  }
}

/* ══════════════════════════════════════════════════════════════
   8. PDF EXPORT
   ══════════════════════════════════════════════════════════════ */
function initPdfExport() {
  const btn = document.getElementById('pdfExportBtn');
  if (!btn) return;

  btn.addEventListener('click', async () => {
    btn.disabled = true;
    btn.textContent = '⏳ Generating…';

    try {
      // Dynamic import html2pdf
      if (!window.html2pdf) {
        const s = document.createElement('script');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
        document.head.appendChild(s);
        await new Promise((r, j) => { s.onload = r; s.onerror = j; });
      }

      const el = document.querySelector('.entry-detail-wrap');
      if (!el) return;

      const title = document.querySelector('.page-header h1')?.textContent || 'entry';
      const opt = {
        margin: [10, 10, 10, 10],
        filename: `lost-knowledge-${title.replace(/\s+/g, '-').toLowerCase()}.pdf`,
        image: { type: 'jpeg', quality: 0.95 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
      };

      await html2pdf().set(opt).from(el).save();
      showToast('PDF downloaded successfully', 'success');
    } catch (e) {
      showToast('Failed to generate PDF', 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = '📄 Export PDF';
    }
  });
}

/* ══════════════════════════════════════════════════════════════
   9. RICH TEXT EDITOR (Quill.js)
   ══════════════════════════════════════════════════════════════ */
function initQuillEditor() {
  const textarea = document.getElementById('body');
  if (!textarea || !textarea.closest('#knowledgeForm')) return;

  // Load Quill CSS
  const link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = 'https://cdn.quilljs.com/1.3.7/quill.snow.css';
  document.head.appendChild(link);

  // Load Quill JS
  const script = document.createElement('script');
  script.src = 'https://cdn.quilljs.com/1.3.7/quill.min.js';
  document.head.appendChild(script);

  script.onload = () => {
    // Create editor container
    const editorDiv = document.createElement('div');
    editorDiv.id = 'quillEditor';
    textarea.parentNode.insertBefore(editorDiv, textarea);
    textarea.style.display = 'none';

    const quill = new Quill('#quillEditor', {
      theme: 'snow',
      placeholder: 'Describe the tradition — how it was practiced, why it disappeared…',
      modules: {
        toolbar: [
          [{ 'header': [2, 3, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ 'list': 'ordered' }, { 'list': 'bullet' }],
          ['blockquote', 'link'],
          ['clean']
        ]
      }
    });

    // Set initial content
    if (textarea.value) quill.root.innerHTML = textarea.value;

    // Sync to textarea on change
    quill.on('text-change', () => {
      textarea.value = quill.root.innerHTML;
    });

    // Sync before form submit
    const form = textarea.closest('form');
    if (form) {
      form.addEventListener('submit', () => {
        textarea.value = quill.root.innerHTML;
      });
    }
  };
}

/* ══════════════════════════════════════════════════════════════
   10. INTERACTIVE LEAFLET MAP
   ══════════════════════════════════════════════════════════════ */
function initLeafletMap() {
  const container = document.getElementById('leafletMap');
  if (!container) return;

  // Load Leaflet CSS
  const link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
  document.head.appendChild(link);

  // Load Leaflet JS
  const script = document.createElement('script');
  script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
  document.head.appendChild(script);

  script.onload = () => {
    const map = L.map('leafletMap', {
      zoomControl: true,
      scrollWheelZoom: true
    }).setView([20, 30], 2);

    // Dark tile layer
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
      attribution: '© OpenStreetMap © CARTO',
      subdomains: 'abcd',
      maxZoom: 19
    }).addTo(map);

    // Custom gold marker
    const goldIcon = L.divIcon({
      className: '',
      html: '<div style="width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,#B8772A,#D89748);border:2px solid #F9EBD5;box-shadow:0 2px 8px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;font-size:10px;color:#1A1208;font-weight:700">✦</div>',
      iconSize: [24, 24],
      iconAnchor: [12, 12],
      popupAnchor: [0, -14]
    });

    // Region coordinates for mapping
    const regionCoords = {
      'north america': [40, -100], 'south america': [-15, -60], 'europe': [50, 15],
      'africa': [0, 25], 'middle east': [30, 45], 'asia': [35, 80],
      'south asia': [22, 78], 'east asia': [35, 110], 'southeast asia': [5, 110],
      'oceania': [-25, 135], 'pacific': [-15, -170], 'mediterranean': [38, 18],
      'central america': [15, -87], 'northern anatolia': [40, 35], 'andes': [-20, -65],
      'marshall islands': [7, 171], 'polynesia': [-15, -150], 'india': [22, 78],
      'china': [35, 105], 'japan': [36, 138], 'australia': [-25, 135],
      'egypt': [26, 30], 'mesopotamia': [33, 44], 'persia': [32, 53],
      'greece': [39, 22], 'rome': [42, 12], 'scandinavia': [62, 15],
      'siberia': [60, 100], 'mongolia': [47, 105], 'tibet': [30, 90],
      'indonesia': [-5, 120], 'philippines': [12, 122], 'korea': [37, 127],
      'brazil': [-14, -51], 'mexico': [23, -102], 'peru': [-10, -76],
      'nigeria': [9, 8], 'ethiopia': [9, 39], 'kenya': [0, 38],
      'morocco': [32, -6], 'mali': [17, -2], 'madagascar': [-19, 47],
      'iran': [32, 53], 'iraq': [33, 44], 'syria': [35, 38],
      'turkey': [39, 35], 'russia': [60, 100], 'ukraine': [49, 32],
      'scotland': [57, -4], 'ireland': [53, -8], 'wales': [52, -3],
      'france': [46, 2], 'spain': [40, -4], 'italy': [42, 12],
      'germany': [51, 10], 'poland': [52, 20], 'norway': [62, 10]
    };

    function findCoords(region) {
      if (!region) return null;
      const lower = region.toLowerCase();
      // Exact match
      if (regionCoords[lower]) return regionCoords[lower];
      // Partial match
      for (const [key, coords] of Object.entries(regionCoords)) {
        if (lower.includes(key) || key.includes(lower)) return coords;
      }
      return null;
    }

    // Load entries and plot
    fetch('/lost-knowledge/api/knowledge.php?per_page=50&status=approved')
      .then(r => r.json())
      .then(data => {
        if (!data.success || !data.data) return;

        const markers = L.markerClusterGroup ? L.markerClusterGroup() : L.layerGroup();

        data.data.forEach(e => {
          const coords = findCoords(e.region);
          if (!coords) return;

          // Add slight randomness to prevent overlap
          const lat = coords[0] + (Math.random() - 0.5) * 3;
          const lng = coords[1] + (Math.random() - 0.5) * 3;

          const marker = L.marker([lat, lng], { icon: goldIcon });
          marker.bindPopup(`
            <div class="map-popup-title">${(e.title || '').replace(/</g, '&lt;')}</div>
            <div class="map-popup-cat">${(e.category_name || 'General').replace(/</g, '&lt;')} · ${(e.region || '').replace(/</g, '&lt;')}</div>
            <div class="map-popup-summary">${(e.summary || '').replace(/</g, '&lt;').substring(0, 120)}…</div>
            <a href="/lost-knowledge/entry.php?id=${e.id}" style="display:inline-block;margin-top:8px;font-size:12px;color:#D89748;font-weight:500">Read more →</a>
          `);
          markers.addLayer ? markers.addLayer(marker) : markers.addLayer(marker);
        });

        map.addLayer(markers);

        // Update count display
        const countEl = document.getElementById('mapEntryCount');
        if (countEl) countEl.textContent = data.data.filter(e => findCoords(e.region)).length;
      })
      .catch(() => {});
  };
}

/* ══════════════════════════════════════════════════════════════
   11. AVATAR PREVIEW ON PROFILE EDIT
   ══════════════════════════════════════════════════════════════ */
function initAvatarUpload() {
  const fileInput = document.getElementById('avatarFile');
  const preview = document.getElementById('avatarPreview');
  if (!fileInput || !preview) return;

  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) { showToast('Please select an image', 'error'); return; }
    if (file.size > 2 * 1024 * 1024) { showToast('Avatar must be under 2MB', 'error'); return; }
    const reader = new FileReader();
    reader.onload = (e) => { preview.src = e.target.result; };
    reader.readAsDataURL(file);
  });
}

/* ══════════════════════════════════════════════════════════════
   INIT
   ══════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  initDarkMode();
  initBackToTop();
  initSortControls();
  initNotificationBell();
  initTagInput();
  initImageUpload();
  initPdfExport();
  initQuillEditor();
  initLeafletMap();
  initAvatarUpload();
});
