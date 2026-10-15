/**
 * BICS registration: 4-step form, automatic photo crop, AJAX submit with success pop-up.
 * Works without this file too (the form posts normally); this adds the better experience.
 */
(function () {
  'use strict';

  var REG = window.REG || {};
  var form = document.getElementById('regForm');
  if (!form) return;

  var panels = Array.prototype.slice.call(form.querySelectorAll('.reg-panel'));
  var steps = Array.prototype.slice.call(document.querySelectorAll('.reg-step'));
  var bar = document.querySelector('.reg-progress-bar');
  var btnNext = document.getElementById('reg-next');
  var btnBack = document.getElementById('reg-back');
  var btnSubmit = document.getElementById('reg-submit');
  var counter = document.getElementById('reg-count');
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || form.querySelector('[name=_token]').value;
  var total = panels.length;
  var current = 1;

  var PHOTO_SIZE = 600;               // output: 600 x 600 px
  var PHOTO_MAX_BYTES = 950 * 1024;   // server limit is 1 MB
  var photoBlob = null;               // cropped photo to upload
  var existingPhoto = null;           // returning participant: the photo we already have (URL), kept unless replaced
  var photoSource = null;             // original image (for "Adjust")

  var swalBase = {
    customClass: { popup: 'reg-swal' },
    buttonsStyling: true,
    didOpen: function (popup) {
      if (!popup.querySelector('.reg-swal-top')) {
        var top = document.createElement('div'); top.className = 'reg-swal-top'; popup.prepend(top);
      }
    }
  };
  function swal(opts) { return Swal.fire(Object.assign({}, swalBase, opts)); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function $(id) { return document.getElementById(id); }

  /* ------------------------------------------------------------------ */
  /* Steps                                                              */
  /* ------------------------------------------------------------------ */

  function goTo(step, focus) {
    current = Math.max(1, Math.min(total, step));
    panels.forEach(function (p) { p.classList.toggle('is-active', +p.dataset.step === current); });
    steps.forEach(function (s) {
      var n = +s.dataset.step;
      s.classList.toggle('is-active', n === current);
      s.classList.toggle('is-done', n < current);
      s.setAttribute('aria-current', n === current ? 'step' : 'false');
    });
    bar.style.width = (current / total * 100) + '%';
    btnBack.hidden = current === 1;
    btnNext.hidden = current === total;
    btnSubmit.hidden = current !== total;
    counter.textContent = 'Step ' + current + ' of ' + total;
    if (current === total) renderReview();

    var card = document.querySelector('.reg-card');
    if (card.getBoundingClientRect().top < 0) window.scrollTo({ top: window.scrollY + card.getBoundingClientRect().top - 90, behavior: 'smooth' });
    if (focus !== false) {
      var first = panels[current - 1].querySelector('input:not([type=hidden]):not(.btn-check):not([type=file]), select, textarea');
      if (first && window.innerWidth > 768) setTimeout(function () { try { first.focus({ preventScroll: true }); } catch (e) {} }, 60);
    }
  }

  btnNext.addEventListener('click', function () { if (validateStep(current)) goTo(current + 1); });
  btnBack.addEventListener('click', function () { goTo(current - 1); });
  steps.forEach(function (s) {
    s.addEventListener('click', function () { if (+s.dataset.step < current) goTo(+s.dataset.step); });
  });
  // Enter moves to the next step instead of submitting early
  form.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type !== 'checkbox' && current < total) {
      e.preventDefault(); btnNext.click();
    }
  });

  /* ------------------------------------------------------------------ */
  /* Validation                                                         */
  /* ------------------------------------------------------------------ */

  var rules = {
    1: ['title', 'firstname', 'lastname', 'gender', 'birthday', 'country', 'phone', 'email'],
    2: ['profession', 'institute'],
    3: [],
    4: ['consent']
  };
  var labels = { title: 'title', firstname: 'first name', lastname: 'last name', gender: 'gender', birthday: 'date of birth', country: 'country', phone: 'mobile number', email: 'email address', profession: 'profession', institute: 'institution name' };

  function value(name) {
    var els = form.querySelectorAll('[name="' + name + '"]');
    if (!els.length) return '';
    if (els[0].type === 'radio') { var c = form.querySelector('[name="' + name + '"]:checked'); return c ? c.value : ''; }
    return (els[0].value || '').trim();
  }

  function check(name) {
    if (name === 'consent') return $('reg-consent').checked ? '' : 'Please confirm your details to continue.';
    if (name === 'birthday') {
      var missing = [['reg-dob-d', 'day'], ['reg-dob-m', 'month'], ['reg-dob-y', 'year']]
        .filter(function (p) { return !$(p[0]).value; }).map(function (p) { return p[1]; });
      if (missing.length === 3) return 'Please select your date of birth.';
      if (missing.length) return 'Please select the ' + missing.join(' and ') + ' of your birth.';
    }
    var v = value(name);
    if (!v) return (['country', 'profession', 'title', 'gender'].indexOf(name) > -1 ? 'Please select your ' : 'Please enter your ') + labels[name] + '.';
    if (name === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return 'Please enter a valid email address.';
    if (name === 'phone' && !/^[0-9\s\-]{5,20}$/.test(v)) return 'Use digits only (5–20), without the country code.';
    if (name === 'birthday') {
      var d = new Date(v + 'T00:00:00'), today = new Date();
      if (isNaN(d) || d.getFullYear() < 1900) return 'Please enter a valid date of birth.';
      if (d >= today) return 'Date of birth must be in the past.';
      var age = (today - d) / 31557600000;
      if (age < 16) return 'Please check the year — you appear to be under 16.';
    }
    return '';
  }

  function fieldEl(name) {
    if (name === 'country') return countrySelect && countrySelect.wrapper ? countrySelect.wrapper : $('reg-country');
    if (name === 'birthday') return $('reg-dob');
    if (name === 'consent') return $('reg-consent');
    return form.querySelector('[name="' + name + '"]:not(.btn-check)');
  }

  // Element that should receive the cursor for a field (first empty Day/Month/Year for date of birth)
  function focusTarget(name, el) {
    if (name === 'birthday') return [$('reg-dob-d'), $('reg-dob-m'), $('reg-dob-y')].filter(function (s) { return !s.value; })[0] || $('reg-dob-d');
    return el && el.focus ? el : null;
  }

  function setError(name, msg) {
    var box = form.querySelector('[data-error="' + name + '"]');
    if (box) box.textContent = msg || '';
    var el = fieldEl(name);
    if (el) {
      el.classList.toggle('is-invalid', !!msg);
      if (el.setAttribute) el.setAttribute('aria-invalid', msg ? 'true' : 'false');
    }
  }

  function validateStep(step) {
    var firstBad = null;
    (rules[step] || []).forEach(function (name) {
      var msg = check(name);
      setError(name, msg);
      if (msg && !firstBad) firstBad = name;
    });
    if (firstBad) {
      var el = fieldEl(firstBad);
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        var focusable = firstBad === 'country' && countrySelect ? null : focusTarget(firstBad, el);
        if (focusable) setTimeout(function () { focusable.focus({ preventScroll: true }); }, 250);
        if (firstBad === 'country' && countrySelect) countrySelect.focus();
      }
      shake();
      return false;
    }
    return true;
  }

  function shake() {
    var p = panels[current - 1];
    p.animate && p.animate([{ transform: 'translateX(0)' }, { transform: 'translateX(-6px)' }, { transform: 'translateX(6px)' }, { transform: 'translateX(0)' }], { duration: 260 });
  }

  // Clear an error as soon as the person fixes it
  form.addEventListener('input', function (e) { if (e.target.name && form.querySelector('[data-error="' + e.target.name + '"]')) setError(e.target.name, ''); });
  form.addEventListener('change', function (e) {
    if (e.target.id === 'reg-consent') setError('consent', '');
    else if (e.target.name) setError(e.target.name, '');
  });

  /* ------------------------------------------------------------------ */
  /* Country (searchable) + dial code                                   */
  /* ------------------------------------------------------------------ */

  var countrySelect = null;
  var dialEl = $('reg-dial');
  var codeEl = $('reg-countryCode');
  function applyDial() {
    var opt = $('reg-country').selectedOptions[0];
    var dial = opt && opt.dataset.dial ? opt.dataset.dial : '';
    dialEl.textContent = dial || '+ ··';
    codeEl.value = dial;
  }
  if (window.TomSelect) {
    countrySelect = new TomSelect('#reg-country', {
      // Attach the list to <body> so the card edge and the sticky buttons can never cover or clip it
      dropdownParent: 'body',
      dropdownClass: 'ts-dropdown reg-ts-dropdown',
      maxOptions: 300,
      allowEmptyOption: false,
      placeholder: 'Search your country…',
      searchField: ['text'],
      render: {
        option: function (data, escape) {
          var dial = data.$option ? data.$option.dataset.dial : '';
          return '<div><span>' + escape(data.text) + '</span><span class="reg-opt-dial">' + escape(dial || '') + '</span></div>';
        },
        no_results: function () { return '<div class="no-results p-2 text-muted">No country found</div>'; }
      },
      onChange: function () { applyDial(); setError('country', ''); if ($('reg-phone').value === '') setTimeout(function () { $('reg-phone').focus(); }, 50); }
    });
  } else {
    $('reg-country').classList.add('form-select');
    $('reg-country').addEventListener('change', applyDial);
  }
  applyDial();

  // Phone: digits, spaces and dashes only
  $('reg-phone').addEventListener('input', function () {
    var clean = this.value.replace(/[^0-9\s\-]/g, '');
    if (clean !== this.value) this.value = clean;
  });

  /* ------------------------------------------------------------------ */
  /* Date of birth: Day / Month / Year lists -> hidden YYYY-MM-DD       */
  /* ------------------------------------------------------------------ */

  var dobD = $('reg-dob-d'), dobM = $('reg-dob-m'), dobY = $('reg-dob-y');
  var dobHidden = $('reg-birthday'), ageEl = $('reg-age');

  function daysIn(month, year) { return new Date(year || 2000, month, 0).getDate(); } // 2000 = leap year, allows 29 Feb until a year is chosen

  function syncDob(e) {
    // Hide days that do not exist in the chosen month (e.g. 31 April, 29 Feb in non-leap years)
    if (dobM.value) {
      var max = daysIn(+dobM.value, +dobY.value);
      Array.prototype.forEach.call(dobD.options, function (o) { if (o.value) o.hidden = o.disabled = +o.value > max; });
      if (+dobD.value > max) dobD.value = '';
    }
    [dobD, dobM, dobY].forEach(function (s) { s.classList.toggle('is-filled', !!s.value); });

    if (dobD.value && dobM.value && dobY.value) {
      var pad = function (n) { return (n < 10 ? '0' : '') + n; };
      dobHidden.value = dobY.value + '-' + pad(+dobM.value) + '-' + pad(+dobD.value);
      var born = new Date(+dobY.value, +dobM.value - 1, +dobD.value), now = new Date();
      var age = now.getFullYear() - born.getFullYear() - ((now.getMonth() < born.getMonth() || (now.getMonth() === born.getMonth() && now.getDate() < born.getDate())) ? 1 : 0);
      ageEl.innerHTML = '<i class="bi bi-check-circle-fill"></i>' + age + ' years old';
      ageEl.hidden = false;
      setError('birthday', '');
    } else {
      dobHidden.value = '';
      ageEl.hidden = true;
      if (e && $('reg-dob').classList.contains('is-invalid')) setError('birthday', check('birthday'));
    }

    // Move on to the next empty list automatically
    if (e && e.target.value) {
      var next = { 'reg-dob-d': dobM, 'reg-dob-m': dobY }[e.target.id];
      if (next && !next.value && window.innerWidth > 768) next.focus();
    }
  }
  [dobD, dobM, dobY].forEach(function (s) { s.addEventListener('change', syncDob); });
  syncDob();

  /* ------------------------------------------------------------------ */
  /* Profession -> speciality                                           */
  /* ------------------------------------------------------------------ */

  var spec = $('reg-specialty');
  function loadSpecialities(keep) {
    var opt = $('reg-profession').selectedOptions[0];
    var id = opt && opt.dataset.id;
    if (!id) return;
    // Lists come with the page (Admin > Form Options), so this is instant
    var list = (REG.specialities && REG.specialities[id]) || [];
    spec.innerHTML = '<option value="">' + (list.length ? 'Select speciality' : 'Not applicable') + '</option>' +
      list.map(function (s) { return '<option value="' + esc(s) + '">' + esc(s) + '</option>'; }).join('');
    spec.disabled = !list.length;
    if (keep) spec.value = keep;
  }
  $('reg-profession').addEventListener('change', function () { loadSpecialities(''); });
  if ($('reg-profession').value) loadSpecialities(spec.dataset.old);

  /* ID type changes the number label */
  $('reg-idtype').addEventListener('change', function () {
    $('reg-idno-label').textContent = this.value ? this.value : 'ID number';
    $('reg-idno').placeholder = this.value ? 'Enter ' + this.value.toLowerCase() : 'Optional';
  });

  /* ------------------------------------------------------------------ */
  /* Photo: automatic square crop, optional manual adjust               */
  /* ------------------------------------------------------------------ */

  var fileInput = $('reg-image');
  var drop = $('reg-drop');
  var photoBox = $('reg-photo');
  var avatar = $('reg-avatar');
  var cropModalEl = $('cropModal');
  var cropModal = window.bootstrap ? new bootstrap.Modal(cropModalEl) : null;
  var cropImg = $('reg-crop-img');
  var cropper = null;

  function loadImage(file) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () { resolve(img); };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('unreadable')); };
      img.src = url;
    });
  }

  function toJpeg(canvas) {
    // Lower the quality until the photo is under the size limit
    return new Promise(function (resolve) {
      var q = 0.92;
      (function attempt() {
        canvas.toBlob(function (blob) {
          if (blob.size > PHOTO_MAX_BYTES && q > 0.5) { q -= 0.08; attempt(); } else resolve(blob);
        }, 'image/jpeg', q);
      })();
    });
  }

  // Centered square; for portrait photos keep the upper part (where the face usually is)
  function autoCrop(img) {
    var w = img.naturalWidth, h = img.naturalHeight, side = Math.min(w, h);
    var sx = (w - side) / 2;
    var sy = h > w ? (h - side) * 0.25 : (h - side) / 2;
    var canvas = document.createElement('canvas');
    canvas.width = canvas.height = PHOTO_SIZE;
    var ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, PHOTO_SIZE, PHOTO_SIZE);
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(img, sx, sy, side, side, 0, 0, PHOTO_SIZE, PHOTO_SIZE);
    return toJpeg(canvas);
  }

  function showPhoto(blob) {
    photoBlob = blob;
    var url = URL.createObjectURL(blob);
    avatar.innerHTML = '<img src="' + url + '" alt="Your photo">';
    photoBox.classList.add('has-photo');
    drop.querySelector('.reg-drop-title').textContent = 'Photo ready';
    drop.querySelector('.reg-drop-sub').innerHTML = 'Cropped to ' + PHOTO_SIZE + '×' + PHOTO_SIZE + ' · ' + Math.max(1, Math.round(blob.size / 1024)) + ' KB';
    $('reg-photo-actions').classList.remove('d-none');
    var adjust = $('reg-adjust'); if (adjust) adjust.classList.remove('d-none');
    setError('image', '');
  }

  function handleFile(file) {
    if (!file) return;
    if (!/^image\/(jpe?g|png|webp)$/i.test(file.type)) { setError('image', 'Please choose a JPG, PNG or WEBP image.'); return; }
    if (file.size > 15 * 1024 * 1024) { setError('image', 'That image is very large (over 15 MB). Please choose a smaller one.'); return; }
    drop.querySelector('.reg-drop-title').textContent = 'Processing…';
    loadImage(file).then(function (img) {
      if (img.naturalWidth < 150 || img.naturalHeight < 150) {
        setError('image', 'This photo is too small (min. 150 × 150 px). Please choose a clearer one.');
        resetPhoto(); return;
      }
      photoSource = img;
      return autoCrop(img).then(showPhoto);
    }).catch(function () {
      setError('image', 'We could not read this image. Please try another photo.');
      resetPhoto();
    });
  }

  function resetPhoto() {
    photoBlob = null; photoSource = null; fileInput.value = '';
    if (existingPhoto) { existingPhoto = null; var kp = $('reg-keep-photo'); if (kp) kp.value = '0'; }
    avatar.innerHTML = '<i class="bi bi-person-bounding-box"></i>';
    photoBox.classList.remove('has-photo');
    drop.querySelector('.reg-drop-title').textContent = 'Upload your photo';
    drop.querySelector('.reg-drop-sub').innerHTML = 'Drag &amp; drop or <u>browse</u><br>JPG, PNG or WEBP';
    $('reg-photo-actions').classList.add('d-none');
  }

  fileInput.addEventListener('change', function () { handleFile(fileInput.files[0]); });
  ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-drag'); }); });
  ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-drag'); }); });
  drop.addEventListener('drop', function (e) { handleFile(e.dataTransfer.files[0]); });
  $('reg-change').addEventListener('click', function () { fileInput.click(); });
  $('reg-remove').addEventListener('click', resetPhoto);

  $('reg-adjust').addEventListener('click', function () {
    if (!photoSource || !window.Cropper || !cropModal) return;
    cropImg.src = photoSource.src;
    cropModal.show();
  });
  cropModalEl.addEventListener('shown.bs.modal', function () {
    if (cropper) cropper.destroy();
    cropper = new Cropper(cropImg, {
      aspectRatio: 1, viewMode: 1, dragMode: 'move', autoCropArea: 0.85, background: false,
      guides: false, center: false, highlight: false, toggleDragModeOnDblclick: false, responsive: true
    });
  });
  cropModalEl.addEventListener('hidden.bs.modal', function () { if (cropper) { cropper.destroy(); cropper = null; } });
  cropModalEl.querySelectorAll('[data-crop]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (!cropper) return;
      var a = b.dataset.crop;
      if (a === 'zoom-in') cropper.zoom(0.1);
      if (a === 'zoom-out') cropper.zoom(-0.1);
      if (a === 'rotate') cropper.rotate(90);
      if (a === 'reset') cropper.reset();
    });
  });
  $('reg-crop-apply').addEventListener('click', function () {
    if (!cropper) return;
    var canvas = cropper.getCroppedCanvas({ width: PHOTO_SIZE, height: PHOTO_SIZE, fillColor: '#fff', imageSmoothingQuality: 'high' });
    toJpeg(canvas).then(function (blob) { showPhoto(blob); cropModal.hide(); });
  });

  /* ------------------------------------------------------------------ */
  /* Review                                                             */
  /* ------------------------------------------------------------------ */

  function renderReview() {
    function item(label, v) {
      return '<div class="reg-review-item"><small>' + esc(label) + '</small>' +
        (v ? '<span>' + esc(v) + '</span>' : '<span class="reg-empty">Not provided</span>') + '</div>';
    }
    function group(title, step, body) {
      return '<div class="reg-review-group"><div class="reg-review-head">' + esc(title) +
        '<button type="button" data-goto="' + step + '"><i class="bi bi-pencil"></i> Edit</button></div>' +
        '<div class="reg-review-body">' + body + '</div></div>';
    }
    var phone = value('phone') ? (codeEl.value ? codeEl.value + ' ' : '') + value('phone') : '';
    var photo = (photoBlob || existingPhoto) && avatar.querySelector('img')
      ? '<img src="' + avatar.querySelector('img').src + '" alt="Your photo">'
      : '<span class="reg-noimg"><i class="bi bi-person"></i></span>';
    var idText = value('identification_type') ? value('identification_type') + (value('identification_no') ? ' — ' + value('identification_no') : '') : '';

    $('reg-review').innerHTML =
      group('Personal', 1,
        item('Name', [value('title'), value('firstname'), value('lastname')].filter(Boolean).join(' ')) +
        item('Gender', value('gender')) +
        item('Date of birth', value('birthday') ? new Date(value('birthday') + 'T00:00:00').toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' }) : '') +
        item('Country', value('country')) + item('Mobile', phone) + item('Email', value('email'))) +
      group('Professional', 2,
        item('Profession', value('profession')) + item('Speciality', value('specialty')) + item('Designation', value('designation')) +
        item('Institution', value('institute')) + item('Institution address', value('institute_address'))) +
      group('Photo & ID', 3,
        '<div class="reg-review-item reg-review-photo">' + photo + '<div><small>Photo</small>' +
          (photoBlob ? '<span>Ready</span>' : existingPhoto ? '<span>Your saved photo</span>' : '<span class="reg-empty">No photo — your card will be printed without one</span>') + '</div></div>' +
        item('Identification', idText));

    $('reg-review').querySelectorAll('[data-goto]').forEach(function (b) {
      b.addEventListener('click', function () { goTo(+b.dataset.goto); });
    });
  }

  /* ------------------------------------------------------------------ */
  /* Submit                                                             */
  /* ------------------------------------------------------------------ */

  var stepOf = { title: 1, firstname: 1, lastname: 1, gender: 1, birthday: 1, country: 1, phone: 1, countryCode: 1, email: 1,
    profession: 2, specialty: 2, designation: 2, institute: 2, institute_address: 2,
    image: 3, identification_type: 3, identification_no: 3 };

  var fieldNames = { title: 'Title', firstname: 'First name', lastname: 'Last name', gender: 'Gender', birthday: 'Date of birth',
    country: 'Country', countryCode: 'Country code', phone: 'Mobile number', email: 'Email address', profession: 'Profession',
    specialty: 'Speciality', designation: 'Designation', institute: 'Institution name', institute_address: 'Institution address',
    image: 'Photo', identification_type: 'ID type', identification_no: 'ID number', consent: 'Confirmation' };
  var stepNames = { 1: 'Personal', 2: 'Professional', 3: 'Photo & ID', 4: 'Review' };
  stepOf.consent = 4;

  // Jump to the step that holds a field and put the cursor in it
  function focusField(name) {
    if (stepOf[name]) goTo(stepOf[name], false);
    setTimeout(function () {
      var el = fieldEl(name);
      if (!el) return;
      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      if (name === 'country' && countrySelect) countrySelect.focus();
      else { var t = focusTarget(name, el); if (t) try { t.focus({ preventScroll: true }); } catch (e) {} }
    }, 120);
  }

  // Pop-up listing every problem: field name, what is wrong, and a link to fix it
  function showErrorList(list, title, intro) {
    var firstField = null;
    var items = list.map(function (err, i) {
      if (!firstField && err.field && stepOf[err.field]) firstField = err.field;
      var where = err.field && stepOf[err.field]
        ? '<button type="button" class="reg-err-go" data-field="' + esc(err.field) + '">' + esc(fieldNames[err.field] || err.field) +
          ' <span>· ' + esc(stepNames[stepOf[err.field]]) + '</span> <i class="bi bi-arrow-right-short"></i></button>'
        : '';
      return '<li class="reg-err-item"><i class="bi bi-x-circle-fill"></i><div>' + where + '<div class="reg-err-msg">' + esc(err.message) + '</div></div></li>';
    }).join('');
    return swal({
      icon: 'warning',
      title: title || 'Please fix ' + (list.length === 1 ? 'this' : 'these ' + list.length) + ' item' + (list.length === 1 ? '' : 's'),
      html: (intro ? '<p class="mb-2">' + intro + '</p>' : '') + '<ul class="reg-err-list">' + items + '</ul>',
      confirmButtonText: firstField ? 'Fix ' + (fieldNames[firstField] || 'it').toLowerCase() : 'OK',
      didOpen: function (popup) {
        swalBase.didOpen(popup);
        popup.querySelectorAll('.reg-err-go').forEach(function (b) {
          b.addEventListener('click', function () { Swal.close(); focusField(b.dataset.field); });
        });
      }
    }).then(function (r) { if (r.isConfirmed && firstField) focusField(firstField); });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    // Check every step and list all problems at once
    var problems = [];
    for (var s = 1; s <= total; s++) {
      (rules[s] || []).forEach(function (name) {
        var msg = check(name);
        setError(name, msg);
        if (msg) problems.push({ field: name, message: msg });
      });
    }
    if (problems.length) { showErrorList(problems); return; }

    // Browser autofill must never trip the spam trap for a real person
    var hp = $('reg-hp'); if (hp) hp.value = '';

    var data = new FormData(form);
    data.delete('image');
    if (photoBlob) data.append('image', new File([photoBlob], 'photo.jpg', { type: 'image/jpeg' }));

    btnSubmit.disabled = true;
    swal({
      title: 'Submitting your registration',
      html: 'Creating your verification card and sending your confirmation email.<br>This takes a few seconds…',
      allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false,
      customClass: { popup: 'reg-swal reg-swal-loading' },
      didOpen: function (popup) { swalBase.didOpen(popup); Swal.showLoading(); }
    });

    fetch(form.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }, body: data })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (body) { return { status: r.status, body: body }; }); })
      .then(function (res) {
        btnSubmit.disabled = false;
        if (window.FormGuard) window.FormGuard.reset(form);   // a fresh "I am human" check for the next try
        if (res.status === 200 && res.body.success) return success(res.body);
        if (res.status === 422) return showServerErrors(res.body);
        if (res.status === 429) return swal({ icon: 'warning', title: 'Too many attempts', text: 'Please wait a minute and try again.', confirmButtonText: 'OK' });
        if (res.status === 419) return swal({ icon: 'warning', title: 'Page expired', text: 'For your security this page expired. Please reload it and submit again.', confirmButtonText: 'Reload page' }).then(function () { location.reload(); });
        return swal({ icon: 'error', title: 'Something went wrong', text: 'Your registration was not completed. Please try again in a moment.', confirmButtonText: 'OK' });
      })
      .catch(function () {
        btnSubmit.disabled = false;
        swal({ icon: 'error', title: 'No connection', text: 'We could not reach the server. Check your internet connection and try again.', confirmButtonText: 'OK' });
      });
  });

  function showServerErrors(body) {
    var errors = body.errors || {};
    var names = Object.keys(errors);
    names.forEach(function (n) { setError(n, errors[n][0]); });

    // Duplicate email gets its own friendly message
    if (names.length === 1 && names[0] === 'email' && /already/i.test(errors.email[0])) {
      return swal({
        icon: 'info', title: 'Already registered',
        html: esc(errors.email[0]) + '<br><small class="text-muted">If you registered before, check your inbox for your registration number.</small>',
        confirmButtonText: 'Use another email'
      }).then(function () { focusField('email'); });
    }

    var list = [];
    names.forEach(function (n) { errors[n].forEach(function (m) { list.push({ field: n, message: m }); }); });
    // A server message without field details is still shown, never an empty list
    if (!list.length) list.push({ field: null, message: body.message || 'The server could not accept the form. Please reload the page and try again.' });
    showErrorList(list, null, 'The server found ' + (list.length === 1 ? 'a problem' : list.length + ' problems') + ' with your details:');
  }

  // Admin text -> safe HTML: the text is escaped, then {name} {email} {code} {event} become bold values
  function fill(template, d) {
    var values = { name: d.name, email: d.email, code: d.code, event: REG.eventName };
    return esc(template || '').replace(/\{(name|email|code|event)\}/g, function (m, key) { return '<b>' + esc(values[key] || '') + '</b>'; })
      .replace(/\r?\n/g, '<br>');
  }

  function success(d) {
    // Texts come from Admin > Website Pages > Site-wide > Registration success message
    var t = REG.success || {};
    var on = function (key) { return t[key] !== false && t[key] !== 0 && t[key] !== '0'; };
    var html = '';
    if (t.welcome) html += '<div class="reg-success-name">' + fill(t.welcome, d) + '</div>';
    if (on('show_code')) {
      html += '<div class="reg-ticket">' + (t.code_label ? '<small>' + esc(t.code_label) + '</small>' : '') +
        '<div class="reg-ticket-code">' + esc(d.code) + '</div>' +
        (on('show_copy') ? '<button type="button" class="reg-copy" id="reg-copy"><i class="bi bi-clipboard"></i> Copy</button>' : '') + '</div>';
    }
    if (on('show_email')) {
      html += d.email_sent
        ? '<div class="reg-mail ok"><i class="bi bi-envelope-check-fill"></i><div>' + fill(t.email_sent, d) + '</div></div>'
        : '<div class="reg-mail warn"><i class="bi bi-exclamation-circle-fill"></i><div>' + fill(t.email_failed || d.message, d) + '</div></div>';
    }
    if (t.note) html += '<p class="small text-muted mt-3 mb-0">' + fill(t.note, d) + '</p>';

    var showDownload = on('show_download') && !!d.card_url;
    swal({
      icon: 'success',
      title: fill(t.title || 'Registration complete', d),
      html: html,
      showDenyButton: showDownload,
      denyButtonText: '<i class="bi bi-download"></i> ' + esc(t.download_label || 'Download card'),
      confirmButtonText: esc(t.done_label || 'Done'),
      allowOutsideClick: false,
      preDeny: function () { window.open(d.card_url, '_blank', 'noopener'); return false; },
      didOpen: function (popup) {
        swalBase.didOpen(popup);
        var copy = popup.querySelector('#reg-copy');
        if (copy) copy.addEventListener('click', function () {
          var done = function () { copy.innerHTML = '<i class="bi bi-check2"></i> Copied'; setTimeout(function () { copy.innerHTML = '<i class="bi bi-clipboard"></i> Copy'; }, 1800); };
          if (navigator.clipboard) navigator.clipboard.writeText(d.code).then(done, done); else done();
        });
      }
    }).then(function () {
      // Admin-chosen page, or a fresh form for the next person
      window.location.href = t.done_url ? t.done_url_full : window.location.pathname;
    });
  }

  /* ------------------------------------------------------------------ */
  /* Start (open the step with server-side errors, if any)              */
  /* ------------------------------------------------------------------ */

  /* ------------------------------------------------------------------ */
  /* Returning participants: fill the form with saved details           */
  /* ------------------------------------------------------------------ */

  function setRadio(name, v) {
    var r = Array.prototype.filter.call(form.querySelectorAll('input[name="' + name + '"]'), function (x) { return x.value === v; })[0];
    if (r) r.checked = true;
    return !!r;
  }
  function lockField(name) {
    var label = 'From your earlier registration';
    if (name === 'gender' || name === 'title') {
      Array.prototype.forEach.call(form.querySelectorAll('input[name="' + name + '"]'), function (x) { if (!x.checked) x.disabled = true; });
    } else if (name === 'birthday') {
      [dobD, dobM, dobY].forEach(function (sel) { Array.prototype.forEach.call(sel.options, function (o) { if (!o.selected) o.disabled = true; }); sel.classList.add('is-locked'); sel.title = label; });
    } else {
      var el = form.querySelector('[name="' + name + '"]');
      if (el) { el.readOnly = true; el.classList.add('is-locked'); el.title = label; }
    }
    // the note goes under the field (for button groups: under the whole group)
    var holder = fieldEl(name) || form.querySelector('[name="' + name + '"]');
    var anchor = holder && (holder.closest('.reg-segment, .reg-titles, [role=radiogroup], .reg-dob, .reg-input') || holder);
    var col = anchor && anchor.parentNode;
    if (col && !col.querySelector('.reg-locked-note[data-for="' + name + '"]')) {
      var n = document.createElement('div'); n.className = 'reg-locked-note'; n.dataset.for = name; n.innerHTML = '<i class="bi bi-lock-fill"></i> ' + label;
      var err = col.querySelector('[data-error="' + name + '"]');
      col.insertBefore(n, err ? err.nextSibling : anchor.nextSibling);
    }
  }

  window.REGFORM = {
    /** d: values from ReturningRegistration::prefill(); locked: names that cannot change */
    fill: function (d, locked) {
      ['firstname', 'lastname', 'email', 'designation', 'institute', 'institute_address', 'identification_no'].forEach(function (k) {
        var el = form.querySelector('[name="' + k + '"]'); if (el && d[k]) el.value = d[k];
      });
      if (d.title) setRadio('title', d.title);
      if (d.gender) setRadio('gender', d.gender);
      if (d.birthday && /^\d{4}-\d{2}-\d{2}$/.test(d.birthday)) {
        var parts = d.birthday.split('-');
        dobY.value = String(+parts[0]); dobM.value = String(+parts[1]); dobD.value = String(+parts[2]);
        syncDob();
      }
      if (d.country) { if (countrySelect) countrySelect.setValue(d.country); else $('reg-country').value = d.country; applyDial(); }
      if (d.phone) $('reg-phone').value = d.phone;
      if (d.profession) {
        var prof = $('reg-profession');
        if (Array.prototype.some.call(prof.options, function (o) { return o.value === d.profession; })) { prof.value = d.profession; loadSpecialities(d.specialty || ''); }
      }
      if (d.identification_type) { var t = $('reg-idtype'); t.value = d.identification_type; t.dispatchEvent(new Event('change')); }
      if (d.photo_url) {
        existingPhoto = d.photo_url;
        avatar.innerHTML = '<img src="' + esc(d.photo_url) + '" alt="Your photo">';
        photoBox.classList.add('has-photo');
        drop.querySelector('.reg-drop-title').textContent = 'Your saved photo';
        drop.querySelector('.reg-drop-sub').innerHTML = 'We will use it again — or <u>choose a new one</u>';
        $('reg-photo-actions').classList.remove('d-none');
        var adjust = $('reg-adjust'); if (adjust) adjust.classList.add('d-none');
      }
      (locked || []).forEach(function (k) { if (d[k]) lockField(k); });
      form.querySelectorAll('.is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });
      goTo(1, false);
    },
    setAction: function (url) { form.action = url; },
    goTo: goTo
  };

  var start = 1;
  if (REG.hasServerErrors) {
    start = total;
    (REG.errorFields || []).forEach(function (n) { if (stepOf[n] && stepOf[n] < start) start = stepOf[n]; });
  }
  goTo(start, false);
})();
