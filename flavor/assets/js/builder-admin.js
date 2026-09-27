/**
 * Flavor Builder — admin drag-and-drop editor.
 * Ported from Rasta Commerce "Rasta Builder" (assets/js/builder-admin.js).
 * Dependencies: none.
 */
(() => {
  'use strict';

  const root = document.querySelector('[data-fb-admin]');
  if (!root) return;

  const canvas = root.querySelector('[data-fb-canvas]');
  const input = root.querySelector('[data-fb-input]');
  const empty = root.querySelector('[data-fb-empty]');
  const schema = JSON.parse(root.querySelector('[data-fb-schema]').textContent || '{}');

  /* ── State: read existing blocks from DOM → build model ── */
  const blocks = [];

  const readBlockFromDom = (el) => {
    const type = el.dataset.fbType;
    const props = {};
    el.querySelectorAll('[data-fb-prop]').forEach((f) => {
      props[f.dataset.fbProp] = f.value;
    });
    return { type, props };
  };

  const syncFromDom = () => {
    blocks.length = 0;
    canvas.querySelectorAll('[data-fb-block]').forEach((el) => blocks.push(readBlockFromDom(el)));
    input.value = JSON.stringify(blocks);
    empty.style.display = blocks.length ? 'none' : 'grid';
  };

  const buildBlockEl = (type, props) => {
    const def = schema[type];
    if (!def) return null;

    const el = document.createElement('div');
    el.className = 'fb-block-item';
    el.draggable = true;
    el.dataset.fbBlock = '';
    el.dataset.fbType = type;

    const head = document.createElement('div');
    head.className = 'fb-block-item__head';
    head.innerHTML = `<span class="dashicons ${def.icon}"></span><strong>${def.label}</strong>`;
    const tools = document.createElement('span');
    tools.className = 'fb-block-item__tools';
    tools.innerHTML = `
      <button type="button" class="fb-tool" data-fb-up title="بالا">↑</button>
      <button type="button" class="fb-tool" data-fb-down title="پایین">↓</button>
      <button type="button" class="fb-tool fb-tool--danger" data-fb-del title="حذف">✕</button>`;
    head.appendChild(tools);

    const fields = document.createElement('div');
    fields.className = 'fb-block-item__fields';
    (def.fields || []).forEach((f) => {
      const lab = document.createElement('label');
      lab.className = 'fb-field';
      const span = document.createElement('span');
      span.textContent = f.label;
      lab.appendChild(span);

      let ctrl;
      const val = props && props[f.key] !== undefined ? props[f.key] : (def.defaults[f.key] ?? '');
      if (f.type === 'textarea') {
        ctrl = document.createElement('textarea');
        ctrl.rows = 2;
        ctrl.value = val;
      } else if (f.type === 'select') {
        ctrl = document.createElement('select');
        Object.entries(f.options || {}).forEach(([v, l]) => {
          const o = document.createElement('option');
          o.value = v;
          o.textContent = l;
          o.selected = val === v;
          ctrl.appendChild(o);
        });
      } else if (f.type === 'number') {
        ctrl = document.createElement('input');
        ctrl.type = 'number';
        ctrl.min = 1;
        ctrl.value = val;
      } else if (f.type === 'url') {
        ctrl = document.createElement('input');
        ctrl.type = 'url';
        ctrl.value = val;
      } else {
        ctrl = document.createElement('input');
        ctrl.type = 'text';
        ctrl.value = val;
      }
      ctrl.dataset.fbProp = f.key;
      lab.appendChild(ctrl);
      fields.appendChild(lab);
    });

    el.append(head, fields);
    bindBlockEvents(el);
    return el;
  };

  const bindBlockEvents = (el) => {
    el.addEventListener('input', syncFromDom);

    el.addEventListener('click', (e) => {
      const t = e.target;
      if (t.dataset.fbUp !== undefined) { moveBlock(el, -1); }
      else if (t.dataset.fbDown !== undefined) { moveBlock(el, 1); }
      else if (t.dataset.fbDel !== undefined) {
        el.remove();
        syncFromDom();
      }
    });

    /* Reorder drag */
    el.addEventListener('dragstart', (e) => {
      e.dataTransfer.setData('text/fb-move', '1');
      e.dataTransfer.effectAllowed = 'move';
      el.classList.add('dragging');
    });
    el.addEventListener('dragend', () => {
      el.classList.remove('dragging');
      clearDropIndicators();
    });
    el.addEventListener('dragover', (e) => {
      const moving = canvas.querySelector('.dragging');
      if (!moving || moving === el) return;
      e.preventDefault();
      e.stopPropagation();
      const r = el.getBoundingClientRect();
      const before = e.clientY < (r.top + r.height / 2);
      el.classList.toggle('dragover-top', before);
      el.classList.toggle('dragover-bottom', !before);
    });
    el.addEventListener('dragleave', () => {
      el.classList.remove('dragover-top', 'dragover-bottom');
    });
    el.addEventListener('drop', (e) => {
      const moving = canvas.querySelector('.dragging');
      if (!moving || moving === el) return;
      e.preventDefault();
      e.stopPropagation();
      const r = el.getBoundingClientRect();
      const before = e.clientY < (r.top + r.height / 2);
      if (before) canvas.insertBefore(moving, el);
      else canvas.insertBefore(moving, el.nextSibling);
      syncFromDom();
    });
  };

  const clearDropIndicators = () => {
    canvas.querySelectorAll('[data-fb-block]').forEach((b) => b.classList.remove('dragover-top', 'dragover-bottom'));
  };

  const moveBlock = (el, dir) => {
    const sib = dir < 0 ? el.previousElementSibling : el.nextElementSibling;
    if (!sib) return;
    if (dir < 0) canvas.insertBefore(el, sib);
    else canvas.insertBefore(el, sib.nextSibling);
    syncFromDom();
  };

  /* ── Palette drag → canvas ── */
  root.querySelectorAll('[data-fb-type]').forEach((item) => {
    item.addEventListener('dragstart', (e) => {
      e.dataTransfer.setData('text/fb-new', item.dataset.fbType);
      e.dataTransfer.effectAllowed = 'copy';
    });
  });

  canvas.addEventListener('dragover', (e) => {
    if (e.dataTransfer.types.includes('text/fb-new') || e.dataTransfer.types.includes('text/fb-move')) {
      e.preventDefault();
      canvas.classList.add('dragover');
    }
  });
  canvas.addEventListener('dragleave', () => canvas.classList.remove('dragover'));

  canvas.addEventListener('drop', (e) => {
    const type = e.dataTransfer.getData('text/fb-new');
    if (!type) return;
    e.preventDefault();
    canvas.classList.remove('dragover');
    const el = buildBlockEl(type, schema[type].defaults);
    if (!el) return;
    /* Drop position: before the block being hovered, else append. */
    const target = e.target.closest('[data-fb-block]');
    if (target) {
      const r = target.getBoundingClientRect();
      const before = e.clientY < (r.top + r.height / 2);
      canvas.insertBefore(el, before ? target : target.nextSibling);
    } else {
      canvas.appendChild(el);
    }
    syncFromDom();
  });

  /* ── Enable toggle ── */
  const enable = root.querySelector('[data-fb-enabled]');
  const updateDisabledState = () => {
    const off = !enable.checked;
    canvas.style.opacity = off ? '0.5' : '1';
    canvas.style.pointerEvents = off ? 'none' : '';
    root.querySelectorAll('[data-fb-type]').forEach((p) => { p.style.opacity = off ? '0.5' : '1'; p.style.pointerEvents = off ? 'none' : ''; });
  };
  enable.addEventListener('change', updateDisabledState);
  updateDisabledState();

  /* ── Initial sync ── */
  syncFromDom();
})();
