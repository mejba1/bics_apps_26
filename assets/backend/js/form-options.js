/**
 * Admin > Form Options: manage registration drop-down values (add, rename, dial code, show/hide, reorder, delete) over AJAX.
 */
(function () {
  'use strict';

  var FO = window.FO;
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var options = FO.options.slice();
  var selectedProfession = null;
  var toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 1800, timerProgressBar: true });

  function request(method, url, body) {
    return fetch(url, {
      method: method,
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (d) {
        if (!r.ok) {
          var msg = d.message || 'Could not save (' + r.status + ').';
          if (d.errors) msg = Object.keys(d.errors).map(function (k) { return d.errors[k][0]; }).join('<br>');
          if (r.status === 419) msg = 'Your session expired. Please reload the page.';
          throw new Error(msg);
        }
        return d;
      });
    });
  }
  function fail(e) { Swal.fire({ icon: 'error', title: 'Not saved', html: e.message }); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  function itemsOf(list, parentId) {
    return options.filter(function (o) { return o.list === list && (list === 'speciality' ? o.parent_id === parentId : !o.parent_id); });
  }
  function replace(opt) { options = options.map(function (o) { return o.id === opt.id ? opt : o; }); }

  /* ---------------- rendering ---------------- */

  function render(list) {
    var ul = document.querySelector('.fo-list[data-list="' + list + '"]');
    var items = list === 'speciality' ? (selectedProfession ? itemsOf('speciality', selectedProfession) : []) : itemsOf(list);
    ul.innerHTML = '';
    if (!items.length) {
      ul.innerHTML = '<li class="fo-empty">' + (list === 'speciality' && !selectedProfession ? 'Select a profession on the left.' : 'Nothing here yet — add the first one above.') + '</li>';
    }
    items.forEach(function (o) { ul.appendChild(row(o)); });
    updateCounts();
    if (list === 'country') filterCountries();
  }

  function row(o) {
    var li = document.createElement('li');
    li.className = 'fo-row' + (o.is_active ? '' : ' is-off') + (o.list === 'profession' && o.id === selectedProfession ? ' is-selected' : '');
    li.dataset.id = o.id;
    var specCount = o.list === 'profession' ? itemsOf('speciality', o.id).length : null;
    li.innerHTML =
      '<i class="bi bi-grip-vertical fo-handle" title="Drag to reorder"></i>' +
      '<input class="fo-label" value="' + esc(o.label) + '" maxlength="150" aria-label="Name">' +
      (o.list === 'country' ? '<input class="fo-dial" value="' + esc(o.extra || '') + '" placeholder="+00" maxlength="10" aria-label="Dial code">' : '') +
      (specCount !== null ? '<button type="button" class="btn btn-sm btn-outline-primary fo-pick" title="Edit specialities">' + specCount + ' specialities <i class="bi bi-chevron-right"></i></button>' : '') +
      '<div class="form-check form-switch" title="' + (o.is_active ? 'Shown on the form' : 'Hidden from the form') + '"><input class="form-check-input fo-toggle" type="checkbox" ' + (o.is_active ? 'checked' : '') + ' aria-label="Show on form"></div>' +
      '<button type="button" class="btn btn-sm btn-outline-danger fo-del" title="Delete"><i class="bi bi-trash"></i></button>';

    var label = li.querySelector('.fo-label'), dial = li.querySelector('.fo-dial');
    var sending = null; // value currently being saved (blur + change both fire)
    function save() {
      var newLabel = label.value.trim(), newDial = dial ? dial.value.trim() : o.extra;
      if (!newLabel) { label.value = o.label; return; }
      if (newLabel === o.label && (newDial || '') === (o.extra || '')) return;
      var key = newLabel + '|' + newDial;
      if (sending === key) return;
      sending = key;
      request('PUT', FO.urls.base + '/' + o.id, { label: newLabel, extra: dial ? newDial : null }).finally(function () { sending = null; }).then(function (d) {
        replace(d.option); o = d.option;
        label.value = o.label; if (dial) dial.value = o.extra || '';
        li.classList.remove('fo-saved'); void li.offsetWidth; li.classList.add('fo-saved');
        if (o.list === 'profession' && o.id === selectedProfession) document.getElementById('fo-spec-title').textContent = o.label;
        toast.fire({ icon: 'success', title: 'Saved' });
      }).catch(function (e) { label.value = o.label; if (dial) dial.value = o.extra || ''; fail(e); });
    }
    [label, dial].forEach(function (input) {
      if (!input) return;
      input.addEventListener('blur', save);
      input.addEventListener('change', save); // save() ignores repeats when nothing changed
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
        if (e.key === 'Escape') { label.value = o.label; if (dial) dial.value = o.extra || ''; input.blur(); }
      });
    });

    li.querySelector('.fo-toggle').addEventListener('change', function () {
      request('POST', FO.urls.base + '/' + o.id + '/toggle').then(function (d) {
        replace(d.option); render(o.list);
        toast.fire({ icon: 'success', title: d.option.is_active ? 'Shown on the form' : 'Hidden from the form' });
      }).catch(fail);
    });

    li.querySelector('.fo-del').addEventListener('click', function () {
      var kids = o.list === 'profession' ? itemsOf('speciality', o.id).length : 0;
      Swal.fire({
        icon: 'warning', title: 'Delete "' + o.label + '"?',
        html: (kids ? 'Its <b>' + kids + ' specialities</b> will be deleted too.<br>' : '') + 'Past registrations are not affected. Tip: switch it off instead to hide it but keep it.',
        showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#d33'
      }).then(function (r) {
        if (!r.isConfirmed) return;
        request('DELETE', FO.urls.base + '/' + o.id).then(function () {
          options = options.filter(function (x) { return x.id !== o.id && x.parent_id !== o.id; });
          if (selectedProfession === o.id) selectProfession(null);
          render(o.list);
          toast.fire({ icon: 'success', title: 'Deleted' });
        }).catch(fail);
      });
    });

    var pick = li.querySelector('.fo-pick');
    if (pick) {
      pick.addEventListener('click', function () { selectProfession(o.id); });
    }
    return li;
  }

  function selectProfession(id) {
    selectedProfession = id;
    var p = options.find(function (o) { return o.id === id; });
    document.getElementById('fo-spec-title').textContent = p ? p.label : '—';
    var form = document.getElementById('fo-spec-form');
    form.querySelectorAll('input, button').forEach(function (el) { el.disabled = !p; });
    render('profession');
    render('speciality');
    if (p && window.innerWidth < 992) document.getElementById('fo-spec-form').scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function updateCounts() {
    ['title', 'profession', 'id_type', 'country'].forEach(function (list) {
      var all = itemsOf(list), on = all.filter(function (o) { return o.is_active; }).length;
      var badge = document.querySelector('[data-count="' + list + '"]');
      if (badge) badge.textContent = on === all.length ? all.length : on + '/' + all.length;
    });
  }

  /* ---------------- add ---------------- */

  document.querySelectorAll('.fo-add').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var list = form.dataset.list;
      var body = { list: list, label: form.label.value.trim(), extra: form.extra ? form.extra.value.trim() : null, parent_id: list === 'speciality' ? selectedProfession : null };
      if (!body.label) return;
      var btn = form.querySelector('button'); btn.disabled = true;
      request('POST', FO.urls.store, body).then(function (d) {
        options.push(d.option);
        form.reset();
        render(list);
        if (list === 'speciality') render('profession');
        form.label.focus();
        var ul = document.querySelector('.fo-list[data-list="' + list + '"]'); ul.scrollTop = ul.scrollHeight;
        toast.fire({ icon: 'success', title: 'Added' });
      }).catch(fail).finally(function () { btn.disabled = false; });
    });
  });

  /* ---------------- reorder ---------------- */

  document.querySelectorAll('.fo-list').forEach(function (ul) {
    Sortable.create(ul, {
      handle: '.fo-handle', animation: 150, ghostClass: 'fo-ghost',
      onEnd: function () {
        var ids = Array.prototype.map.call(ul.querySelectorAll('.fo-row'), function (li) { return +li.dataset.id; });
        options.sort(function (a, b) {
          var ia = ids.indexOf(a.id), ib = ids.indexOf(b.id);
          return (ia < 0 || ib < 0) ? 0 : ia - ib;
        });
        request('POST', FO.urls.reorder, { ids: ids }).then(function () { toast.fire({ icon: 'success', title: 'Order saved' }); }).catch(fail);
      }
    });
  });

  /* ---------------- country search ---------------- */

  var search = document.getElementById('fo-country-search');
  function filterCountries() {
    var q = (search.value || '').trim().toLowerCase();
    document.querySelectorAll('.fo-list[data-list="country"] .fo-row').forEach(function (li) {
      var o = options.find(function (x) { return x.id === +li.dataset.id; });
      li.style.display = !q || o.label.toLowerCase().indexOf(q) > -1 || (o.extra || '').indexOf(q) > -1 ? '' : 'none';
    });
  }
  search.addEventListener('input', filterCountries);

  ['title', 'profession', 'id_type', 'country'].forEach(render);
  render('speciality');
})();
