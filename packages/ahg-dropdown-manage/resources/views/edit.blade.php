@extends('theme::layouts.1col')

@section('title', 'Edit Taxonomy: ' . $taxonomyLabel)
@section('body-class', 'admin dropdowns-edit')

@section('breadcrumbs')
  <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
  <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Admin</a></li>
  <li class="breadcrumb-item"><a href="{{ route('dropdown.index') }}">Dropdown Manager</a></li>
  <li class="breadcrumb-item active">{{ $taxonomyLabel }}</li>
@endsection

@section('content')
<div class="row">
  {{-- Sidebar --}}
  <div class="col-lg-3 col-md-4 mb-4">
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <a href="{{ route('dropdown.index') }}" class="btn btn-outline-secondary w-100 mb-3">
          <i class="fas fa-arrow-left me-1"></i> Back to Dropdowns
        </a>
        <button type="button" class="btn btn-primary w-100 mb-3" data-bs-toggle="modal" data-bs-target="#addTermModal">
          <i class="fas fa-plus me-1"></i> Add Term
        </button>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header"><strong>Info</strong></div>
      <div class="card-body small">
        <div class="mb-2">
          <span class="text-muted">Code:</span><br>
          <code>{{ $taxonomy }}</code>
        </div>
        <div class="mb-2">
          <span class="text-muted">Section:</span><br>
          {{ $sectionLabels[$taxonomySection] ?? $taxonomySection ?? 'Other' }}
        </div>
        <div>
          <span class="text-muted">Terms:</span><br>
          <span id="termCountDisplay">{{ $terms->count() }}</span>
          (<span id="activeCountDisplay">{{ $terms->where('is_active', 1)->count() }}</span> active)
        </div>
      </div>
    </div>

    <div class="card shadow-sm mt-3">
      <div class="card-body">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="showInactive" checked>
          <label class="form-check-label small" for="showInactive">Show inactive terms</label>
        </div>
      </div>
    </div>
  </div>

  {{-- Main content --}}
  <div class="col-lg-9 col-md-8">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h3 mb-0">
        <i class="fas fa-list me-2"></i>{{ $taxonomyLabel }}
      </h1>
    </div>

    <div class="card shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="termsTable">
          <thead class="table-light">
            <tr>
              <th style="width:40px"></th>
              <th>Label</th>
              <th style="width:140px">Code</th>
              <th style="width:80px" class="text-center">Color</th>
              <th style="width:80px" class="text-center">Default</th>
              <th style="width:80px" class="text-center">Active</th>
              <th style="width:60px" class="text-center">Delete</th>
            </tr>
          </thead>
          <tbody id="termsTbody">
            @foreach ($terms as $term)
              <tr data-id="{{ $term->id }}" class="term-row {{ !$term->is_active ? 'table-secondary text-muted inactive-row' : '' }}">
                <td class="drag-handle text-center" style="cursor:grab" title="Drag to reorder">
                  <i class="fas fa-grip-vertical text-muted"></i>
                </td>
                <td>
                  <input type="text" class="form-control form-control-sm border-0 bg-transparent term-label-input"
                         value="{{ $term->label }}" data-id="{{ $term->id }}" data-original="{{ $term->label }}">
                </td>
                <td>
                  <code class="small">{{ $term->code }}</code>
                </td>
                <td class="text-center">
                  <input type="color" class="form-control form-control-color form-control-sm term-color-input"
                         value="{{ $term->color ?: '#ffffff' }}" data-id="{{ $term->id }}"
                         title="Choose color" style="width:32px;height:28px;padding:2px;">
                </td>
                <td class="text-center">
                  <input type="radio" class="form-check-input term-default-radio"
                         name="default_term" value="{{ $term->id }}"
                         {{ $term->is_default ? 'checked' : '' }} data-id="{{ $term->id }}">
                </td>
                <td class="text-center">
                  <input type="checkbox" class="form-check-input term-active-checkbox"
                         data-id="{{ $term->id }}" {{ $term->is_active ? 'checked' : '' }}>
                </td>
                <td class="text-center">
                  <button type="button" class="btn btn-sm btn-outline-danger btn-delete-term"
                          data-id="{{ $term->id }}" data-label="{{ $term->label }}" title="Delete term">
                    <i class="fas fa-times"></i>
                  </button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if ($terms->isEmpty())
        <div class="p-4 text-center text-muted">
          No terms found for this taxonomy. Click "Add Term" to create one.
        </div>
      @endif
    </div>
  </div>
</div>

{{-- Add Term Modal --}}
<div class="modal fade" id="addTermModal" tabindex="-1" aria-labelledby="addTermModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addTermModalLabel">Add Term</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="addTermLabel" class="form-label">Label</label>
          <input type="text" id="addTermLabel" class="form-control" placeholder="e.g. Good">
        </div>
        <div class="mb-3">
          <label for="addTermCode" class="form-label">Code</label>
          <input type="text" id="addTermCode" class="form-control" placeholder="Auto-generated from label">
          <div class="form-text">Unique machine-readable identifier within this taxonomy.</div>
        </div>
        <div class="mb-3">
          <label for="addTermColor" class="form-label">Color</label>
          <input type="color" id="addTermColor" class="form-control form-control-color" value="#ffffff">
        </div>
        <div class="mb-3">
          <label for="addTermIcon" class="form-label">Icon (FontAwesome class)</label>
          <input type="text" id="addTermIcon" class="form-control" placeholder="e.g. fa-check">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="addTermBtn">
          <i class="fas fa-plus me-1"></i> Add Term
        </button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('css')
<style>
  .term-label-input:focus { background-color: #fff !important; border: 1px solid #86b7fe !important; }
  .drag-handle:active { cursor: grabbing; }
  .sortable-ghost { opacity: 0.4; background: #e3f2fd; }
  .sortable-chosen { background: #f0f8ff; }
</style>
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
(function() {
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
  const taxonomy  = @json($taxonomy);

  function ajaxPost(url, data) {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
      },
      body: JSON.stringify(data),
    }).then(r => r.json());
  }

  // ---- Sortable drag-reorder ----
  const tbody = document.getElementById('termsTbody');
  if (tbody) {
    Sortable.create(tbody, {
      handle: '.drag-handle',
      animation: 150,
      ghostClass: 'sortable-ghost',
      chosenClass: 'sortable-chosen',
      onEnd: function() {
        const ids = Array.from(tbody.querySelectorAll('tr[data-id]')).map(tr => parseInt(tr.dataset.id));
        ajaxPost('{{ route("dropdown.reorder") }}', { ids: ids }).then(data => {
          if (!data.success) alert(data.message || 'Reorder failed.');
        }).catch(() => alert('Network error during reorder.'));
      }
    });
  }

  // ---- Auto-generate code from label (Add Term Modal) ----
  const addTermLabel = document.getElementById('addTermLabel');
  const addTermCode  = document.getElementById('addTermCode');
  if (addTermLabel && addTermCode) {
    addTermLabel.addEventListener('input', function() {
      addTermCode.value = this.value
        .toLowerCase()
        .replace(/[^a-z0-9\s_-]/g, '')
        .replace(/[\s-]+/g, '_')
        .replace(/^_+|_+$/g, '');
    });
  }

  // ---- Add Term ----
  document.getElementById('addTermBtn').addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    const color = document.getElementById('addTermColor').value;
    ajaxPost('{{ route("dropdown.add-term") }}', {
      taxonomy: taxonomy,
      label: addTermLabel.value,
      code: addTermCode.value,
      color: color !== '#ffffff' ? color : null,
      icon: document.getElementById('addTermIcon').value || null,
    }).then(data => {
      btn.disabled = false;
      if (data.success) {
        window.location.reload();
      } else {
        alert(data.message || 'Failed to add term.');
      }
    }).catch(() => {
      btn.disabled = false;
      alert('Network error. Please try again.');
    });
  });

  // ---- Inline label editing ----
  document.querySelectorAll('.term-label-input').forEach(input => {
    input.addEventListener('blur', function() {
      const id       = this.dataset.id;
      const original = this.dataset.original;
      const newVal   = this.value.trim();
      if (newVal === original || newVal === '') {
        this.value = original;
        return;
      }
      ajaxPost('{{ route("dropdown.update-term") }}', {
        id: parseInt(id), field: 'label', value: newVal,
      }).then(data => {
        if (data.success) {
          input.dataset.original = newVal;
        } else {
          input.value = original;
          alert(data.message || 'Failed to update label.');
        }
      }).catch(() => {
        input.value = original;
        alert('Network error.');
      });
    });
    input.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') { e.preventDefault(); this.blur(); }
      if (e.key === 'Escape') { this.value = this.dataset.original; this.blur(); }
    });
  });

  // ---- Color picker ----
  document.querySelectorAll('.term-color-input').forEach(input => {
    input.addEventListener('change', function() {
      const id    = this.dataset.id;
      const color = this.value;
      ajaxPost('{{ route("dropdown.update-term") }}', {
        id: parseInt(id), field: 'color', value: color !== '#ffffff' ? color : null,
      }).then(data => {
        if (!data.success) alert(data.message || 'Failed to update color.');
      }).catch(() => alert('Network error.'));
    });
  });

  // ---- Default radio ----
  document.querySelectorAll('.term-default-radio').forEach(radio => {
    radio.addEventListener('change', function() {
      if (!this.checked) return;
      const id = this.dataset.id;
      ajaxPost('{{ route("dropdown.set-default") }}', { id: parseInt(id) })
        .then(data => {
          if (!data.success) alert(data.message || 'Failed to set default.');
        })
        .catch(() => alert('Network error.'));
    });
  });

  // ---- Active checkbox ----
  document.querySelectorAll('.term-active-checkbox').forEach(cb => {
    cb.addEventListener('change', function() {
      const id    = this.dataset.id;
      const value = this.checked ? '1' : '0';
      const row   = this.closest('tr');
      ajaxPost('{{ route("dropdown.update-term") }}', {
        id: parseInt(id), field: 'is_active', value: value,
      }).then(data => {
        if (data.success) {
          if (value === '0') {
            row.classList.add('table-secondary', 'text-muted', 'inactive-row');
          } else {
            row.classList.remove('table-secondary', 'text-muted', 'inactive-row');
          }
          updateCounts();
        } else {
          cb.checked = !cb.checked;
          alert(data.message || 'Failed to update status.');
        }
      }).catch(() => {
        cb.checked = !cb.checked;
        alert('Network error.');
      });
    });
  });

  // ---- Delete term ----
  document.querySelectorAll('.btn-delete-term').forEach(btn => {
    btn.addEventListener('click', function() {
      const id    = this.dataset.id;
      const label = this.dataset.label;
      if (!confirm('Delete term "' + label + '"? This cannot be undone.')) return;

      ajaxPost('{{ route("dropdown.delete-term") }}', { id: parseInt(id) })
        .then(data => {
          if (data.success) {
            const row = document.querySelector('tr[data-id="' + id + '"]');
            if (row) row.remove();
            updateCounts();
          } else {
            alert(data.message || 'Failed to delete term.');
          }
        })
        .catch(() => alert('Network error.'));
    });
  });

  // ---- Show/hide inactive ----
  document.getElementById('showInactive').addEventListener('change', function() {
    const show = this.checked;
    document.querySelectorAll('.inactive-row').forEach(row => {
      row.style.display = show ? '' : 'none';
    });
  });

  // ---- Update sidebar counts ----
  function updateCounts() {
    const allRows    = document.querySelectorAll('#termsTbody tr[data-id]');
    const activeRows = document.querySelectorAll('#termsTbody tr[data-id]:not(.inactive-row)');
    const countEl    = document.getElementById('termCountDisplay');
    const activeEl   = document.getElementById('activeCountDisplay');
    if (countEl) countEl.textContent = allRows.length;
    if (activeEl) activeEl.textContent = activeRows.length;
  }
})();
</script>
@endpush
