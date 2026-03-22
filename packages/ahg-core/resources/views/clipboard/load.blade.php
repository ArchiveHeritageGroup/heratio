@extends('theme::layouts.1col')

@section('title', 'Load clipboard')
@section('body-class', 'clipboard load')

@section('content')

  <div class="alert alert-info">
    Enter the ID of the saved clipboard you would like to load.
    In the &ldquo;Action&rdquo; selector, indicate whether you want to <strong>merge</strong>
    the saved clipboard with the entries on the current clipboard or <strong>replace</strong>
    (overwrite) the current clipboard with the saved one.
  </div>

  <h1>Load clipboard</h1>

  <form id="clipboard-load-form" action="{{ route('clipboard.load.post') }}" method="POST">
    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="load-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse"
                  data-bs-target="#load-collapse" aria-expanded="true" aria-controls="load-collapse">
            Load options
          </button>
        </h2>
        <div id="load-collapse" class="accordion-collapse collapse show" aria-labelledby="load-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="clipboardPassword" class="form-label">Clipboard ID <span class="badge bg-danger ms-1">Required</span></label>
              <input type="text" class="form-control" id="clipboardPassword" name="clipboardPassword"
                     required placeholder="Enter 7-digit clipboard ID">
            </div>
            <div class="mb-3">
              <label for="mode" class="form-label">Action <span class="badge bg-secondary ms-1">Optional</span></label>
              <select class="form-select" id="mode" name="mode">
                <option value="merge" selected>Merge saved clipboard with existing clipboard results</option>
                <option value="replace">Replace existing clipboard results with saved clipboard</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li>
        <button type="submit" name="load" class="btn atom-btn-outline-success">Load</button>
      </li>
      <li>
        <button type="submit" name="loadView" class="btn atom-btn-outline-success">Load and view</button>
      </li>
    </ul>
  </form>

@endsection

@push('css')
<style>
.accordion-button {
  background-color: var(--ahg-primary) !important;
  color: var(--ahg-card-header-text, #fff) !important;
}
.accordion-button:not(.collapsed) {
  background-color: var(--ahg-primary) !important;
  color: var(--ahg-card-header-text, #fff) !important;
  box-shadow: none;
}
.accordion-button.collapsed {
  background-color: var(--ahg-primary) !important;
  color: var(--ahg-card-header-text, #fff) !important;
}
.accordion-button::after {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'//%3e%3c/svg%3e");
}
.accordion-button:focus {
  box-shadow: 0 0 0 0.25rem var(--ahg-input-focus, rgba(0,88,55,0.25));
}
</style>
@endpush
@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
  var form = document.getElementById('clipboard-load-form');
  if (!form) return;

  form.addEventListener('submit', function(e) {
    e.preventDefault();

    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var password = document.getElementById('clipboardPassword').value;
    var mode = document.getElementById('mode').value;
    var loadType = document.activeElement ? document.activeElement.getAttribute('name') : 'load';

    fetch(form.getAttribute('action'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        clipboardPassword: password,
        mode: mode
      })
    })
    .then(function(resp) { return resp.json().then(function(data) { return { ok: resp.ok, data: data }; }); })
    .then(function(result) {
      if (!result.ok) {
        showAlert(result.data.error, 'alert-danger');
        return;
      }

      var data = result.data;
      var clipboard = data.clipboard || {};

      // Get current clipboard from localStorage
      var currentItems = JSON.parse(localStorage.getItem('clipboard') || '{}');

      if (mode === 'merge') {
        // Merge: add loaded items to existing
        var types = ['informationObject', 'actor', 'repository'];
        types.forEach(function(type) {
          if (!currentItems[type]) currentItems[type] = [];
          if (clipboard[type]) {
            clipboard[type].forEach(function(slug) {
              if (currentItems[type].indexOf(slug) === -1) {
                currentItems[type].push(slug);
              }
            });
          }
        });
      } else {
        // Replace
        currentItems = clipboard;
      }

      localStorage.setItem('clipboard', JSON.stringify(currentItems));

      showAlert(data.message, 'alert-info');

      if (loadType === 'loadView') {
        window.location.href = '{{ route("clipboard.view") }}';
      }
    })
    .catch(function() {
      showAlert('An error occurred while loading the clipboard.', 'alert-danger');
    });
  });

  function showAlert(message, type) {
    var wrapper = document.getElementById('wrapper') || document.querySelector('.container-xxl');
    // Clear any existing alerts first
    wrapper.querySelectorAll('.alert').forEach(function(el) { el.remove(); });
    var alert = document.createElement('div');
    alert.className = 'alert ' + type + ' alert-dismissible fade show';
    alert.setAttribute('role', 'alert');
    alert.innerHTML = message +
      '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    wrapper.insertBefore(alert, wrapper.firstChild);
  }
});
</script>
@endpush
