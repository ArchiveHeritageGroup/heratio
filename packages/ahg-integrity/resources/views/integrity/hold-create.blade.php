@extends('theme::layouts.1col')
@section('title', 'Integrity - Place Legal Hold')
@section('body-class', 'admin integrity holds create')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-shield-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">{{ __('Place Legal Hold') }}</h1><span class="small text-muted">Digital object integrity management</span></div>
  </div>
@endsection
@section('content')

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">{{ __('Place Legal Hold') }}</h5></div>
  <div class="card-body">
    <form method="post" action="{{ route('integrity.holds.store') }}">
      @csrf

      <div class="mb-3">
        <label for="io_search" class="form-label">{{ __('Information Object') }}</label>
        <div class="input-group">
          <input type="text" id="io_search" class="form-control" placeholder="{{ __('Search by title or enter IO ID...') }}" value="{{ old('io_search', '') }}" autocomplete="off">
          <input type="hidden" name="information_object_id" id="information_object_id" value="{{ old('information_object_id', '') }}">
          <button type="button" class="btn btn-outline-secondary" id="io_search_btn"><i class="fas fa-search"></i></button>
        </div>
        <div id="io_search_results" class="list-group mt-1" style="display:none; max-height:200px; overflow-y:auto;"></div>
        <div id="io_selected" class="mt-2" style="display:none;">
          <span class="badge bg-success" id="io_selected_label"></span>
          <button type="button" class="btn btn-sm btn-link text-danger" id="io_clear">{{ __('Clear') }}</button>
        </div>
        @error('information_object_id')
        <div class="text-danger small">{{ $message }}</div>
        @enderror
      </div>

      <div class="mb-3">
        <label for="reason" class="form-label">{{ __('Reason for Hold') }}</label>
        <textarea name="reason" id="reason" class="form-control" rows="4" required>{{ old('reason', '') }}</textarea>
        @error('reason')
        <div class="text-danger small">{{ $message }}</div>
        @enderror
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success"><i class="fas fa-lock me-1"></i>Place Hold</button>
        <a href="{{ route('integrity.holds') }}" class="btn atom-btn-white">Cancel</a>
      </div>
    </form>
  </div>
</div>

<div class="mt-3"><a href="{{ route('integrity.holds') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to Holds</a></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('io_search');
    const searchBtn = document.getElementById('io_search_btn');
    const resultsDiv = document.getElementById('io_search_results');
    const hiddenInput = document.getElementById('information_object_id');
    const selectedDiv = document.getElementById('io_selected');
    const selectedLabel = document.getElementById('io_selected_label');
    const clearBtn = document.getElementById('io_clear');

    function doSearch() {
        const q = searchInput.value.trim();
        if (!q) { resultsDiv.style.display = 'none'; return; }

        // If it is a number, set directly
        if (/^\d+$/.test(q)) {
            hiddenInput.value = q;
            selectedLabel.textContent = 'IO #' + q;
            selectedDiv.style.display = 'block';
            resultsDiv.style.display = 'none';
            return;
        }

        // AJAX search
        fetch('/api/search/io?q=' + encodeURIComponent(q) + '&limit=10')
            .then(r => r.json())
            .then(data => {
                resultsDiv.innerHTML = '';
                if (data.length === 0) {
                    resultsDiv.innerHTML = '<div class="list-group-item text-muted">No results. Enter IO ID manually.</div>';
                } else {
                    data.forEach(function(item) {
                        const a = document.createElement('a');
                        a.href = '#';
                        a.className = 'list-group-item list-group-item-action';
                        a.textContent = '#' + item.id + ' - ' + (item.title || 'Untitled');
                        a.addEventListener('click', function(e) {
                            e.preventDefault();
                            hiddenInput.value = item.id;
                            selectedLabel.textContent = '#' + item.id + ' - ' + (item.title || 'Untitled');
                            selectedDiv.style.display = 'block';
                            resultsDiv.style.display = 'none';
                            searchInput.value = '';
                        });
                        resultsDiv.appendChild(a);
                    });
                }
                resultsDiv.style.display = 'block';
            })
            .catch(function() {
                // If API not available, allow direct ID entry
                if (/^\d+$/.test(q)) {
                    hiddenInput.value = q;
                    selectedLabel.textContent = 'IO #' + q;
                    selectedDiv.style.display = 'block';
                }
                resultsDiv.style.display = 'none';
            });
    }

    searchBtn.addEventListener('click', doSearch);
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
    });

    clearBtn.addEventListener('click', function() {
        hiddenInput.value = '';
        selectedDiv.style.display = 'none';
        searchInput.value = '';
        searchInput.focus();
    });
});
</script>
@endsection
