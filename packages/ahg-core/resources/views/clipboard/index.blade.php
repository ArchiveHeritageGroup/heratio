@extends('theme::layouts.1col')

@section('title', 'Clipboard')
@section('body-class', 'clipboard view')

@section('content')

  {{-- Page header --}}
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-paperclip me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0" aria-describedby="heading-label">
        Showing <span id="clipboard-result-count">{{ count($details) }}</span> results
      </h1>
      <span class="small" id="heading-label">Clipboard</span>
    </div>
  </div>

  {{-- Entity type filter + actions bar --}}
  <div class="d-flex flex-wrap gap-2 mb-3">
    <div class="d-flex flex-wrap gap-2 ms-auto">
      <div class="dropdown">
        <button class="btn btn-sm atom-btn-white dropdown-toggle" type="button" data-bs-toggle="dropdown">
          Entity type: {{ $uiLabels[$type] ?? 'All' }}
        </button>
        <ul class="dropdown-menu">
          @foreach($uiLabels as $key => $label)
            <li>
              <a class="dropdown-item {{ $type === $key ? 'active' : '' }}"
                 href="{{ route('clipboard.index', ['type' => $key]) }}">
                {{ $label }}
              </a>
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  </div>

  {{-- Clipboard content --}}
  <div id="clipboard-content">
    @if(empty($details))
      <div class="text-section p-3">
        <p class="mb-0">No results for this entity type.</p>
      </div>
    @else
      <table class="table table-bordered table-striped table-hover">
        <thead>
          <tr>
            <th>Name</th>
            <th>Type</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($details as $item)
            <tr id="clipboard-row-{{ $item->slug }}">
              <td>
                @if($item->type === 'informationObject')
                  <a href="{{ url('/' . $item->slug) }}">{{ $item->name }}</a>
                @elseif($item->type === 'actor')
                  <a href="{{ url('/actor/' . $item->slug) }}">{{ $item->name }}</a>
                @elseif($item->type === 'repository')
                  <a href="{{ url('/repository/' . $item->slug) }}">{{ $item->name }}</a>
                @elseif($item->type === 'accession')
                  <a href="{{ url('/accession/' . $item->slug) }}">{{ $item->name }}</a>
                @else
                  {{ $item->name }}
                @endif
              </td>
              <td>
                @switch($item->type)
                  @case('informationObject')
                    <span class="badge bg-primary">{{ config('app.ui_label_informationobject', 'Archival description') }}</span>
                    @break
                  @case('actor')
                    <span class="badge bg-success">{{ config('app.ui_label_actor', 'Authority record') }}</span>
                    @break
                  @case('repository')
                    <span class="badge bg-info">{{ config('app.ui_label_repository', 'Archival institution') }}</span>
                    @break
                  @case('accession')
                    <span class="badge bg-warning text-dark">Accession</span>
                    @break
                @endswitch
              </td>
              <td class="text-end">
                <button class="btn btn-sm atom-btn-outline-danger clipboard-remove-btn"
                        data-clipboard-slug="{{ $item->slug }}"
                        data-clipboard-type="{{ $item->type }}"
                        title="Remove from clipboard">
                  <i class="fas fa-times"></i>
                </button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  {{-- Action buttons --}}
  @if(!empty($details))
    <ul class="actions mb-3 nav gap-2">
      <li>
        <button class="btn atom-btn-outline-danger" id="clipboard-clear-all"
                data-clipboard-type="{{ $type }}">
          Clear {{ strtolower($uiLabels[$type] ?? '') }} clipboard
        </button>
      </li>
      <li>
        <button class="btn atom-btn-outline-light" id="clipboard-save-btn">
          <i class="fas fa-save me-1"></i> Save
        </button>
      </li>
      <li>
        <a href="{{ route('clipboard.export.csv') }}" class="btn atom-btn-outline-light">
          <i class="fas fa-file-csv me-1"></i> Export CSV
        </a>
      </li>
    </ul>
  @endif

  {{-- Counts summary --}}
  <div class="card mb-3">
    <div class="card-body small">
      <strong>Clipboard summary:</strong>
      {{ config('app.ui_label_informationobject', 'Archival description') }}s: {{ $counts['informationObject'] ?? 0 }} |
      {{ config('app.ui_label_actor', 'Authority record') }}s: {{ $counts['actor'] ?? 0 }} |
      {{ config('app.ui_label_repository', 'Archival institution') }}s: {{ $counts['repository'] ?? 0 }} |
      <strong>Total: {{ $totalCount }}</strong>
    </div>
  </div>

@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
  var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  // Sync session clipboard from localStorage on page load
  var clipboardData = JSON.parse(localStorage.getItem('clipboard') || '{}');
  if (Object.keys(clipboardData).length > 0) {
    fetch('{{ route("clipboard.sync") }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json'
      },
      body: JSON.stringify({ items: clipboardData })
    }).then(function(resp) {
      if (resp.ok) {
        // Reload the page to show synced items
        if (document.querySelectorAll('#clipboard-content tbody tr').length === 0 &&
            Object.values(clipboardData).some(function(arr) { return arr.length > 0; })) {
          window.location.reload();
        }
      }
    });
  }

  // Remove item buttons
  document.querySelectorAll('.clipboard-remove-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var slug = this.getAttribute('data-clipboard-slug');
      var type = this.getAttribute('data-clipboard-type');
      var row = document.getElementById('clipboard-row-' + slug);

      // Remove from localStorage
      var items = JSON.parse(localStorage.getItem('clipboard') || '{}');
      if (items[type]) {
        items[type] = items[type].filter(function(s) { return s !== slug; });
        localStorage.setItem('clipboard', JSON.stringify(items));
      }

      // Remove from server session
      fetch('{{ route("clipboard.remove") }}', {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json'
        },
        body: JSON.stringify({ slug: slug, type: type })
      });

      // Remove row from table
      if (row) row.remove();

      // Update count display
      var countEl = document.getElementById('clipboard-result-count');
      if (countEl) {
        var current = parseInt(countEl.textContent) - 1;
        countEl.textContent = current;
      }

      updateClipboardMenuCount();
    });
  });

  // Clear clipboard button
  var clearBtn = document.getElementById('clipboard-clear-all');
  if (clearBtn) {
    clearBtn.addEventListener('click', function() {
      var type = this.getAttribute('data-clipboard-type');

      // Clear from localStorage
      var items = JSON.parse(localStorage.getItem('clipboard') || '{}');
      if (type && type !== 'all') {
        items[type] = [];
      } else {
        items = {};
      }
      localStorage.setItem('clipboard', JSON.stringify(items));

      // Clear from server session
      fetch('{{ route("clipboard.clear") }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json'
        },
        body: JSON.stringify({ type: type })
      }).then(function() {
        window.location.reload();
      });
    });
  }

  // Save clipboard button
  var saveBtn = document.getElementById('clipboard-save-btn');
  if (saveBtn) {
    saveBtn.addEventListener('click', function() {
      var items = JSON.parse(localStorage.getItem('clipboard') || '{}');

      fetch('{{ route("clipboard.save") }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json'
        },
        body: JSON.stringify({ slugs: items })
      })
      .then(function(resp) { return resp.json(); })
      .then(function(data) {
        if (data.error) {
          showAlert(data.error, 'alert-danger');
        } else {
          showAlert(data.message, 'alert-info');
        }
      })
      .catch(function() {
        showAlert('An error occurred while saving the clipboard.', 'alert-danger');
      });
    });
  }

  function showAlert(message, type) {
    var wrapper = document.getElementById('wrapper') || document.querySelector('body > #wrapper');
    if (!wrapper) wrapper = document.querySelector('.container-xxl');
    // Clear any existing alerts first
    wrapper.querySelectorAll('.alert').forEach(function(el) { el.remove(); });
    var alert = document.createElement('div');
    alert.className = 'alert ' + type + ' alert-dismissible fade show';
    alert.setAttribute('role', 'alert');
    alert.innerHTML = message +
      '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    wrapper.insertBefore(alert, wrapper.firstChild);
  }

  function updateClipboardMenuCount() {
    var items = JSON.parse(localStorage.getItem('clipboard') || '{}');
    var total = 0;
    for (var t in items) {
      if (Array.isArray(items[t])) total += items[t].length;
    }

    var menuBtn = document.getElementById('clipboard-nav');
    if (menuBtn) {
      var badge = menuBtn.querySelector('.clipboard-count');
      if (total > 0) {
        if (!badge) {
          badge = document.createElement('span');
          badge.className = 'clipboard-count position-absolute top-0 start-0 badge rounded-pill bg-primary';
          menuBtn.appendChild(badge);
        }
        badge.textContent = total;
      } else if (badge) {
        badge.remove();
      }
    }
  }
});
</script>
@endpush
