@extends('theme::layouts.1col')

@section('title', 'Dropdown Manager')
@section('body-class', 'admin dropdowns')

@section('content')
<div class="row">
  {{-- Sidebar --}}
  <div class="col-lg-3 col-md-4 mb-4">
    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="fas fa-list-alt me-2"></i>Dropdown Manager</h5>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">Manage controlled vocabularies (dropdowns) used throughout the system.</p>
        <button type="button" class="btn atom-btn-outline-success w-100 mb-3" data-bs-toggle="modal" data-bs-target="#createTaxonomyModal">
          <i class="fas fa-plus me-2"></i>Create Taxonomy
        </button>
        <hr>
        <input type="text" id="sectionFilter" class="form-control form-control-sm mb-2" placeholder="Filter sections...">
        <div class="list-group list-group-flush" id="sectionNav">
          @foreach ($sectionLabels as $sKey => $sLabel)
            @php
              $items = $taxonomyGroups[$sKey] ?? [];
              $count = count($items);
              $icon = $sectionIcons[$sKey] ?? 'fa-folder';
            @endphp
            @if ($count > 0)
              <a href="#section-{{ $sKey }}"
                 class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-1 section-nav-link"
                 data-section="{{ $sKey }}"
                 data-label="{{ strtolower($sLabel) }}">
                <span><i class="fas fa-fw {{ $icon }} me-1 text-muted"></i> {{ $sLabel }}</span>
                <span class="badge bg-secondary rounded-pill">{{ $count }}</span>
              </a>
            @endif
          @endforeach
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-body text-center small text-muted">
        @php
          $totalTaxonomies = 0; $totalTerms = 0;
          foreach ($taxonomyGroups as $items) { $totalTaxonomies += count($items); foreach ($items as $item) { $totalTerms += $item->term_count; } }
          $mappedColumns = \Illuminate\Support\Facades\DB::table('ahg_dropdown_column_map')->count();
        @endphp
        <div><strong>{{ $totalTaxonomies }}</strong> taxonomies</div>
        <div><strong>{{ $totalTerms }}</strong> total terms</div>
        <div><strong>{{ $mappedColumns }}</strong> column mappings</div>
      </div>
    </div>
  </div>

  {{-- Main content --}}
  <div class="col-lg-9 col-md-8">
    <div class="mb-3 d-flex gap-2">
      <button class="btn btn-sm atom-btn-white" id="expandAll">
        <i class="fas fa-expand-arrows-alt me-1"></i>Expand All
      </button>
      <button class="btn btn-sm atom-btn-white" id="collapseAll">
        <i class="fas fa-compress-arrows-alt me-1"></i>Collapse All
      </button>
      <input type="text" class="form-control form-control-sm w-auto" id="taxonomySearch" placeholder="Search taxonomies...">
    </div>

    <div class="accordion" id="dropdownAccordion">
      @foreach ($sectionLabels as $sKey => $sLabel)
        @php $items = $taxonomyGroups[$sKey] ?? []; $icon = $sectionIcons[$sKey] ?? 'fa-folder'; @endphp
        @if (count($items) > 0)
          <div class="accordion-item section-accordion" id="section-{{ $sKey }}" data-section="{{ $sKey }}">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button"
                      data-bs-toggle="collapse"
                      data-bs-target="#collapse-{{ $sKey }}">
                <i class="fas fa-fw {{ $icon }} me-2"></i>
                <span class="me-2">{{ $sLabel }}</span>
                <span class="badge bg-primary rounded-pill">{{ count($items) }}</span>
              </button>
            </h2>
            <div id="collapse-{{ $sKey }}" class="accordion-collapse collapse" data-bs-parent="#dropdownAccordion">
              <div class="accordion-body p-0">
                <div class="table-responsive">
                  <table class="table table-bordered table-hover table-sm mb-0">
                    <thead>
                      <tr style="background:var(--ahg-primary);color:#fff">
                        <th>Taxonomy</th>
                        <th style="width:120px">Code</th>
                        <th class="text-center" style="width:80px">Terms</th>
                        <th class="text-end" style="width:160px">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($items as $item)
                        <tr class="taxonomy-row" data-taxonomy="{{ $item->taxonomy }}" data-label="{{ strtolower($item->taxonomy_label) }}">
                          <td>
                            <a href="{{ route('dropdown.edit', $item->taxonomy) }}">{{ $item->taxonomy_label }}</a>
                          </td>
                          <td><code class="small">{{ $item->taxonomy }}</code></td>
                          <td class="text-center">
                            <span class="badge bg-info rounded-pill">{{ $item->term_count }}</span>
                          </td>
                          <td class="text-end">
                            <a href="{{ route('dropdown.edit', $item->taxonomy) }}" class="btn btn-sm atom-btn-white" title="Edit Terms">
                              <i class="fas fa-edit"></i>
                            </a>
                            <button type="button" class="btn btn-sm atom-btn-white btn-rename"
                                    data-taxonomy="{{ $item->taxonomy }}"
                                    data-label="{{ $item->taxonomy_label }}" title="Rename">
                              <i class="fas fa-pen"></i>
                            </button>
                            <button type="button" class="btn btn-sm atom-btn-white btn-move"
                                    data-taxonomy="{{ $item->taxonomy }}"
                                    data-section="{{ $sKey }}" title="Move Section">
                              <i class="fas fa-arrows-alt"></i>
                            </button>
                            <button type="button" class="btn btn-sm atom-btn-outline-danger btn-delete-taxonomy"
                                    data-taxonomy="{{ $item->taxonomy }}"
                                    data-label="{{ $item->taxonomy_label }}" title="Delete">
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
          </div>
        @endif
      @endforeach
    </div>
  </div>
</div>

{{-- Create Taxonomy Modal --}}
<div class="modal fade" id="createTaxonomyModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Create Taxonomy</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Section <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
          <select id="createSection" class="form-select">
            @foreach ($sectionLabels as $sKey => $sLabel)
              <option value="{{ $sKey }}">{{ $sLabel }}</option>
            @endforeach
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Display Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
          <input type="text" id="createLabel" class="form-control" placeholder="e.g., Condition Status">
        </div>
        <div class="mb-3">
          <label class="form-label">Code <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
          <input type="text" id="createCode" class="form-control" placeholder="e.g., condition_status">
          <div class="form-text">Lowercase letters, numbers, and underscores only</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn atom-btn-outline-success" id="createTaxonomyBtn">Create</button>
      </div>
    </div>
  </div>
</div>

{{-- Rename Taxonomy Modal --}}
<div class="modal fade" id="renameTaxonomyModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-pen me-2"></i>Rename Taxonomy</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="renameTaxonomyCode">
        <div class="mb-3">
          <label class="form-label">New Display Name <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="text" id="renameNewLabel" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn atom-btn-white" id="renameTaxonomyBtn">Save</button>
      </div>
    </div>
  </div>
</div>

{{-- Move Section Modal --}}
<div class="modal fade" id="moveSectionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-arrows-alt me-2"></i>Move to Section</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="moveTaxonomyCode">
        <div class="mb-3">
          <label class="form-label">Target Section <span class="badge bg-secondary ms-1">Optional</span></label>
          <select id="moveTargetSection" class="form-select">
            @foreach ($sectionLabels as $sKey => $sLabel)
              <option value="{{ $sKey }}">{{ $sLabel }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn atom-btn-white" id="moveSectionBtn">Move</button>
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
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
      body: JSON.stringify(data),
    }).then(r => r.json());
  }

  // Auto-generate code from label
  var createLabel = document.getElementById('createLabel');
  var createCode  = document.getElementById('createCode');
  if (createLabel && createCode) {
    createLabel.addEventListener('input', function() {
      createCode.value = this.value.toLowerCase().replace(/[^a-z0-9\s_-]/g, '').replace(/[\s-]+/g, '_').replace(/^_+|_+$/g, '');
    });
  }

  // Create taxonomy
  document.getElementById('createTaxonomyBtn').addEventListener('click', function() {
    var btn = this; btn.disabled = true;
    ajaxPost('{{ route("dropdown.create") }}', {
      taxonomy_label: createLabel.value, taxonomy_code: createCode.value,
      taxonomy_section: document.getElementById('createSection').value,
    }).then(function(data) {
      btn.disabled = false;
      if (data.success) { window.location.reload(); } else { alert(data.message || 'Failed.'); }
    }).catch(function() { btn.disabled = false; alert('Network error.'); });
  });

  // Rename
  document.querySelectorAll('.btn-rename').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.getElementById('renameTaxonomyCode').value = this.dataset.taxonomy;
      document.getElementById('renameNewLabel').value = this.dataset.label;
      new bootstrap.Modal(document.getElementById('renameTaxonomyModal')).show();
    });
  });
  document.getElementById('renameTaxonomyBtn').addEventListener('click', function() {
    var btn = this; btn.disabled = true;
    ajaxPost('{{ route("dropdown.rename") }}', {
      taxonomy: document.getElementById('renameTaxonomyCode').value,
      new_label: document.getElementById('renameNewLabel').value,
    }).then(function(data) {
      btn.disabled = false;
      if (data.success) { window.location.reload(); } else { alert(data.message || 'Failed.'); }
    }).catch(function() { btn.disabled = false; alert('Network error.'); });
  });

  // Move
  document.querySelectorAll('.btn-move').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.getElementById('moveTaxonomyCode').value = this.dataset.taxonomy;
      document.getElementById('moveTargetSection').value = this.dataset.section;
      new bootstrap.Modal(document.getElementById('moveSectionModal')).show();
    });
  });
  document.getElementById('moveSectionBtn').addEventListener('click', function() {
    var btn = this; btn.disabled = true;
    ajaxPost('{{ route("dropdown.move-section") }}', {
      taxonomy: document.getElementById('moveTaxonomyCode').value,
      section: document.getElementById('moveTargetSection').value,
    }).then(function(data) {
      btn.disabled = false;
      if (data.success) { window.location.reload(); } else { alert(data.message || 'Failed.'); }
    }).catch(function() { btn.disabled = false; alert('Network error.'); });
  });

  // Delete
  document.querySelectorAll('.btn-delete-taxonomy').forEach(function(btn) {
    btn.addEventListener('click', function() {
      if (!confirm('Delete "' + this.dataset.label + '" and ALL its terms? This cannot be undone.')) return;
      ajaxPost('{{ route("dropdown.delete-taxonomy") }}', { taxonomy: this.dataset.taxonomy })
        .then(function(data) { if (data.success) { window.location.reload(); } else { alert(data.message || 'Failed.'); } })
        .catch(function() { alert('Network error.'); });
    });
  });

  // Expand/Collapse All
  document.getElementById('expandAll').addEventListener('click', function() {
    document.querySelectorAll('#dropdownAccordion .accordion-collapse').forEach(function(el) { new bootstrap.Collapse(el, { toggle: false }).show(); });
  });
  document.getElementById('collapseAll').addEventListener('click', function() {
    document.querySelectorAll('#dropdownAccordion .accordion-collapse.show').forEach(function(el) { new bootstrap.Collapse(el, { toggle: false }).hide(); });
  });

  // Section filter
  document.getElementById('sectionFilter').addEventListener('input', function() {
    var q = this.value.toLowerCase();
    document.querySelectorAll('.section-nav-link').forEach(function(link) {
      link.style.display = (!q || link.dataset.label.includes(q)) ? '' : 'none';
    });
  });

  // Taxonomy search
  document.getElementById('taxonomySearch').addEventListener('input', function() {
    var q = this.value.toLowerCase();
    document.querySelectorAll('.taxonomy-row').forEach(function(row) {
      row.style.display = (!q || row.dataset.label.includes(q) || row.dataset.taxonomy.includes(q)) ? '' : 'none';
    });
    if (q) {
      document.querySelectorAll('.section-accordion').forEach(function(section) {
        if (section.querySelectorAll('.taxonomy-row:not([style*="display: none"])').length > 0) {
          new bootstrap.Collapse(section.querySelector('.accordion-collapse'), { toggle: false }).show();
        }
      });
    }
  });

  // Sidebar section click → expand + scroll
  document.querySelectorAll('.section-nav-link').forEach(function(link) {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      var section = this.dataset.section;
      var collapseEl = document.getElementById('collapse-' + section);
      if (collapseEl) {
        new bootstrap.Collapse(collapseEl, { toggle: false }).show();
        setTimeout(function() {
          document.getElementById('section-' + section).scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 300);
      }
    });
  });
})();
</script>
@endpush
