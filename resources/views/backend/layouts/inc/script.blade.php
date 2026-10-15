<script src="{{ asset('assets/backend') }}/vendor/apexcharts/apexcharts.min.js"></script>
<script src="{{ asset('assets/backend') }}/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('assets/backend') }}/vendor/chart.js/chart.min.js"></script>
<script src="{{ asset('assets/backend') }}/vendor/echarts/echarts.min.js"></script>
<script src="{{ asset('assets/backend') }}/vendor/quill/quill.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js" crossorigin="anonymous"></script>
<script src="{{ asset('assets/backend') }}/vendor/tinymce/tinymce.min.js"></script>
<script src="{{ asset('assets/backend') }}/vendor/php-email-form/validate.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Template Main JS File -->
<script src="{{ asset('assets/backend') }}/js/main.js"></script>
<script>
  // Notification bell: opening it marks everything as read (the count disappears)
  (function () {
    var bell = document.getElementById('adBell'); if (!bell) return;
    bell.addEventListener('shown.bs.dropdown', function () {
      var c = document.getElementById('adBellCount'); if (!c) return;
      fetch(@json(route('notifications.read')), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' } })
        .then(function () { c.remove(); bell.querySelector('i').className = 'bi bi-bell'; bell.setAttribute('aria-label', 'Notifications'); });
    });
  })();

  // Pinned event (header): pin another event, then reopen this page on it
  (function () {
    document.querySelectorAll('[data-pin]').forEach(function (b) {
      b.addEventListener('click', function () {
        if (b.classList.contains('is-on')) return;
        b.disabled = true;
        fetch(@json(route('events.pin')), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ venue: b.dataset.pin }) })
          .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
          .then(function () {
            var u = new URL(location.href);
            ['event', 'venue', 'event_id'].forEach(function (k) { u.searchParams.delete(k); });   // the page should follow the new pin
            location.href = u.toString();
          })
          .catch(function () { b.disabled = false; if (window.Swal) Swal.fire('Not pinned', 'Please try again.', 'warning'); });
      });
    });
  })();

  // Light / dark theme toggle (header). Remembered in this browser; "bics:theme" lets pages (charts) follow.
  (function () {
    var root = document.documentElement, btn = document.getElementById('adThemeToggle');
    function label() {
      if (!btn) return;
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      btn.title = 'Switch to ' + next + ' mode';
      btn.setAttribute('aria-label', btn.title);
    }
    function apply(theme, remember) {
      root.setAttribute('data-theme', theme); // instant switch (no fade: nothing can get stuck half-way)
      if (remember) { try { localStorage.setItem('bics-admin-theme', theme); } catch (e) {} }
      label();
      document.dispatchEvent(new CustomEvent('bics:theme', { detail: theme }));
    }
    if (btn) btn.addEventListener('click', function () { apply(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark', true); });
    // Follow the device setting until the person picks a theme themselves
    if (window.matchMedia) {
      matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
        var saved = null; try { saved = localStorage.getItem('bics-admin-theme'); } catch (err) {}
        if (!saved) apply(e.matches ? 'dark' : 'light', false);
      });
    }
    label();
  })();

$(document).ready(function() {
    let table = new DataTable('.datatable', {
      responsive: {
        details: {
            display: DataTable.Responsive.display.modal({
                header: function (row) {
                    var data = row.data();
                //   return 'Details for ' + data[1] + ' ( ' + data[2]+' )';
                return 'Details';
                }
            }),
            renderer: DataTable.Responsive.renderer.tableAll({
                tableClass: 'table'
            })
        }
    }
    });
  });

  
  
  //Delete Register Info
  //Delete Register Info
  // Works for rows on every table page (delegated), and only reports success when the server agrees
  $(document).on('click', '.deleteRecord', function(e){
    e.preventDefault();
    var id = $(this).data("id");
    var name = $(this).data("name") || 'this registration';
    Swal.fire({
        title: 'Delete ' + name + '?',
        text: "The registration and its files are removed permanently.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d13c4b',
        confirmButtonText: 'Delete',
      }).then((result) => {
        if (!result.isConfirmed) return;
        $.ajax({
            url: "{{ url('/delete_register') }}/" + id,
            type: 'DELETE',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' },
            success: function () {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Registration deleted', showConfirmButton: false, timer: 1600 });
                setTimeout(function () { location.reload(); }, 1200);
            },
            error: function (xhr) {
                var m = xhr.responseJSON && xhr.responseJSON.message;
                Swal.fire('Not deleted', xhr.status === 403 ? 'Your role is not allowed to delete registrations.' : (m || 'Something went wrong. Please try again.'), xhr.status === 423 ? 'info' : 'error');
            }
        });
      });
  });


</script>