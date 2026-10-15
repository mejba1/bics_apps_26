{{--
  Social / web links editor: any number of links (App\Support\SocialLinks::MAX), platform recognised from the address.
  Click a link's icon to choose another icon or colour (or keep "Automatic").
  Include with ['links' => $p->socialLinks(), 'disabled' => false, 'id' => 'social-editor'].
  Read the list with window.SocialEditor.collect(element) → [{url, label, type, icon, color}]; show errors with SocialEditor.errors(element, {"socials.2.url": [...]}).
--}}
@php $edId = $id ?? 'social-editor'; @endphp
<div class="sl-ed" id="{{ $edId }}" data-social-editor data-max="{{ \App\Support\SocialLinks::MAX }}" @if(!empty($disabled)) data-disabled="1" @endif>
  <div class="sl-list" role="list"></div>
  <div class="sl-empty">No links yet. Add LinkedIn, X, ResearchGate, ORCID, a lab website — as many as you like.</div>
  @if(empty($disabled))
  <div class="sl-foot">
    <button type="button" class="sl-add"><i class="bi bi-plus-lg"></i> Add link</button>
    <span class="sl-hint">Tip: paste several addresses at once — each becomes a link. Click an icon to change it.</span>
  </div>
  @endif
  <script type="application/json" class="sl-data">{!! json_encode(['links' => array_values($links ?? []), 'platforms' => \App\Support\SocialLinks::forJs(), 'icons' => \App\Support\SocialLinks::iconsForJs(), 'colors' => \App\Support\SocialLinks::COLORS], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) !!}</script>
</div>

@once
<style>
  .sl-ed { --sl-line: rgba(120,135,160,.28); --sl-soft: rgba(120,135,160,.08); }
  .sl-list { display: grid; gap: 8px; }
  .sl-row { position: relative; display: grid; grid-template-columns: 40px minmax(0, 1.6fr) minmax(0, 1fr) auto; gap: 8px; align-items: center; padding: 8px; border: 1px solid var(--sl-line); border-radius: 14px; background: var(--sl-soft); transition: border-color .2s, box-shadow .2s; }
  .sl-row:focus-within { border-color: rgba(37,99,208,.55); box-shadow: 0 0 0 3px rgba(37,99,208,.12); }
  .sl-row.sl-bad { border-color: #d13c4b; }
  .sl-badge { position: relative; width: 40px; height: 40px; border-radius: 12px; border: 0; padding: 0; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 1.1rem; background: var(--sl-c, #475569); transition: background .2s, transform .15s; overflow: hidden; }
  .sl-badge img { width: 100%; height: 100%; object-fit: cover; }
  button.sl-badge { cursor: pointer; } button.sl-badge:hover { transform: scale(1.06); }
  button.sl-badge::after { content: "\F4CB"; font-family: bootstrap-icons; position: absolute; right: -1px; bottom: -1px; width: 15px; height: 15px; border-radius: 6px 0 10px 0; background: #fff; color: #334155; font-size: 9px; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity .15s; }
  button.sl-badge:hover::after, button.sl-badge:focus-visible::after { opacity: 1; }
  .sl-row input { min-width: 0; }
  .sl-name { font-size: 11px; font-weight: 700; opacity: .7; margin: 2px 0 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .sl-name .sl-custom { color: #2563d0; opacity: 1; }
  .sl-err { grid-column: 2 / -1; color: #d13c4b; font-size: 12.5px; font-weight: 600; margin-top: -2px; }
  .sl-tools { display: flex; gap: 2px; }
  .sl-tools button { width: 32px; height: 32px; border: 0; border-radius: 9px; background: transparent; color: inherit; opacity: .6; display: inline-flex; align-items: center; justify-content: center; }
  .sl-tools button:hover:not(:disabled) { opacity: 1; background: var(--sl-soft); }
  .sl-tools button:disabled { opacity: .2; }
  .sl-tools .sl-del:hover { color: #d13c4b; }
  .sl-empty { font-size: 13px; opacity: .7; padding: 10px 2px; display: none; }
  .sl-ed.is-empty .sl-empty { display: block; }
  .sl-foot { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 10px; }
  .sl-add { border: 1.5px dashed rgba(37,99,208,.55); background: transparent; color: #2563d0; font-weight: 700; border-radius: 12px; padding: 8px 14px; display: inline-flex; gap: 6px; align-items: center; }
  .sl-add:hover { background: rgba(37,99,208,.07); }
  .sl-add:disabled { opacity: .4; }
  .sl-hint { font-size: 12px; opacity: .65; }
  /* icon picker */
  .sl-pick { position: absolute; z-index: 30; top: 54px; left: 4px; width: min(380px, calc(100vw - 40px)); background: var(--sl-pop-bg, #fff); color: var(--sl-pop-ink, #1d2433); border: 1px solid var(--sl-line); border-radius: 16px; box-shadow: 0 22px 50px -18px rgba(10,26,61,.45); padding: 12px; }
  html[data-theme="dark"] .sl-pick { --sl-pop-bg: #111a2e; --sl-pop-ink: #e8edf6; }
  @media (prefers-color-scheme: dark) { .sl-pick.sl-auto-dark { --sl-pop-bg: #111a2e; --sl-pop-ink: #e8edf6; } }
  .sl-pick h6 { font-size: 11px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; opacity: .6; margin: 10px 0 6px; }
  .sl-pick h6:first-child { margin-top: 0; }
  .sl-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: 5px; }
  .sl-grid button { aspect-ratio: 1; border: 1.5px solid transparent; border-radius: 10px; background: var(--sl-soft); color: inherit; display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; padding: 0; overflow: hidden; }
  .sl-grid button:hover, .sl-grid button:focus-visible { border-color: rgba(37,99,208,.6); outline: 0; }
  .sl-grid button.on { border-color: #2563d0; box-shadow: 0 0 0 2px rgba(37,99,208,.2); }
  .sl-grid button.pf { color: #fff; }
  .sl-grid img { width: 100%; height: 100%; object-fit: cover; }
  .sl-auto { width: 100%; display: flex; align-items: center; gap: 10px; border: 1.5px solid transparent; background: var(--sl-soft); border-radius: 12px; padding: 7px 10px; color: inherit; text-align: left; font-size: 13px; }
  .sl-auto.on { border-color: #2563d0; }
  .sl-auto .sl-badge { width: 30px; height: 30px; font-size: .9rem; border-radius: 9px; }
  .sl-colors { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
  .sl-colors button { width: 26px; height: 26px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 1px var(--sl-line); padding: 0; }
  .sl-colors button.on { box-shadow: 0 0 0 2px #2563d0; }
  .sl-colors .sl-c-auto { background: conic-gradient(#0a66c2, #d62976, #1fa855, #ff0000, #0a66c2); }
  .sl-colors input[type=color] { width: 30px; height: 28px; padding: 0; border: 1px solid var(--sl-line); border-radius: 8px; background: none; }
  .sl-pick-foot { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; font-size: 12px; opacity: .8; }
  .sl-pick-foot button { border: 0; background: #2563d0; color: #fff; font-weight: 700; border-radius: 9px; padding: 5px 12px; }
  @media (max-width: 575px) {
    .sl-row { grid-template-columns: 40px minmax(0, 1fr) auto; }
    .sl-row .sl-label { grid-column: 2 / 3; }
    .sl-row .sl-tools { grid-row: 1; grid-column: 3; }
    .sl-grid { grid-template-columns: repeat(6, 1fr); }
  }
</style>
<script>
window.SocialEditor = (function () {
  function hostOf(u) { try { return new URL(/^https?:\/\//i.test(u) ? u : 'https://' + u).hostname.toLowerCase().replace(/^(www|m|mobile)\./, ''); } catch (e) { return ''; } }
  function detect(platforms, u, hint) {
    var h = hostOf(u);
    if (h) for (var i = 0; i < platforms.length; i++) {
      var p = platforms[i];
      if (p.domains && p.domains.some(function (d) { return h === d || h.slice(-d.length - 1) === '.' + d; })) return p;
      if (p.pattern && new RegExp(p.pattern).test(h)) return p;
    }
    var keep = platforms.find(function (p) { return p.type === hint && hint !== 'other'; });
    return keep || platforms[platforms.length - 1];
  }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

  function init(ed) {
    if (ed._sl) return;
    var cfg = JSON.parse(ed.querySelector('.sl-data').textContent), platforms = cfg.platforms, icons = cfg.icons || [], colors = cfg.colors || [], max = +ed.dataset.max || 100;
    var disabled = ed.dataset.disabled === '1', list = ed.querySelector('.sl-list'), add = ed.querySelector('.sl-add');
    var rows = cfg.links.map(function (l) { return { url: l.url, label: l.label || '', hint: l.type, icon: l.icon || '', color: l.color || '' }; });
    var openPick = null;

    /** the same rules as App\Support\SocialLinks::look() */
    function look(r) {
      var p = detect(platforms, r.url, r.hint), L = { icon: p.icon, image: p.image, color: p.color, name: p.name, custom: false };
      if (r.icon && r.icon.indexOf('platform:') === 0) {
        var q = platforms.find(function (x) { return x.type === r.icon.slice(9); });
        if (q) { L.icon = q.icon; L.image = q.image; L.color = q.color; L.custom = true; }
      } else if (r.icon) { L.icon = r.icon; L.image = null; L.custom = true; }
      if (r.color) { L.color = r.color; L.custom = true; }
      return L;
    }
    function badgeInner(L) { return L.image ? '<img src="' + esc(L.image) + '" alt="">' : '<i class="bi ' + esc(L.icon) + '"></i>'; }
    function nameLine(r, L) { return r.url ? esc(L.name) + (L.custom ? ' · <span class="sl-custom">custom icon</span>' : '') : 'Paste an address — the platform is recognised'; }

    function closePick() { if (openPick) { openPick.remove(); openPick = null; document.removeEventListener('mousedown', outside, true); } }
    function outside(e) { if (openPick && !openPick.contains(e.target) && !e.target.closest('.sl-badge')) closePick(); }
    function pick(row, i) {
      var was = openPick && openPick.parentNode === row;
      closePick(); if (was) return;
      var r = rows[i], auto = detect(platforms, r.url, r.hint);
      var pop = document.createElement('div'); pop.className = 'sl-pick sl-auto-dark'; pop.setAttribute('role', 'dialog'); pop.setAttribute('aria-label', 'Choose an icon');
      pop.innerHTML =
        '<h6>Icon</h6><button type="button" class="sl-auto' + (!r.icon ? ' on' : '') + '" data-icon=""><span class="sl-badge" style="--sl-c:' + auto.color + '">' + badgeInner(auto) + '</span><span><b>Automatic</b> — ' + esc(auto.name) + '</span></button>' +
        '<h6>Platforms</h6><div class="sl-grid">' + platforms.filter(function (p) { return p.type !== 'other'; }).map(function (p) {
          var v = 'platform:' + p.type;
          return '<button type="button" class="pf' + (r.icon === v ? ' on' : '') + '" data-icon="' + esc(v) + '" title="' + esc(p.name) + '" style="background:' + p.color + '">' + badgeInner(p) + '</button>';
        }).join('') + '</div>' +
        '<h6>More icons</h6><div class="sl-grid">' + icons.map(function (x) {
          return '<button type="button" class="' + (r.icon === x.icon ? 'on' : '') + '" data-icon="' + esc(x.icon) + '" title="' + esc(x.name) + '"><i class="bi ' + esc(x.icon) + '"></i></button>';
        }).join('') + '</div>' +
        '<h6>Colour</h6><div class="sl-colors"><button type="button" class="sl-c-auto' + (!r.color ? ' on' : '') + '" data-color="" title="Automatic colour"></button>' +
          colors.map(function (c) { return '<button type="button" class="' + (r.color === c ? 'on' : '') + '" data-color="' + c + '" title="' + c + '" style="background:' + c + '"></button>'; }).join('') +
          '<input type="color" value="' + esc(r.color || auto.color) + '" title="Any colour" aria-label="Any colour"></div>' +
        '<div class="sl-pick-foot"><span>Shown on the public profile</span><button type="button" class="sl-done">Done</button></div>';
      row.appendChild(pop); openPick = pop;
      function apply() {
        var L = look(r), b = row.querySelector('.sl-badge');
        row.style.setProperty('--sl-c', L.color); b.innerHTML = badgeInner(L);
        row.querySelector('.sl-name').innerHTML = nameLine(r, L);
        ed.dispatchEvent(new Event('input', { bubbles: true }));
      }
      pop.querySelectorAll('[data-icon]').forEach(function (b) { b.addEventListener('click', function () {
        r.icon = b.dataset.icon; pop.querySelectorAll('[data-icon]').forEach(function (x) { x.classList.toggle('on', x === b); }); apply();
      }); });
      pop.querySelectorAll('[data-color]').forEach(function (b) { b.addEventListener('click', function () {
        r.color = b.dataset.color; pop.querySelectorAll('[data-color]').forEach(function (x) { x.classList.toggle('on', x === b); }); apply();
      }); });
      pop.querySelector('input[type=color]').addEventListener('input', function () { r.color = this.value; pop.querySelectorAll('[data-color]').forEach(function (x) { x.classList.remove('on'); }); apply(); });
      pop.querySelector('.sl-done').addEventListener('click', closePick);
      pop.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closePick(); row.querySelector('.sl-badge').focus(); } });
      setTimeout(function () { document.addEventListener('mousedown', outside, true); (pop.querySelector('.on') || pop.querySelector('button')).focus(); }, 0);
    }

    function render(focusIndex) {
      closePick();
      list.innerHTML = rows.map(function (r, i) {
        var L = look(r);
        var badge = disabled ? '<span class="sl-badge" title="' + esc(L.name) + '">' + badgeInner(L) + '</span>'
          : '<button type="button" class="sl-badge" title="' + esc(L.name) + ' — click to change the icon" aria-label="Change icon">' + badgeInner(L) + '</button>';
        return '<div class="sl-row" role="listitem" data-i="' + i + '" style="--sl-c:' + L.color + '">' +
          '<div>' + badge + '</div>' +
          '<div><input class="form-control sl-url" type="url" inputmode="url" placeholder="https://linkedin.com/in/your-name" value="' + esc(r.url) + '" maxlength="500"' + (disabled ? ' disabled' : '') + ' aria-label="Link address">' +
          '<div class="sl-name">' + nameLine(r, L) + '</div></div>' +
          '<div class="sl-label"><input class="form-control" type="text" placeholder="Name (optional)" value="' + esc(r.label) + '" maxlength="60"' + (disabled ? ' disabled' : '') + ' aria-label="Link name"></div>' +
          (disabled ? '<div></div>' : '<div class="sl-tools"><button type="button" class="sl-up" title="Move up"' + (i === 0 ? ' disabled' : '') + '><i class="bi bi-arrow-up"></i></button>' +
            '<button type="button" class="sl-down" title="Move down"' + (i === rows.length - 1 ? ' disabled' : '') + '><i class="bi bi-arrow-down"></i></button>' +
            '<button type="button" class="sl-del" title="Remove"><i class="bi bi-x-lg"></i></button></div>') + '</div>';
      }).join('');
      ed.classList.toggle('is-empty', !rows.length);
      if (add) { add.disabled = rows.length >= max; add.innerHTML = '<i class="bi bi-plus-lg"></i> ' + (rows.length ? 'Add another link' : 'Add link'); }
      list.querySelectorAll('.sl-row').forEach(function (row) {
        var i = +row.dataset.i, url = row.querySelector('.sl-url'), label = row.querySelector('.sl-label input');
        var b = row.querySelector('button.sl-badge'); if (b) b.addEventListener('click', function () { pick(row, i); });
        url.addEventListener('input', function () {
          rows[i].url = url.value;
          var L = look(rows[i]);
          row.style.setProperty('--sl-c', L.color);
          row.querySelector('.sl-badge').innerHTML = badgeInner(L);
          row.querySelector('.sl-name').innerHTML = nameLine(rows[i], L);
          row.classList.remove('sl-bad'); var e = row.querySelector('.sl-err'); if (e) e.remove();
        });
        // pasting several addresses (one per line, or separated by spaces) makes one link each
        url.addEventListener('paste', function (ev) {
          var t = (ev.clipboardData || window.clipboardData).getData('text') || '';
          var parts = t.split(/[\s,;]+/).filter(function (x) { return /^(https?:\/\/|www\.)|\.[a-z]{2,}\//i.test(x); });
          if (parts.length < 2) return;
          ev.preventDefault();
          rows.splice.apply(rows, [i, 1].concat(parts.slice(0, max - rows.length + 1).map(function (u) { return { url: u, label: '', hint: null, icon: '', color: '' }; })));
          render(); ed.dispatchEvent(new Event('input', { bubbles: true }));
        });
        label.addEventListener('input', function () { rows[i].label = label.value; });
        var up = row.querySelector('.sl-up'), down = row.querySelector('.sl-down'), del = row.querySelector('.sl-del');
        if (up) up.addEventListener('click', function () { var t = rows[i - 1]; rows[i - 1] = rows[i]; rows[i] = t; render(); ed.dispatchEvent(new Event('input', { bubbles: true })); });
        if (down) down.addEventListener('click', function () { var t = rows[i + 1]; rows[i + 1] = rows[i]; rows[i] = t; render(); ed.dispatchEvent(new Event('input', { bubbles: true })); });
        if (del) del.addEventListener('click', function () { rows.splice(i, 1); render(); ed.dispatchEvent(new Event('input', { bubbles: true })); });
      });
      if (focusIndex !== undefined) { var f = list.querySelector('[data-i="' + focusIndex + '"] .sl-url'); if (f) f.focus(); }
    }
    if (add) add.addEventListener('click', function () { if (rows.length >= max) return; rows.push({ url: '', label: '', hint: null, icon: '', color: '' }); render(rows.length - 1); });
    ed._sl = {
      collect: function () { return rows.filter(function (r) { return r.url.trim(); }).map(function (r) { return { url: r.url.trim(), label: r.label.trim() || null, type: r.hint || null, icon: r.icon || null, color: r.color || null }; }); },
      // server errors use the position among the links sent ("socials.2.url")
      errors: function (errs) {
        var sent = []; rows.forEach(function (r, i) { if (r.url.trim()) sent.push(i); });
        var first = null;
        Object.keys(errs || {}).forEach(function (k) {
          var m = k.match(/^socials\.(\d+)\.(url|label|icon|color)$/); if (!m) return;
          var row = list.querySelector('[data-i="' + sent[+m[1]] + '"]'); if (!row) return;
          row.classList.add('sl-bad');
          if (!row.querySelector('.sl-err')) { var d = document.createElement('div'); d.className = 'sl-err'; d.textContent = errs[k][0]; row.appendChild(d); }
          first = first || row.querySelector(m[2] === 'label' ? '.sl-label input' : '.sl-url');
        });
        if (first) first.focus();
        return !!first;
      }
    };
    render();
  }
  document.querySelectorAll('[data-social-editor]').forEach(init);
  return {
    collect: function (el) { init(el); return el._sl.collect(); },
    errors: function (el, errs) { init(el); return el._sl.errors(errs); }
  };
})();
</script>
@endonce
