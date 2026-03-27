/* AutoTrack+ – dashboard controller (robust, event-delegated) */

(() => {
  const $  = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];
  const on = (type, sel, fn) => {
    document.addEventListener(type, e => {
      const el = e.target.closest(sel);
      if (el) fn(e, el);
    });
  };

  // ========== THEME ==========
  // We apply the theme in 3 ways to satisfy any CSS:
  // 1) html.dark
  // 2) body.dark
  // 3) [data-theme="dark"]
  const root = document.documentElement;
  const body = document.body;
  const themeBtn = document.getElementById('btn-theme');

  function applyTheme(mode) {
    const dark = mode === 'dark';

    // html/body classes
    root.classList.toggle('dark', dark);
    body.classList.toggle('dark', dark);

    // data attribute
    root.setAttribute('data-theme', dark ? 'dark' : 'light');
    body.setAttribute('data-theme', dark ? 'dark' : 'light');

    // persist
    localStorage.setItem('theme', dark ? 'dark' : 'light');

    // update icon text if your button uses a text/icon swap
    if (themeBtn) {
      themeBtn.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
      // If your icon is text-based, you can flip it here:
      // e.g., themeBtn.textContent = dark ? '☀️' : '🌙';
      // If you use <span data-icon="sun/moon">, flip a data attribute instead:
      themeBtn.dataset.icon = dark ? 'sun' : 'moon';
    }
  }

  // Initial theme: saved -> system -> light
  (() => {
    const saved = localStorage.getItem('theme');
    if (saved === 'dark' || saved === 'light') {
      applyTheme(saved);
    } else {
      const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      applyTheme(prefersDark ? 'dark' : 'light');
    }
  })();

  // Toggle on click
  if (themeBtn) {
    themeBtn.addEventListener('click', () => {
      const nowDark = !(root.classList.contains('dark') || body.classList.contains('dark'));
      applyTheme(nowDark ? 'dark' : 'light');
    });
  }

  // ========== NAV BUTTONS (History / Notifications) ==========
  const historyBtn = $('#btn-history');
  historyBtn && historyBtn.addEventListener('click', () => {
    toast('History is coming soon.');
  });

  const notifyBtn = $('#btn-notify');
  const notifyDot = $('#notify-dot');
  notifyBtn && notifyBtn.addEventListener('click', () => {
    notifyDot && notifyDot.setAttribute('hidden', '');
    dialog('Notifications', `
      <ul class="menu">
        <li>Honda Civic (2018) – service completed ✅</li>
        <li>Nissan Rogue (2014) – due in 12 days ⏳</li>
      </ul>
    `);
  });

  // show a demo unread dot once (so you can see the dot until clicked)
  setTimeout(() => notifyDot && notifyDot.removeAttribute('hidden'), 700);

  // ========== VEHICLE LIST & PANEL ==========
  let activeId = null;
  const panel = $('#vehicle-panel');
  const list  = $('#vehicle-list');

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  })[m]);

  const labelFor = (s) => s === 'ok' ? 'Completed' : s === 'expired' ? 'Expired' : 'Due Soon';
  const cryptoRand = () => (self.crypto?.randomUUID?.() || Math.random().toString(36).slice(2));

  const renderPanel = (v) => {
    if (!panel) return;

    if (!v) {
      panel.innerHTML = `<div class="empty"><p>Select a vehicle from the list. <span class="muted">Add your first repair ↑</span></p></div>`;
      return;
    }

    const next  = v.next_service || '—';
    const ins   = v.insurance_expiry || '—';
    const miles = v.mileage ?? 0;

    panel.innerHTML = `
      <div class="vehicle-detail">
        <header class="vd-head">
          <h2>${esc(v.make_model)} <span class="muted">(${esc(v.year)})</span></h2>
          <span class="badge">${esc(v.plate)}</span>
        </header>

        <div class="grid-3 gap">
          <div class="tile">
            <div class="label">Mileage</div>
            <div class="value">${Number(miles).toLocaleString()}</div>
          </div>
          <div class="tile">
            <div class="label">Next Service</div>
            <div class="value">${esc(next)}</div>
          </div>
          <div class="tile">
            <div class="label">Insurance Expiry</div>
            <div class="value">${esc(ins)}</div>
          </div>
        </div>

        <div class="spacer"></div>

        <div class="row">
          <input class="input flex" id="repair-input" placeholder="e.g., Brake pads" />
          <select class="input" id="repair-status">
            <option value="due">Due Soon</option>
            <option value="expired">Expired</option>
            <option value="ok">Completed</option>
          </select>
          <button class="btn" id="btn-add-repair">Add</button>
        </div>

        <div class="spacer xs"></div>

        <div class="pillbar" id="repair-filters">
          <button class="pill active" data-r="all">All</button>
          <button class="pill" data-r="due">Due Soon</button>
          <button class="pill" data-r="expired">Expired</button>
          <button class="pill" data-r="ok">Completed</button>
        </div>

        <ul class="repairs" id="repairs-list" aria-live="polite"></ul>
      </div>
    `;

    // in-memory repairs per vehicle (front-end only)
    if (!window.__repairs) window.__repairs = {};
    if (!window.__repairs[v.id]) window.__repairs[v.id] = [];

    // initial render
    renderRepairs();

    $('#btn-add-repair', panel).addEventListener('click', () => {
      const title = $('#repair-input', panel).value.trim();
      const state = $('#repair-status', panel).value;
      if (!title) return;
      window.__repairs[v.id].push({ id: cryptoRand(), title, state, at: new Date() });
      $('#repair-input', panel).value = '';
      renderRepairs();
      toast('Repair added.');
    });

    on('click', '#repair-filters .pill', (_e, el) => {
      $('#repair-filters .pill.active', panel)?.classList.remove('active');
      el.classList.add('active');
      renderRepairs();
    });

    on('click', '.repairs .btn-del', (_e, el) => {
      const rid = el.dataset.id;
      window.__repairs[v.id] = window.__repairs[v.id].filter(r => r.id !== rid);
      renderRepairs();
    });

    function renderRepairs() {
      const filter = $('#repair-filters .pill.active', panel)?.dataset.r ?? 'all';
      const items = window.__repairs[v.id].filter(r => filter === 'all' ? true : r.state === filter);
      const ul = $('#repairs-list', panel);
      if (!items.length) {
        ul.innerHTML = `<li class="muted">No repairs yet.</li>`;
        return;
      }
      ul.innerHTML = items.map(r => `
        <li class="repair ${r.state}">
          <span>${esc(r.title)}</span>
          <span class="muted small">${new Date(r.at).toLocaleString()}</span>
          <span class="badge tiny ${r.state}">${labelFor(r.state)}</span>
          <button class="btn tiny danger btn-del" data-id="${r.id}">Delete</button>
        </li>
      `).join('');
    }
  };

  // Select a card → render right panel
  on('click', '.vehicle-card', (_e, card) => {
    if (_e.target.closest('footer')) return; // ignore footer buttons
    const list = document.getElementById('vehicle-list');
    list?.querySelector('.vehicle-card.active')?.classList.remove('active');
    card.classList.add('active');
    activeId = +card.dataset.id;

    const v = (window.VEHICLES || []).find(x => +x.id === activeId) || {
      id: activeId,
      make_model: card.dataset.make,
      year: card.dataset.year,
      plate: card.dataset.plate,
      next_service: card.dataset.next,
      insurance_expiry: card.dataset.ins,
      mileage: +card.dataset.mileage || 0
    };
    renderPanel(v);
  });

  // Remove
  on('click', '.btn-remove', async (_e, btn) => {
    const id = +btn.dataset.id;
    if (!confirm('Remove this vehicle?')) return;
    try {
      const res = await fetch(`${BASE_URL}/actions/remove_vehicle.php`, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `id=${encodeURIComponent(id)}`
      });
      if (res.ok) {
        $(`.vehicle-card[data-id="${id}"]`)?.remove();
        if (activeId === id) { activeId = null; renderPanel(null); }
        toast('Vehicle removed.');
      } else {
        toast('Server rejected removal (check backend).', 'warn');
      }
    } catch {
      toast('Failed to contact server.', 'warn');
    }
  });

  // Details
  on('click', '.btn-details', (_e, btn) => {
    const id = +btn.dataset.id;
    location.href = `${BASE_URL}/public/employee/vehicle.php?id=${id}`;
  });

  // Pills filter
  on('click', '#filter-pills .pill', (_e, pill) => {
    $('#filter-pills .pill.active')?.classList.remove('active');
    pill.classList.add('active');
    const f = pill.dataset.filter;
    const q = $('#search')?.value.trim().toLowerCase() || '';
    filterList(f, q);
  });

  // Search
  $('#search')?.addEventListener('input', e => {
    const f = $('#filter-pills .pill.active')?.dataset.filter || 'all';
    filterList(f, e.target.value.trim().toLowerCase());
  });

  function filterList(filter, q = '') {
    const list = document.getElementById('vehicle-list');
    if (!list) return;
    $$('.vehicle-card', list).forEach(card => {
      const state = card.dataset.state;
      const text = `${card.dataset.make} ${card.dataset.plate}`.toLowerCase();
      const stateMatch = (filter === 'all') || (state === filter);
      const textMatch = !q || text.includes(q);
      card.style.display = (stateMatch && textMatch) ? '' : 'none';
    });
  }

  // Toast + simple dialog
  function toast(msg, tone = 'ok') {
    let host = $('#toast-host');
    if (!host) {
      host = document.createElement('div');
      host.id = 'toast-host';
      document.body.appendChild(host);
    }
    const el = document.createElement('div');
    el.className = `toast ${tone}`;
    el.textContent = msg;
    host.appendChild(el);
    requestAnimationFrame(() => el.classList.add('show'));
    setTimeout(() => el.classList.remove('show'), 2200);
    setTimeout(() => el.remove(), 2600);
  }

  function dialog(title, html) {
    let dlg = $('#dialog');
    if (!dlg) {
      dlg = document.createElement('div');
      dlg.id = 'dialog';
      dlg.innerHTML = `
        <div class="dlg-wrap">
          <div class="dlg">
            <header><strong id="dlg-title"></strong><button class="btn icon" id="dlg-close">✕</button></header>
            <div id="dlg-body"></div>
          </div>
        </div>`;
      document.body.appendChild(dlg);
      $('#dlg-close', dlg).addEventListener('click', () => dlg.remove());
      dlg.addEventListener('click', e => e.target === $('.dlg-wrap', dlg) && dlg.remove());
    }
    $('#dlg-title', dlg).textContent = title;
    $('#dlg-body', dlg).innerHTML = html;
  }

  // Select first vehicle on load (if any)
  const first = $('.vehicle-card');
  if (first) first.click();
})();
