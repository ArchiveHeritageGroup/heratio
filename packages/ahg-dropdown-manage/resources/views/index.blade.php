@extends('theme::layouts.1col')

@section('title', 'Dropdown Manager')
@section('body-class', 'admin dropdowns')

@section('breadcrumbs')
  <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
  <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Admin</a></li>
  <li class="breadcrumb-item active">Dropdown Manager</li>
@endsection

@section('content')
<div class="row">
  {{-- Sidebar --}}
  <div class="col-lg-3 col-md-4 mb-4">
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <button type="button" class="btn btn-primary w-100 mb-3" data-bs-toggle="modal" data-bs-target="#createTaxonomyModal">
          <i class="fas fa-plus me-1"></i> Create Taxonomy
        </button>

        <div class="mb-3">
          <input type="text" id="sectionFilter" class="form-control form-control-sm" placeholder="Filter sections...">
        </div>

        <nav id="sectionNav">
          @foreach ($sectionLabels as $sKey => $sLabel)
            @php
              $items = $taxonomyGroups[$sKey] ?? [];
              $count = count($items);
            @endphp
            <a href="#section-{{ $sKey }}"
               class="d-flex justify-content-between align-items-center text-decoration-none text-dark py-1 section-nav-link {{ $count === 0 ? 'd-none' : '' }}"
               data-section="{{ $sKey }}"
               data-label="{{ strtolower($sLabel) }}">
              <span class="small">{{ $sLabel }}</span>
              @if ($count > 0)
                <span class="badge bg-secondary rounded-pill">{{ $count }}</span>
              @endif
            </a>
          @endforeach
        </nav>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body text-center small text-muted">
        @php
          $totalTaxonomies = 0;
          $totalTerms = 0;
          foreach ($taxonomyGroups as $items) {
              $totalTaxonomies += count($items);
              foreach ($items as $item) {
                  $totalTerms += $item->term_count;
              }
          }
        @endphp
        <div><strong>{{ $totalTaxonomies }}</strong> taxonomies</div>
        <div><strong>{{ $totalTerms }}</strong> total terms</div>
      </div>
    </div>
  </div>

  {{-- Main content --}}
  <div class="col-lg-9 col-md-8">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h3 mb-0"><i class="fas fa-list-alt me-2"></i>Dropdown Manager</h1>
      <div>
        <button type="button" class="btn btn-sm btn-outline-secondary me-1" id="expandAll">
          <i class="fas fa-expand-alt"></i> Expand All
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="collapseAll">
          <i class="fas fa-compress-alt"></i> Collapse All
        </button>
      </div>
    </div>

    <div class="mb-3">
      <input type="text" id="taxonomySearch" class="form-control" placeholder="Search taxonomies...">
    </div>

    <div class="accordion" id="dropdownAccordion">
      @foreach ($sectionLabels as $sKey => $sLabel)
        @php $items = $taxonomyGroups[$sKey] ?? []; @endphp
        @if (count($items) > 0)
          <div class="accordion-item section-accordion" id="section-{{ $sKey }}" data-section="{{ $sKey }}">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button"
                      data-bs-toggle="collapse"
                      data-bs-target="#collapse-{{ $sKey }}"
                      aria-expanded="false"
                      aria-controls="collapse-{{ $sKey }}">
                <span class="me-2">{{ $sLabel }}</span>
                <span class="badge bg-primary rounded-pill">{{ count($items) }}</span>
              </button>
            </h2>
            <div id="collapse-{{ $sKey }}" class="accordion-collapse collapse" data-bs-parent="#dropdownAccordion">
              <div class="accordion-body p-0">
                <table class="table table-hover table-sm mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Taxonomy</th>
                      <th class="text-center" style="width:80px">Code</th>
                      <th class="text-center" style="width:80px">Terms</th>
                      <th class="text-end" style="width:160px">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach ($items as $item)
                      <tr class="taxonomy-row" data-taxonomy="{{ $item->taxonomy }}" data-label="{{ strtolower($item->taxonomy_label) }}">
                        <td>
                          <a href="{{ route('dropdown.edit', $item->taxonomy) }}">
                            {{ $item->taxonomy_label }}
                          </a>
                        </td>
                        <td class="text-center"><code class="small">{{ $item->taxonomy }}</code></td>
                        <td class="text-center">
                          <span class="badge bg-info rounded-pill">{{ $item->term_count }}</span>
                        </td>
                        <td class="text-end">
                          <a href="{{ route('dropdown.edit', $item->taxonomy) }}" class="btn btn-sm btn-outline-primary" title="Edit terms">
                            <i class="fas fa-edit"></i>
                          </a>
                          <button type="button" class="btn btn-sm btn-outline-secondary btn-rename"
                                  data-taxonomy="{{ $item->taxonomy }}"
                                  data-label="{{ $item->taxonomy_label }}" title="Rename">
                            <i class="fas fa-pen"></i>
                          </button>
                          <button type="button" class="btn btn-sm btn-outline-warning btn-move"
                                  data-taxonomy="{{ $item->taxonomy }}"
                                  data-section="{{ $sKey }}" title="Move section">
                            <i class="fas fa-arrows-alt"></i>
                          </button>
                          <button type="button" class="btn btn-sm btn-outline-danger btn-delete-taxonomy"
                                  data-taxonomy="{{ $item->taxonomy }}"
                                  data-label="{{ $item->taxonomy_label }}" title="Delete taxonomy">
                            <i class="fas fa-trash"></i>
                          </button>
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        @endif
      @endforeach
    </div>
  </div>
</div>

{{-- Create Taxonomy Modal --}}
<div class="modal fade" id="createTaxonomyModal" tabindex="-1" aria-labelledby="createTaxonomyModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="createTaxonomyModalLabel">Create Taxonomy</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="createSection" class="form-label">Section</label>
          <select id="createSection" class="form-select">
            @foreach ($sectionLabels as $sKey => $sLabel)
              <option value="{{ $sKey }}">{{ $sLabel }}</option>
            @endforeach
          </select>
        </div>
        <div class="mb-3">
          <label for="createLabel" class="form-label">Display Name</label>
          <input type="text" id="createLabel" class="form-control" placeholder="e.g. Condition Ratings">
        </div>
        <div class="mb-3">
          <label for="createCode" class="form-label">Code</label>
          <input type="text" id="createCode" class="form-control" placeholder="Auto-generated from name">
          <div class="form-text">Unique machine-readable identifier. Auto-generated from the display name.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="createTaxonomyBtn">
          <i class="fas fa-plus me-1"></i> Create
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Rename Taxonomy Modal --}}
<div class="modal fade" id="renameTaxonomyModal" tabindex="-1" aria-labelledby="renameTaxonomyModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="renameTaxonomyModalLabel">Rename Taxonomy</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="renameTaxonomyCode">
        <div class="mb-3">
          <label for="renameNewLabel" class="form-label">New Display Name</label>
          <input type="text" id="renameNewLabel" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="renameTaxonomyBtn">
          <i class="fas fa-save me-1"></i> Save
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Move Section Modal --}}
<div class="modal fade" id="moveSectionModal" tabindex="-1" aria-labelledby="moveSectionModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="moveSectionModalLabel">Move to Section</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="moveTaxonomyCode">
        <div class="mb-3">
          <label for="moveTargetSection" class="form-label">Target Section</label>
          <select id="moveTargetSection" class="form-select">
            @foreach ($sectionLabels as $sKey => $sLabel)
              <option value="{{ $sKey }}">{{ $sLabel }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" id="moveSectionBtn">
          <i class="fas fa-arrows-alt me-1"></i> Move
        </button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('js')
<script>
(function() {
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

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

  // Auto-generate code from label
  const createLabel = document.getElementById('createLabel');
  const createCode  = document.getElementById('createCode');
  if (createLabel && createCode) {
    createLabel.addEventListener('input', function() {
      createCode.value = this.value
        .toLowerCase()
        .replace(/[^a-z0-9\s_-]/g, '')
        .replace(/[\s-]+/g, '_')
        .replace(/^_+|_+$/g, '');
    });
  }

  // Create taxonomy
  document.getElementById('createTaxonomyBtn').addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    ajaxPost('{{ route("dropdown.create") }}', {
      taxonomy_label: createLabel.value,
      taxonomy_code: createCode.value,
      taxonomy_section: document.getElementById('createSection').value,
    }).then(data => {
      btn.disabled = false;
      if (data.success) {
        window.location.reload();
      } else {
        alert(data.message || 'Failed to create taxonomy.');
      }
    }).catch(() => {
      btn.disabled = false;
      alert('Network error. Please try again.');
    });
  });

  // Rename taxonomy
  document.querySelectorAll('.btn-rename').forEach(btn => {
    btn.addEventListener('click', function() {
      document.getElementById('renameTaxonomyCode').value = this.dataset.taxonomy;
      document.getElementById('renameNewLabel').value = this.dataset.label;
      new bootstrap.Modal(document.getElementById('renameTaxonomyModal')).show();
    });
  });

  document.getElementById('renameTaxonomyBtn').addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    ajaxPost('{{ route("dropdown.rename") }}', {
      taxonomy: document.getElementById('renameTaxonomyCode').value,
      new_label: document.getElementById('renameNewLabel').value,
    }).then(data => {
      btn.disabled = false;
      if (data.success) {
        window.location.reload();
      } else {
        alert(data.message || 'Failed to rename taxonomy.');
      }
    }).catch(() => {
      btn.disabled = false;
      alert('Network error. Please try again.');
    });
  });

  // Move section
  document.querySelectorAll('.btn-move').forEach(btn => {
    btn.addEventListener('click', function() {
      document.getElementById('moveTaxonomyCode').value = this.dataset.taxonomy;
      document.getElementById('moveTargetSection').value = this.dataset.section;
      new bootstrap.Modal(document.getElementById('moveSectionModal')).show();
    });
  });

  document.getElementById('moveSectionBtn').addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    ajaxPost('{{ route("dropdown.move-section") }}', {
      taxonomy: document.getElementById('moveTaxonomyCode').value,
      section: document.getElementById('moveTargetSection').value,
    }).then(data => {
      btn.disabled = false;
      if (data.success) {
        window.location.reload();
      } else {
        alert(data.message || 'Failed to move taxonomy.');
      }
    }).catch(() => {
      btn.disabled = false;
      alert('Network error. Please try again.');
    });
  });

  // Delete taxonomy
  document.querySelectorAll('.btn-delete-taxonomy').forEach(btn => {
    btn.addEventListener('click', function() {
      const taxonomy = this.dataset.taxonomy;
      const label = this.dataset.label;
      if (!confirm('Are you sure you want to delete the taxonomy "' + label + '" and ALL its terms? This cannot be undone.')) {
        return;
      }
      ajaxPost('{{ route("dropdown.delete-taxonomy") }}', { taxonomy: taxonomy })
        .then(data => {
          if (data.success) {
            window.location.reload();
          } else {
            alert(data.message || 'Failed to delete taxonomy.');
          }
        })
        .catch(() => alert('Network error. Please try again.'));
    });
  });

  // Expand/Collapse All
  document.getElementById('expandAll').addEventListener('click', function() {
    document.querySelectorAll('#dropdownAccordion .accordion-collapse').forEach(el => {
      new bootstrap.Collapse(el, { toggle: false }).show();
    });
  });

  document.getElementById('collapseAll').addEventListener('click', function() {
    document.querySelectorAll('#dropdownAccordion .accordion-collapse.show').forEach(el => {
      new bootstrap.Collapse(el, { toggle: false }).hide();
    });
  });

  // Section filter
  document.getElementById('sectionFilter').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.section-nav-link').forEach(link => {
      const label = link.dataset.label;
      link.style.display = (!q || label.includes(q)) ? '' : 'none';
    });
  });

  // Taxonomy search
  document.getElementById('taxonomySearch').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.taxonomy-row').forEach(row => {
      const label = row.dataset.label;
      const code  = row.dataset.taxonomy;
      row.style.display = (!q || label.includes(q) || code.includes(q)) ? '' : 'none';
    });
    // Show relevant accordion sections
    if (q) {
      document.querySelectorAll('.section-accordion').forEach(section => {
        const visibleRows = section.querySelectorAll('.taxonomy-row:not([style*="display: none"])');
        if (visibleRows.length > 0) {
          const collapse = section.querySelector('.accordion-collapse');
          new bootstrap.Collapse(collapse, { toggle: false }).show();
        }
      });
    }
  });
})();
</script>
@endpush
