/**
 * Admin > Gallery: albums + photo upload (with in-browser resize and progress), captions, reorder, move, delete.
 */
(function () {
  'use strict';

  var GM = window.GM;
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var albums = GM.albums;
  var selected = albums.length ? albums[0].id : null;
  var MAX_SIDE = 2400;           // resize before upload (server limit friendly)
  var toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 1800, timerProgressBar: true });

  var $ = function (id) { return document.getElementById(id); };
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  function album(id) { return albums.find(function (a) { return a.id === id; }); }

  function request(method, url, body) {
    var isForm = body instanceof FormData;
    return fetch(url, {
      method: method,
      headers: Object.assign({ 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, isForm || !body ? {} : { 'Content-Type': 'application/json' }),
      body: isForm ? body : (body ? JSON.stringify(body) : undefined)
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (d) {
        if (!r.ok) {
          var msg = d.message || 'Could not save (' + r.status + ').';
          if (d.errors) msg = Object.keys(d.errors).map(function (k) { return d.errors[k][0]; }).join('<br>');
          if (r.status === 419) msg = 'Your session expired. Please reload the page.';
          if (r.status === 413) msg = 'This file is too large for the server.';
          throw new Error(msg);
        }
        return d;
      });
    });
  }
  function fail(e) { Swal.fire({ icon: 'error', title: 'Not saved', html: e.message }); }

  /* ================= Albums ================= */

  function renderAlbums() {
    var box = $('gm-albums');
    box.innerHTML = '';
    if (!albums.length) box.innerHTML = '<div class="gm-empty">No albums yet. Create the first one above.</div>';
    albums.forEach(function (a) {
      var cover = a.photos[0] ? '<img class="gm-cover" src="' + esc(a.photos[0].thumb) + '" alt="">' : '<span class="gm-cover"><i class="bi bi-image"></i></span>';
      var el = document.createElement('div');
      el.className = 'gm-album' + (a.id === selected ? ' is-selected' : '') + (a.is_active ? '' : ' is-off');
      el.dataset.id = a.id;
      el.innerHTML =
        '<i class="bi bi-grip-vertical gm-handle" title="Drag to reorder"></i>' + cover +
        '<div class="flex-grow-1" style="min-width:0"><div class="gm-album-title" title="' + esc(a.title) + '">' + esc(a.title) + '</div>' +
        '<div class="gm-album-count">' + a.photos.length + ' photo' + (a.photos.length === 1 ? '' : 's') + (a.is_active ? '' : ' · hidden') + '</div></div>' +
        '<div class="form-check form-switch m-0" title="Show on the gallery page"><input class="form-check-input gm-toggle" type="checkbox" ' + (a.is_active ? 'checked' : '') + ' aria-label="Show on website"></div>' +
        '<div class="dropdown"><button class="btn btn-sm btn-light border" data-bs-toggle="dropdown" aria-label="Album actions"><i class="bi bi-three-dots"></i></button>' +
        '<ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item gm-rename" href="#"><i class="bi bi-pencil"></i> Rename</a></li>' +
        '<li><a class="dropdown-item text-danger gm-delete" href="#"><i class="bi bi-trash"></i> Delete album</a></li></ul></div>';

      el.addEventListener('click', function (e) {
        if (e.target.closest('.gm-toggle, .dropdown, .gm-handle')) return;
        selected = a.id; renderAlbums(); renderPhotos();
      });
      el.querySelector('.gm-toggle').addEventListener('change', function () {
        request('POST', GM.base + '/albums/' + a.id + '/toggle').then(function (d) {
          a.is_active = d.album.is_active; renderAlbums();
          toast.fire({ icon: 'success', title: a.is_active ? 'Album shown on the website' : 'Album hidden' });
        }).catch(fail);
      });
      el.querySelector('.gm-rename').addEventListener('click', function (e) {
        e.preventDefault();
        Swal.fire({ title: 'Rename album', input: 'text', inputValue: a.title, showCancelButton: true, confirmButtonText: 'Save',
          inputValidator: function (v) { return !v.trim() && 'Please enter a title'; } })
          .then(function (r) {
            if (!r.isConfirmed) return;
            request('PUT', GM.base + '/albums/' + a.id, { title: r.value }).then(function (d) {
              a.title = d.album.title; renderAlbums(); renderPhotos(); toast.fire({ icon: 'success', title: 'Renamed' });
            }).catch(fail);
          });
      });
      el.querySelector('.gm-delete').addEventListener('click', function (e) {
        e.preventDefault();
        Swal.fire({ icon: 'warning', title: 'Delete "' + a.title + '"?',
          html: a.photos.length ? 'Its <b>' + a.photos.length + ' photos</b> will be removed from the gallery.' : 'The album is empty.',
          showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#d33' })
          .then(function (r) {
            if (!r.isConfirmed) return;
            request('DELETE', GM.base + '/albums/' + a.id).then(function () {
              albums = albums.filter(function (x) { return x.id !== a.id; });
              if (selected === a.id) selected = albums.length ? albums[0].id : null;
              renderAlbums(); renderPhotos(); toast.fire({ icon: 'success', title: 'Album deleted' });
            }).catch(fail);
          });
      });
      box.appendChild(el);
    });
  }

  Sortable.create($('gm-albums'), {
    handle: '.gm-handle', animation: 150, ghostClass: 'gm-ghost',
    onEnd: function () {
      var ids = Array.prototype.map.call($('gm-albums').querySelectorAll('.gm-album'), function (el) { return +el.dataset.id; });
      albums.sort(function (x, y) { return ids.indexOf(x.id) - ids.indexOf(y.id); });
      request('POST', GM.base + '/albums/reorder', { ids: ids }).then(function () { toast.fire({ icon: 'success', title: 'Order saved' }); }).catch(fail);
    }
  });

  $('gm-new-album').addEventListener('submit', function (e) {
    e.preventDefault();
    var input = this.title, btn = this.querySelector('button');
    if (!input.value.trim()) return;
    btn.disabled = true;
    request('POST', GM.base + '/albums', { title: input.value }).then(function (d) {
      albums.push(d.album); selected = d.album.id; input.value = '';
      renderAlbums(); renderPhotos();
      toast.fire({ icon: 'success', title: 'Album created — now add photos' });
    }).catch(fail).finally(function () { btn.disabled = false; });
  });

  /* ================= Photos ================= */

  function renderPhotos() {
    var a = album(selected);
    $('gm-no-album').classList.toggle('d-none', !!a);
    $('gm-album-tools').classList.toggle('d-none', !a);
    $('gm-album-heading').textContent = a ? a.title.toUpperCase() : 'PHOTOS';
    $('gm-photo-count').textContent = a ? a.photos.length + ' photos' : '';
    if (!a) return;
    var grid = $('gm-grid');
    grid.innerHTML = '';
    if (!a.photos.length) grid.innerHTML = '<div class="gm-empty" style="grid-column:1/-1">No photos yet — drop some above.</div>';
    a.photos.forEach(function (p, i) { grid.appendChild(photoTile(a, p, i)); });
  }

  function photoTile(a, p, i) {
    var el = document.createElement('div');
    el.className = 'gm-photo';
    el.dataset.id = p.id;
    var moveOptions = albums.filter(function (x) { return x.id !== a.id; })
      .map(function (x) { return '<li><a class="dropdown-item gm-move" href="#" data-to="' + x.id + '">' + esc(x.title) + '</a></li>'; }).join('');
    el.innerHTML =
      '<span class="gm-num">' + (i + 1) + '</span>' +
      '<a href="' + esc(p.full) + '" target="_blank" tabindex="-1"><img src="' + esc(p.thumb) + '" alt="" loading="lazy" draggable="false"></a>' +
      '<div class="gm-photo-actions">' +
        (moveOptions ? '<div class="dropdown"><button class="btn btn-light btn-sm" data-bs-toggle="dropdown" title="Move to another album"><i class="bi bi-folder-symlink"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><h6 class="dropdown-header">Move to</h6></li>' + moveOptions + '</ul></div>' : '') +
        '<button class="btn btn-danger btn-sm gm-del" title="Delete photo"><i class="bi bi-trash"></i></button>' +
      '</div>' +
      '<input class="gm-caption" placeholder="Add a caption…" maxlength="255" value="' + esc(p.caption || '') + '" aria-label="Caption">';

    // Clicking the photo opens it; dragging reorders (handled by Sortable)
    el.querySelector('a').addEventListener('click', function (e) { if (el.classList.contains('gm-dragging')) e.preventDefault(); });

    var cap = el.querySelector('.gm-caption');
    var saveCaption = function () {
      if ((cap.value.trim()) === (p.caption || '')) return;
      request('PUT', GM.base + '/photos/' + p.id, { caption: cap.value }).then(function (d) {
        p.caption = d.photo.caption; cap.classList.remove('gm-saved'); void cap.offsetWidth; cap.classList.add('gm-saved');
      }).catch(fail);
    };
    cap.addEventListener('change', saveCaption);
    cap.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); cap.blur(); saveCaption(); } });

    el.querySelector('.gm-del').addEventListener('click', function () {
      Swal.fire({ icon: 'warning', title: 'Delete this photo?', imageUrl: p.thumb, imageHeight: 120, showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#d33' })
        .then(function (r) {
          if (!r.isConfirmed) return;
          request('DELETE', GM.base + '/photos/' + p.id).then(function () {
            a.photos = a.photos.filter(function (x) { return x.id !== p.id; });
            renderAlbums(); renderPhotos(); toast.fire({ icon: 'success', title: 'Photo deleted' });
          }).catch(fail);
        });
    });
    el.querySelectorAll('.gm-move').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        var to = album(+link.dataset.to);
        request('PUT', GM.base + '/photos/' + p.id, { album_id: to.id }).then(function (d) {
          a.photos = a.photos.filter(function (x) { return x.id !== p.id; });
          to.photos.push(d.photo);
          renderAlbums(); renderPhotos(); toast.fire({ icon: 'success', title: 'Moved to ' + to.title });
        }).catch(fail);
      });
    });
    return el;
  }

  Sortable.create($('gm-grid'), {
    animation: 150, ghostClass: 'gm-ghost', filter: '.gm-caption, .gm-photo-actions', preventOnFilter: false,
    onStart: function (e) { e.item.classList.add('gm-dragging'); },
    onEnd: function (e) {
      setTimeout(function () { e.item.classList.remove('gm-dragging'); }, 0);
      var a = album(selected);
      var ids = Array.prototype.map.call($('gm-grid').querySelectorAll('.gm-photo'), function (el) { return +el.dataset.id; });
      a.photos.sort(function (x, y) { return ids.indexOf(x.id) - ids.indexOf(y.id); });
      request('POST', GM.base + '/albums/' + a.id + '/photos/reorder', { ids: ids }).then(function () {
        renderAlbums(); renderPhotos(); toast.fire({ icon: 'success', title: 'Order saved' });
      }).catch(fail);
    }
  });

  /* ================= Upload ================= */

  // Resize big photos in the browser (fast upload, works with small server limits)
  function prepare(file) {
    return new Promise(function (resolve) {
      if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { resolve(file); return; }
      var url = URL.createObjectURL(file), img = new Image();
      img.onload = function () {
        URL.revokeObjectURL(url);
        var w = img.naturalWidth, h = img.naturalHeight, scale = Math.min(1, MAX_SIDE / Math.max(w, h));
        if (scale === 1 && file.size < 3 * 1024 * 1024) { resolve(file); return; }
        var c = document.createElement('canvas');
        c.width = Math.round(w * scale); c.height = Math.round(h * scale);
        var ctx = c.getContext('2d');
        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(img, 0, 0, c.width, c.height);
        c.toBlob(function (blob) {
          resolve(blob ? new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file);
        }, 'image/jpeg', 0.88);
      };
      img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }

  function uploadOne(a, file, row) {
    return prepare(file).then(function (ready) {
      return new Promise(function (resolve) {
        var fd = new FormData(); fd.append('file', ready);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', GM.base + '/albums/' + a.id + '/photos');
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
        xhr.setRequestHeader('Accept', 'application/json');
        var bar = row.querySelector('.progress-bar');
        xhr.upload.onprogress = function (e) { if (e.lengthComputable) bar.style.width = Math.round(e.loaded / e.total * 90) + '%'; };
        xhr.onload = function () {
          var d = {}; try { d = JSON.parse(xhr.responseText); } catch (e) {}
          if (xhr.status === 200 && d.success) {
            bar.style.width = '100%'; bar.classList.add('bg-success');
            row.querySelector('.gm-qstate').innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
            a.photos.push(d.photo);
            if (a.id === selected) renderPhotos();
            renderAlbums();
            resolve(true);
          } else {
            var msg = (d.errors && d.errors.file && d.errors.file[0]) || d.message || (xhr.status === 413 ? 'File too large for the server' : 'Upload failed');
            bar.classList.add('bg-danger'); bar.style.width = '100%';
            row.querySelector('.gm-qstate').innerHTML = '<span class="text-danger">' + esc(msg) + '</span>';
            resolve(false);
          }
        };
        xhr.onerror = function () { bar.classList.add('bg-danger'); row.querySelector('.gm-qstate').innerHTML = '<span class="text-danger">Network error</span>'; resolve(false); };
        xhr.send(fd);
      });
    });
  }

  function handleFiles(fileList) {
    var a = album(selected);
    var files = Array.prototype.slice.call(fileList).filter(function (f) { return /^image\//.test(f.type); });
    if (!a || !files.length) return;
    var queue = $('gm-queue');
    var rows = files.map(function (f) {
      var row = document.createElement('div');
      row.className = 'gm-qitem';
      row.innerHTML = '<span class="text-truncate" style="max-width:40%">' + esc(f.name) + '</span><div class="progress"><div class="progress-bar" style="width:0%"></div></div><span class="gm-qstate small text-muted">waiting</span>';
      queue.appendChild(row);
      return row;
    });
    var ok = 0;
    // One after another keeps the order and the server load low
    files.reduce(function (p, f, i) {
      return p.then(function () {
        rows[i].querySelector('.gm-qstate').textContent = 'uploading…';
        return uploadOne(a, f, rows[i]).then(function (good) { if (good) ok++; });
      });
    }, Promise.resolve()).then(function () {
      toast.fire({ icon: ok === files.length ? 'success' : 'warning', title: ok + ' of ' + files.length + ' photos uploaded' });
      setTimeout(function () { rows.forEach(function (r) { if (r.querySelector('.bg-success')) r.remove(); }); }, 2500);
    });
  }

  var drop = $('gm-drop'), input = $('gm-files');
  drop.addEventListener('click', function () { input.click(); });
  drop.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
  input.addEventListener('change', function () { handleFiles(input.files); input.value = ''; });
  ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-drag'); }); });
  ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-drag'); }); });
  drop.addEventListener('drop', function (e) { handleFiles(e.dataTransfer.files); });

  renderAlbums();
  renderPhotos();
})();
