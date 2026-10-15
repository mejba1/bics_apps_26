/**
 * Admin > Website Pages > Edit: section list + section editor (all saved over AJAX).
 * Expects window.PB (sections, types, urls) from resources/views/backend/pages/cms/edit.blade.php.
 */
(function () {
  'use strict';

  var PB = window.PB;
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var list = document.getElementById('pb-list');
  var editorBody = document.getElementById('pb-editor');
  var editorFooter = document.getElementById('pb-editor-footer');
  var editorTitle = document.getElementById('pb-editor-title');
  var dirtyBadge = document.getElementById('pb-dirty');

  var sections = PB.sections.slice();
  var current = null;      // section being edited
  var currentForm = null;  // { get: fn } for the open editor
  var dirty = false;

  var toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2200, timerProgressBar: true });

  /* ---------------- helpers ---------------- */

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'class') node.className = attrs[k];
      else if (k === 'text') node.textContent = attrs[k];
      else if (k === 'html') node.innerHTML = attrs[k];
      else if (k.indexOf('on') === 0) node.addEventListener(k.slice(2), attrs[k]);
      else if (attrs[k] !== null && attrs[k] !== undefined && attrs[k] !== false) node.setAttribute(k, attrs[k] === true ? '' : attrs[k]);
    });
    (children || []).forEach(function (c) { if (c) node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return node;
  }

  function uid() { return 'pb' + Math.random().toString(36).slice(2, 9); }

  function imageUrl(path) {
    if (!path) return '';
    return /^(https?:|\/\/|data:)/i.test(path) ? path : PB.baseUrl + path.replace(/^\/+/, '');
  }

  function request(method, url, body) {
    var opts = { method: method, headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } };
    if (body instanceof FormData) opts.body = body;
    else if (body !== undefined) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    return fetch(url, opts).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (data) {
        if (!r.ok) {
          var msg = data.message || 'Something went wrong (' + r.status + ').';
          if (data.errors) msg = Object.keys(data.errors).map(function (k) { return data.errors[k][0]; }).join('<br>');
          if (r.status === 419) msg = 'Your session expired. Please reload the page and log in again.';
          throw new Error(msg);
        }
        return data;
      });
    });
  }

  function fail(err) { Swal.fire({ icon: 'error', title: 'Not saved', html: err.message || String(err) }); }

  function uploadFile(file) {
    var fd = new FormData();
    fd.append('file', file);
    return request('POST', PB.urls.upload, fd);
  }

  function setDirty(value) {
    dirty = value;
    dirtyBadge.classList.toggle('d-none', !value);
  }

  function confirmDiscard() {
    if (!dirty) return Promise.resolve(true);
    return Swal.fire({ icon: 'warning', title: 'Discard unsaved changes?', showCancelButton: true, confirmButtonText: 'Discard', confirmButtonColor: '#d33' })
      .then(function (r) { return r.isConfirmed; });
  }

  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  /* ---------------- section list ---------------- */

  function renderList() {
    list.innerHTML = '';
    sections.forEach(function (s) { list.appendChild(sectionCard(s)); });
    document.getElementById('pb-empty').classList.toggle('d-none', sections.length > 0);
  }

  function sectionCard(s) {
    var actions = [
      el('button', { class: 'btn btn-sm btn-primary', title: 'Edit', onclick: function () { openEditor(s.id); } }, [el('i', { class: 'bi bi-pencil' })])
    ];
    if (!PB.isGlobal) {
      actions.push(
        el('button', { class: 'btn btn-sm ' + (s.is_active ? 'btn-success' : 'btn-outline-secondary'), title: s.is_active ? 'Visible - click to hide' : 'Hidden - click to show', onclick: function () { toggleSection(s); } },
          [el('i', { class: 'bi ' + (s.is_active ? 'bi-eye' : 'bi-eye-slash') })]),
        el('button', { class: 'btn btn-sm btn-outline-secondary', title: 'Duplicate', onclick: function () { duplicateSection(s); } }, [el('i', { class: 'bi bi-files' })]),
        el('button', { class: 'btn btn-sm btn-outline-danger', title: 'Delete', onclick: function () { deleteSection(s); } }, [el('i', { class: 'bi bi-trash' })])
      );
    }
    var card = el('div', { class: 'card pb-item mb-2' + (current && current.id === s.id ? ' active' : '') + (s.is_active ? '' : ' is-hidden'), 'data-id': s.id }, [
      el('div', { class: 'card-body py-2 px-2 d-flex align-items-center gap-2' }, [
        PB.isGlobal ? null : el('i', { class: 'bi bi-grip-vertical pb-handle', title: 'Drag to reorder' }),
        el('i', { class: 'bi ' + s.icon + ' fs-4 text-primary' }),
        el('div', { class: 'flex-grow-1 pb-item-title', style: 'min-width:0;cursor:pointer', onclick: function () { openEditor(s.id); } }, [
          el('div', { class: 'fw-bold text-truncate', text: s.display_label }),
          el('div', { class: 'small text-muted' }, [s.type_label, s.is_active ? null : el('span', { class: 'badge bg-secondary ms-2', text: 'Hidden' })])
        ]),
        el('div', { class: 'btn-group flex-shrink-0' }, actions)
      ])
    ]);
    return card;
  }

  function replaceSection(updated) {
    sections = sections.map(function (s) { return s.id === updated.id ? updated : s; });
    if (current && current.id === updated.id) current = updated;
  }

  if (!PB.isGlobal && window.Sortable) {
    Sortable.create(list, {
      handle: '.pb-handle', animation: 150, ghostClass: 'pb-ghost',
      onEnd: function () {
        var ids = Array.prototype.map.call(list.children, function (c) { return +c.dataset.id; });
        sections.sort(function (a, b) { return ids.indexOf(a.id) - ids.indexOf(b.id); });
        request('POST', PB.urls.reorder, { ids: ids }).then(function () { toast.fire({ icon: 'success', title: 'Order saved' }); }).catch(fail);
      }
    });
  }

  function toggleSection(s) {
    request('POST', s.urls.toggle).then(function (d) {
      replaceSection(d.section); renderList();
      toast.fire({ icon: 'success', title: d.section.is_active ? 'Section is now visible' : 'Section hidden' });
    }).catch(fail);
  }

  function duplicateSection(s) {
    request('POST', s.urls.duplicate).then(function (d) {
      var i = sections.findIndex(function (x) { return x.id === d.after; });
      sections.splice(i + 1, 0, d.section);
      renderList();
      toast.fire({ icon: 'success', title: 'Copy added (hidden until you show it)' });
    }).catch(fail);
  }

  function deleteSection(s) {
    Swal.fire({ icon: 'warning', title: 'Delete "' + s.display_label + '"?', text: 'This cannot be undone.', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#d33' })
      .then(function (r) {
        if (!r.isConfirmed) return;
        request('DELETE', s.urls.destroy).then(function () {
          sections = sections.filter(function (x) { return x.id !== s.id; });
          if (current && current.id === s.id) closeEditor();
          renderList();
          toast.fire({ icon: 'success', title: 'Section deleted' });
        }).catch(fail);
      });
  }

  /* ---------------- add section ---------------- */

  document.querySelectorAll('.pb-type-card').forEach(function (card) {
    function add() {
      var modal = bootstrap.Modal.getInstance(document.getElementById('addSectionModal'));
      request('POST', PB.urls.addSection, { type: card.dataset.type }).then(function (d) {
        if (modal) modal.hide();
        sections.push(d.section);
        renderList();
        dirty = false;
        openEditor(d.section.id, true);
        toast.fire({ icon: 'success', title: 'Section added - now fill in its content' });
      }).catch(fail);
    }
    card.addEventListener('click', add);
    card.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); add(); } });
  });

  /* ---------------- editor ---------------- */

  function destroyEditors() {
    if (window.tinymce) {
      tinymce.editors.slice().forEach(function (ed) { if (editorBody.contains(ed.getElement())) ed.remove(); });
    }
  }

  function closeEditor() {
    destroyEditors();
    current = null; currentForm = null; setDirty(false);
    editorBody.innerHTML = '';
    editorBody.appendChild(el('div', { class: 'text-center text-muted py-5' }, [el('i', { class: 'bi bi-hand-index fs-1 d-block mb-2' }), 'Choose a section on the left to edit its content.']));
    editorFooter.classList.add('d-none');
    editorTitle.textContent = 'EDIT SECTION';
  }

  function openEditor(id, skipConfirm) {
    (skipConfirm ? Promise.resolve(true) : confirmDiscard()).then(function (ok) {
      if (!ok) return;
      var s = sections.find(function (x) { return x.id === id; });
      if (!s) return;
      destroyEditors();
      current = s;
      editorBody.innerHTML = '';
      editorTitle.textContent = 'EDIT: ' + s.type_label.toUpperCase();

      var def = PB.types[s.type] || { fields: [] };
      var labelInput = el('input', { type: 'text', class: 'form-control', value: s.label || '', placeholder: def.label, maxlength: 150 });
      editorBody.appendChild(el('div', { class: 'mb-3 pb-3 border-bottom' }, [
        el('label', { class: 'form-label small text-muted mb-1', text: 'Section name (only shown here in the admin)' }), labelInput
      ]));
      if (def.description) editorBody.appendChild(el('p', { class: 'small text-muted', text: def.description }));

      var form = buildFields(def.fields, s.content || {});
      editorBody.appendChild(form.el);
      currentForm = { get: function () { return { label: labelInput.value, content: form.get() }; } };

      editorFooter.classList.remove('d-none');
      setDirty(false);
      renderList();
      editorBody.scrollTop = 0;
      if (window.innerWidth < 1200) editorBody.scrollIntoView({ behavior: 'smooth' });
    });
  }

  // Any typing in the editor marks it as changed
  editorBody.addEventListener('input', function () { if (current) setDirty(true); });
  editorBody.addEventListener('change', function () { if (current) setDirty(true); });

  document.getElementById('pb-save').addEventListener('click', save);
  document.getElementById('pb-cancel').addEventListener('click', function () {
    if (!current) return;
    var id = current.id;
    confirmDiscard().then(function (ok) { if (ok) { setDirty(false); openEditor(id, true); } });
  });
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's' && current) { e.preventDefault(); save(); }
  });

  function save() {
    if (!current || !currentForm) return;
    if (window.tinymce) tinymce.triggerSave();
    var btn = document.getElementById('pb-save');
    btn.disabled = true;
    request('PUT', current.urls.update, currentForm.get()).then(function (d) {
      replaceSection(d.section);
      setDirty(false);
      renderList();
      toast.fire({ icon: 'success', title: d.message || 'Saved' });
    }).catch(fail).finally(function () { btn.disabled = false; });
  }

  /* ---------------- field builders ---------------- */

  function buildFields(fields, values) {
    var wrap = el('div');
    var getters = {};
    fields.forEach(function (f) {
      var field = buildField(f, values[f.name]);
      getters[f.name] = field.get;
      wrap.appendChild(field.el);
    });
    return {
      el: wrap,
      get: function () { var out = {}; Object.keys(getters).forEach(function (k) { out[k] = getters[k](); }); return out; }
    };
  }

  function buildField(f, value) {
    switch (f.type) {
      case 'textarea': return textField(f, value, 'textarea');
      case 'code': return textField(f, value, 'code');
      case 'url': return textField(f, value, 'url');
      case 'datetime': return textField(f, value, 'datetime');
      case 'richtext': return richField(f, value);
      case 'image': return imageField(f, value);
      case 'select': return selectField(f, value);
      case 'checkbox': return checkboxField(f, value);
      case 'repeater': return repeaterField(f, value);
      default: return textField(f, value, 'text');
    }
  }

  function group(f, id, control, help) {
    return el('div', { class: 'mb-3' }, [
      el('label', { class: 'form-label fw-semibold mb-1', for: id, text: f.label }),
      control,
      help ? el('div', { class: 'form-text', html: help }) : null
    ]);
  }

  function textField(f, value, kind) {
    var id = uid(), input;
    value = value == null ? '' : String(value);
    if (kind === 'textarea' || kind === 'code') {
      input = el('textarea', { class: 'form-control' + (kind === 'code' ? ' pb-code' : ''), id: id, rows: kind === 'code' ? 12 : 3, spellcheck: kind === 'code' ? 'false' : null });
      input.value = value;
    } else {
      input = el('input', { class: 'form-control', id: id, type: kind === 'datetime' ? 'datetime-local' : 'text', value: value });
    }
    var help = kind === 'url' ? 'Site page: <code>registration</code> &nbsp; Home: <code>/</code> &nbsp; Other site: <code>https://…</code> &nbsp; No link: <code>#</code>' : null;
    return { el: group(f, id, input, help), get: function () { return input.value; } };
  }

  function richField(f, value) {
    var id = uid();
    var ta = el('textarea', { id: id, class: 'form-control', rows: 8 });
    ta.value = value || '';
    var g = group(f, id, ta);
    if (window.tinymce) {
      // Wait until the textarea is in the page
      setTimeout(function () {
        tinymce.init({
          target: ta,
          height: 320,
          menubar: false,
          branding: false,
          plugins: 'link lists table image code autoresize paste',
          toolbar: 'undo redo | formatselect | bold italic underline forecolor | alignleft aligncenter alignright | bullist numlist | link image table | removeformat code',
          autoresize_bottom_margin: 20, max_height: 600,
          convert_urls: false, relative_urls: false,
          content_css: PB.urls.tinymceCss,
          images_upload_handler: function (blobInfo, success, failure) {
            uploadFile(new File([blobInfo.blob()], blobInfo.filename())).then(function (d) { success(d.url); }).catch(function (e) { failure(e.message); });
          },
          setup: function (ed) {
            ed.on('input change undo redo ExecCommand', function () { if (current) setDirty(true); });
          }
        });
      }, 0);
    }
    return {
      el: g,
      get: function () {
        var ed = window.tinymce && tinymce.get(id);
        return ed ? ed.getContent() : ta.value;
      }
    };
  }

  function imageField(f, value) {
    var id = uid();
    var input = el('input', { type: 'text', class: 'form-control', id: id, value: value || '', placeholder: 'Upload a picture or paste an image address' });
    var preview = el('img', { class: 'pb-image-preview mb-2', alt: '' });
    var file = el('input', { type: 'file', accept: 'image/jpeg,image/png,image/gif,image/webp', class: 'd-none' });
    var uploadBtn = el('button', { type: 'button', class: 'btn btn-outline-primary', onclick: function () { file.click(); } }, [el('i', { class: 'bi bi-upload' }), ' Upload']);

    function refresh() {
      var url = imageUrl(input.value.trim());
      preview.classList.toggle('empty', !url);
      if (url) preview.src = url; else preview.removeAttribute('src');
    }
    input.addEventListener('input', refresh);
    file.addEventListener('change', function () {
      if (!file.files[0]) return;
      uploadBtn.disabled = true;
      uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Uploading';
      uploadFile(file.files[0]).then(function (d) {
        input.value = d.path; refresh(); setDirty(true);
      }).catch(fail).finally(function () {
        uploadBtn.disabled = false; uploadBtn.innerHTML = '<i class="bi bi-upload"></i> Upload'; file.value = '';
      });
    });
    refresh();

    var control = el('div', {}, [
      preview,
      el('div', { class: 'input-group input-group-sm' }, [
        input, uploadBtn,
        el('button', { type: 'button', class: 'btn btn-outline-secondary', title: 'Remove image', onclick: function () { input.value = ''; refresh(); setDirty(true); } }, [el('i', { class: 'bi bi-x-lg' })])
      ]),
      file
    ]);
    return { el: group(f, id, control, 'JPG, PNG, GIF or WEBP, up to 5 MB.'), get: function () { return input.value.trim(); } };
  }

  function selectField(f, value) {
    var id = uid();
    var select = el('select', { class: 'form-select', id: id });
    Object.keys(f.options).forEach(function (k) {
      select.appendChild(el('option', { value: k, text: f.options[k], selected: String(value) === k }));
    });
    return { el: group(f, id, select), get: function () { return select.value; } };
  }

  function checkboxField(f, value) {
    var id = uid();
    var input = el('input', { class: 'form-check-input', type: 'checkbox', id: id, checked: !!value });
    return {
      el: el('div', { class: 'form-check form-switch mb-3' }, [input, el('label', { class: 'form-check-label', for: id, text: f.label })]),
      get: function () { return input.checked; }
    };
  }

  function repeaterField(f, values) {
    var items = [];
    var container = el('div', { class: 'pb-rep-items' });
    var count = el('span', { class: 'badge bg-light text-dark border ms-1' });

    function updateCount() { count.textContent = items.length; }

    function itemTitle(item, index) {
      var v = item.peek();
      var text = (f.item_label && v[f.item_label]) || '';
      return (index + 1) + '. ' + (String(text).split('\n')[0] || 'Item');
    }

    function refreshHeaders() {
      items.forEach(function (item, i) { item.titleEl.textContent = itemTitle(item, i); item.refreshThumb(); });
      updateCount();
    }

    function addItem(value, open) {
      var form = buildFields(f.fields, value || {});
      var body = el('div', { class: 'card-body pt-3 pb-1' + (open ? '' : ' d-none') }, [form.el]);
      var titleEl = el('span', { class: 'fw-semibold small text-truncate' });
      var imageKey = (f.fields.find(function (x) { return x.type === 'image'; }) || {}).name;
      var thumb = imageKey ? el('img', { class: 'pb-rep-thumb flex-shrink-0', alt: '' }) : null;
      var item;

      function move(delta) {
        var i = items.indexOf(item), j = i + delta;
        if (j < 0 || j >= items.length) return;
        items.splice(i, 1); items.splice(j, 0, item);
        if (delta < 0) container.insertBefore(card, items[j + 1].card); else container.insertBefore(card, items[j].card.nextSibling);
        refreshHeaders(); setDirty(true);
      }

      var card = el('div', { class: 'card pb-rep-item mb-2 shadow-none border' }, [
        el('div', { class: 'card-header d-flex align-items-center gap-2', onclick: function (e) {
          if (e.target.closest('button')) return;
          body.classList.toggle('d-none');
          refreshHeaders();
        } }, [
          el('i', { class: 'bi bi-chevron-expand text-muted' }),
          thumb,
          el('div', { class: 'flex-grow-1', style: 'min-width:0' }, [titleEl]),
          el('div', { class: 'btn-group btn-group-sm flex-shrink-0' }, [
            el('button', { type: 'button', class: 'btn btn-light border', title: 'Move up', onclick: function () { move(-1); } }, [el('i', { class: 'bi bi-arrow-up' })]),
            el('button', { type: 'button', class: 'btn btn-light border', title: 'Move down', onclick: function () { move(1); } }, [el('i', { class: 'bi bi-arrow-down' })]),
            el('button', { type: 'button', class: 'btn btn-light border text-danger', title: 'Remove', onclick: function () {
              items.splice(items.indexOf(item), 1); card.remove(); refreshHeaders(); setDirty(true);
            } }, [el('i', { class: 'bi bi-trash' })])
          ])
        ]),
        body
      ]);

      item = {
        card: card, titleEl: titleEl, get: form.get,
        peek: form.get,
        refreshThumb: function () {
          if (!thumb) return;
          var url = imageUrl(form.get()[imageKey]);
          if (url) thumb.src = url; else thumb.removeAttribute('src');
        }
      };
      body.addEventListener('input', function () { titleEl.textContent = itemTitle(item, items.indexOf(item)); });
      body.addEventListener('change', function () { item.refreshThumb(); });
      items.push(item);
      container.appendChild(card);
      return item;
    }

    (Array.isArray(values) ? values : []).forEach(function (v) { addItem(v, false); });

    var buttons = [el('button', { type: 'button', class: 'btn btn-sm btn-outline-primary', onclick: function () {
      addItem({}, true); refreshHeaders(); setDirty(true);
    } }, [el('i', { class: 'bi bi-plus-lg' }), ' Add item'])];

    if (f.bulk) {
      var multi = el('input', { type: 'file', multiple: true, accept: 'image/jpeg,image/png,image/gif,image/webp', class: 'd-none' });
      var bulkBtn = el('button', { type: 'button', class: 'btn btn-sm btn-outline-success', onclick: function () { multi.click(); } }, [el('i', { class: 'bi bi-images' }), ' Upload several images']);
      multi.addEventListener('change', function () {
        var files = Array.prototype.slice.call(multi.files);
        if (!files.length) return;
        bulkBtn.disabled = true;
        var done = 0;
        // Upload one by one so the order is kept
        files.reduce(function (p, file) {
          return p.then(function () {
            bulkBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ' + (++done) + '/' + files.length;
            return uploadFile(file).then(function (d) {
              var v = {}; v[f.bulk] = d.path; addItem(v, false); refreshHeaders(); setDirty(true);
            });
          });
        }, Promise.resolve()).catch(fail).finally(function () {
          bulkBtn.disabled = false; bulkBtn.innerHTML = '<i class="bi bi-images"></i> Upload several images'; multi.value = '';
          toast.fire({ icon: 'success', title: 'Images added - click Save section' });
        });
      });
      buttons.push(bulkBtn, multi);
    }

    refreshHeaders();
    var wrap = el('div', { class: 'mb-3 p-2 rounded', style: 'background:#fbfcfe;border:1px solid #edf0f5' }, [
      el('div', { class: 'd-flex flex-wrap justify-content-between align-items-center gap-2 mb-2' }, [
        el('label', { class: 'form-label fw-semibold mb-0' }, [f.label, count]),
        el('div', { class: 'd-flex gap-2' }, buttons)
      ]),
      container
    ]);
    return { el: wrap, get: function () { return items.map(function (i) { return i.get(); }); } };
  }

  /* ---------------- page settings ---------------- */

  document.getElementById('pageSettingsForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = this;
    request('POST', PB.urls.page, new FormData(form)).then(function (d) {
      bootstrap.Modal.getInstance(document.getElementById('pageSettingsModal')).hide();
      toast.fire({ icon: 'success', title: d.message });
      var view = document.getElementById('pb-view');
      if (view && d.url) view.href = d.url;
      var title = form.querySelector('[name=title]').value;
      document.querySelector('.pagetitle h1').textContent = title;
      document.querySelector('.pagetitle .breadcrumb-item.active').textContent = title;
    }).catch(fail);
  });

  var deleteBtn = document.getElementById('pb-delete-page');
  if (deleteBtn) {
    deleteBtn.addEventListener('click', function () {
      Swal.fire({ icon: 'warning', title: 'Delete this page?', text: 'The page and all its sections will be removed. Menu links to it will stop working.', showCancelButton: true, confirmButtonText: 'Delete page', confirmButtonColor: '#d33' })
        .then(function (r) {
          if (!r.isConfirmed) return;
          request('DELETE', PB.urls.deletePage).then(function (d) { setDirty(false); window.location = d.redirect; }).catch(fail);
        });
    });
  }

  renderList();
  // Site-wide page: open the first section straight away
  if (PB.isGlobal && sections.length) openEditor(sections[0].id, true);
})();
