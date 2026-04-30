{{--
  Heratio — Manual Condition Assessment
  Copyright (c) Johan Pieterse / Plain Sailing (Pty) Ltd
  Licensed under AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')

@section('title', 'Manual Condition Assessment')
@section('body-class', 'browse ai ai-condition-manual')

@section('sidebar')
  <div class="sidebar-content">
    <div class="card mb-3">
      <div class="card-header bg-primary text-white py-2">
        <h6 class="mb-0"><i class="fas fa-clipboard-check me-1"></i>Manual Assessment</h6>
      </div>
      <div class="card-body py-2 small">
        <p class="text-muted mb-2">Record a condition assessment manually without AI. Fill in the condition grade, damages, and recommendations based on physical inspection.</p>
        <a href="{{ route('admin.ai.condition.browse') }}" class="btn btn-sm btn-outline-secondary w-100">
          <i class="fas fa-arrow-left me-1"></i>Back to Browse
        </a>
      </div>
    </div>
  </div>
@endsection

@section('title-block')
  <h1 class="h3 mb-0"><i class="fas fa-clipboard-check me-2"></i>Manual Condition Assessment</h1>
  <p class="text-muted small mb-3">Record a manual condition assessment without AI</p>
@endsection

@section('content')
<div class="card">
  <div class="card-body">
    <form id="manualAssessForm" enctype="multipart/form-data">
      @csrf

      {{-- Link to Object (optional) --}}
      <div class="mb-3">
        <label class="form-label">{{ __('Link to Object (optional)') }}</label>
        <input type="text" class="form-control form-control-sm" id="objectSearch" placeholder="{{ __('Search by title...') }}" autocomplete="off">
        <input type="hidden" id="objectId" name="information_object_id" value="">
        <div id="objectResults" class="list-group position-absolute" style="z-index:1000;display:none"></div>
      </div>

      {{-- Condition Grade --}}
      <div class="mb-3">
        <label class="form-label">Condition Grade <span class="text-danger">*</span></label>
        <select class="form-select form-select-sm" id="conditionGrade" name="condition_grade" required>
          <option value="">-- Select grade --</option>
          @foreach(['excellent', 'good', 'fair', 'poor', 'critical'] as $g)
            <option value="{{ $g }}">{{ ucfirst($g) }}</option>
          @endforeach
        </select>
      </div>

      {{-- Overall Score --}}
      <div class="mb-3">
        <label class="form-label">Overall Score: <span id="scoreValue">50</span>/100</label>
        <input type="range" class="form-range" id="overallScore" name="overall_score" min="0" max="100" value="50" step="1">
      </div>

      {{-- Damages --}}
      <div class="mb-3">
        <label class="form-label">{{ __('Damages') }}</label>
        <div id="damagesContainer"></div>
        <button type="button" class="btn btn-sm btn-outline-success mt-2" id="addDamageBtn">
          <i class="fas fa-plus me-1"></i>Add Damage
        </button>
      </div>

      <hr>

      {{-- Recommendations --}}
      <div class="mb-3">
        <label class="form-label">{{ __('Recommendations') }}</label>
        <textarea class="form-control form-control-sm" id="recommendations" name="recommendations" rows="3" placeholder="{{ __('Enter conservation recommendations...') }}"></textarea>
      </div>

      {{-- Image Upload (optional) --}}
      <div class="mb-3">
        <label class="form-label">{{ __('Reference Photo (optional)') }}</label>
        <input type="file" class="form-control form-control-sm" id="imageFile" name="image_file" accept="image/*">
      </div>

      <div id="submitAlert" style="display:none"></div>

      <button type="submit" class="btn btn-primary w-100" id="submitBtn">
        <i class="fas fa-save me-1"></i>Save Assessment
      </button>
    </form>
  </div>
</div>

<script>
// Damage types
var damageTypes = ['tear','stain','foxing','fading','water_damage','mold','pest_damage','abrasion','brittleness','loss','discoloration','warping','cracking','delamination','corrosion'];
var severities = ['minor','moderate','severe','critical'];
var damageIndex = 0;

// Score slider
document.getElementById('overallScore').addEventListener('input', function() {
    document.getElementById('scoreValue').textContent = this.value;
});

// Object autocomplete
var searchTimer;
document.getElementById('objectSearch').addEventListener('input', function() {
    clearTimeout(searchTimer);
    var q = this.value.trim();
    if (q.length < 2) { document.getElementById('objectResults').style.display='none'; return; }
    searchTimer = setTimeout(function() {
        fetch('{{ url('/admin/ai/condition/object-search') }}?query=' + encodeURIComponent(q))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var el = document.getElementById('objectResults');
            el.innerHTML = '';
            (data.results || []).forEach(function(item) {
                var a = document.createElement('a');
                a.href = '#';
                a.className = 'list-group-item list-group-item-action small py-1';
                a.textContent = item.title;
                a.dataset.id = item.id;
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.getElementById('objectId').value = this.dataset.id;
                    document.getElementById('objectSearch').value = this.textContent;
                    el.style.display = 'none';
                });
                el.appendChild(a);
            });
            el.style.display = data.results && data.results.length ? '' : 'none';
        });
    }, 300);
});

// Add damage row
function addDamageRow() {
    var idx = damageIndex++;
    var row = document.createElement('div');
    row.className = 'border rounded p-2 mb-2 bg-light';
    row.id = 'damageRow_' + idx;

    var typeOpts = '<option value="">-- Type --</option>';
    damageTypes.forEach(function(t) {
        typeOpts += '<option value="' + t + '">' + t.replace(/_/g, ' ').replace(/\b\w/g, function(c){return c.toUpperCase();}) + '</option>';
    });

    var sevOpts = '<option value="">-- Severity --</option>';
    severities.forEach(function(s) {
        sevOpts += '<option value="' + s + '">' + s.charAt(0).toUpperCase() + s.slice(1) + '</option>';
    });

    row.innerHTML = '<div class="row g-2 mb-2">'
        + '<div class="col-md-4"><select class="form-select form-select-sm" name="damages[' + idx + '][damage_type]" required>' + typeOpts + '</select></div>'
        + '<div class="col-md-3"><select class="form-select form-select-sm" name="damages[' + idx + '][severity]" required>' + sevOpts + '</select></div>'
        + '<div class="col-md-3"><input type="text" class="form-control form-control-sm" name="damages[' + idx + '][location_zone]" placeholder="Location zone"></div>'
        + '<div class="col-md-2 text-end"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeDamageRow(' + idx + ')"><i class="fas fa-times"></i> Remove</button></div>'
        + '</div>'
        + '<textarea class="form-control form-control-sm" name="damages[' + idx + '][description]" rows="2" placeholder="Damage description..."></textarea>';

    document.getElementById('damagesContainer').appendChild(row);
}

function removeDamageRow(idx) {
    var row = document.getElementById('damageRow_' + idx);
    if (row) row.remove();
}

document.getElementById('addDamageBtn').addEventListener('click', addDamageRow);

// Submit form
document.getElementById('manualAssessForm').addEventListener('submit', function(e) {
    e.preventDefault();

    var grade = document.getElementById('conditionGrade').value;
    if (!grade) { alert('Please select a condition grade'); return; }

    var formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('information_object_id', document.getElementById('objectId').value);
    formData.append('condition_grade', grade);
    formData.append('overall_score', document.getElementById('overallScore').value);
    formData.append('recommendations', document.getElementById('recommendations').value);

    var fileInput = document.getElementById('imageFile');
    if (fileInput.files[0]) {
        formData.append('image_file', fileInput.files[0]);
    }

    // Collect damages
    var damages = [];
    var rows = document.getElementById('damagesContainer').children;
    for (var i = 0; i < rows.length; i++) {
        var row = rows[i];
        var typeEl = row.querySelector('select[name*="[damage_type]"]');
        var sevEl = row.querySelector('select[name*="[severity]"]');
        var locEl = row.querySelector('input[name*="[location_zone]"]');
        var descEl = row.querySelector('textarea[name*="[description]"]');
        if (typeEl && typeEl.value) {
            damages.push({
                damage_type: typeEl.value,
                severity: sevEl ? sevEl.value : '',
                location_zone: locEl ? locEl.value : '',
                description: descEl ? descEl.value : ''
            });
        }
    }
    formData.append('damages_json', JSON.stringify(damages));

    document.getElementById('submitBtn').disabled = true;
    document.getElementById('submitAlert').style.display = 'none';

    fetch('{{ url('/admin/ai/condition/manual') }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        document.getElementById('submitBtn').disabled = false;
        if (data.success && data.assessment_id) {
            window.location.href = '{{ url('/admin/ai/condition') }}/' + data.assessment_id;
        } else {
            var alertEl = document.getElementById('submitAlert');
            alertEl.style.display = '';
            alertEl.innerHTML = '<div class="alert alert-danger py-1 small"><i class="fas fa-times me-1"></i>' + esc(data.error || 'Unknown error') + '</div>';
        }
    })
    .catch(function() {
        document.getElementById('submitBtn').disabled = false;
        var alertEl = document.getElementById('submitAlert');
        alertEl.style.display = '';
        alertEl.innerHTML = '<div class="alert alert-danger py-1 small"><i class="fas fa-times me-1"></i>Network error</div>';
    });
});

function esc(str) {
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}
</script>
@endsection
