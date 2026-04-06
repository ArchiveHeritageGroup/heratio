@extends('theme::layouts.1col')

@section('title', 'Move - ' . ($io->title ?? 'Untitled'))
@section('body-class', 'move informationobject')

@section('title-block')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">{{ $io->title ?? 'Untitled' }}</h1>
    <span class="small">Move</span>
  </div>
@endsection

@section('content')

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $error)
        <p class="mb-0">{{ $error }}</p>
      @endforeach
    </div>
  @endif

  <div class="accordion mb-3">
    <div class="accordion-item">
      <h2 class="accordion-header" id="move-heading">
        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#move-collapse" aria-expanded="true" aria-controls="move-collapse">
          Move
        </button>
      </h2>
      <div id="move-collapse" class="accordion-collapse collapse show" aria-labelledby="move-heading">
        <div class="accordion-body">

          <p>Use this form to move this description under a different parent record.</p>

          {{-- Current parent breadcrumb --}}
          <div class="mb-3">
            <label class="form-label fw-bold">Current location</label>
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb mb-0" style="background:var(--ahg-card-header-bg, #005837);padding:.5rem 1rem;border-radius:.375rem;">
                @foreach($breadcrumb as $ancestor)
                  <li class="breadcrumb-item">
                    <a href="{{ url('/' . $ancestor->slug) }}" class="text-white text-decoration-underline">{{ $ancestor->title ?? $ancestor->slug }}</a>
                  </li>
                @endforeach
                <li class="breadcrumb-item active text-white" aria-current="page">{{ $io->title }}</li>
              </ol>
            </nav>
          </div>

          @if($currentParent)
            <p><strong>Current parent:</strong> {{ $currentParent->title }}</p>
          @else
            <p><strong>Current parent:</strong> <em>Top level (no parent)</em></p>
          @endif

          <hr>

          <form action="{{ route('informationobject.move.store', $io->slug) }}" method="POST" id="move-form">
            @csrf

            <div class="mb-3">
              <label for="new_parent_search" class="form-label fw-bold">New parent</label>
              <input type="text" class="form-control" id="new_parent_search" placeholder="Type to search for a new parent record..." autocomplete="off">
              <input type="hidden" name="new_parent_id" id="new_parent_id" value="">
              <div id="move-autocomplete-results" class="list-group mt-1" style="position:relative;z-index:1000;"></div>
              <div id="move-selected-parent" class="mt-2"></div>
            </div>

            <ul class="actions mb-3 nav gap-2">
              <li>
                @php
                  $cancelRoute = 'informationobject.show';
                  if ($io->level_of_description_id && \Illuminate\Support\Facades\Schema::hasTable('level_of_description_sector')) {
                      $sector = \Illuminate\Support\Facades\DB::table('level_of_description_sector')
                          ->where('term_id', $io->level_of_description_id)
                          ->whereNotIn('sector', ['archive'])
                          ->orderBy('display_order')
                          ->value('sector');
                      $sectorRoutes = ['library' => 'library.show', 'museum' => 'museum.show', 'gallery' => 'gallery.show', 'dam' => 'dam.show'];
                      if ($sector && isset($sectorRoutes[$sector]) && \Illuminate\Support\Facades\Route::has($sectorRoutes[$sector])) {
                          $cancelRoute = $sectorRoutes[$sector];
                      }
                  }
                @endphp
                <a href="{{ route($cancelRoute, $io->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a>
              </li>
              <li>
                <input class="btn atom-btn-outline-success" id="move-form-submit" type="submit" value="Move" disabled>
              </li>
            </ul>
          </form>

        </div>
      </div>
    </div>
  </div>

@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('new_parent_search');
    var hiddenInput = document.getElementById('new_parent_id');
    var resultsDiv = document.getElementById('move-autocomplete-results');
    var selectedDiv = document.getElementById('move-selected-parent');
    var submitBtn = document.getElementById('move-form-submit');
    var debounceTimer = null;
    var currentIoId = {{ $io->id }};

    searchInput.addEventListener('input', function() {
        var query = this.value.trim();
        if (query.length < 2) {
            resultsDiv.innerHTML = '';
            return;
        }

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            fetch('{{ route("informationobject.autocomplete") }}?query=' + encodeURIComponent(query) + '&limit=15')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    resultsDiv.innerHTML = '';
                    if (!data.length) {
                        resultsDiv.innerHTML = '<div class="list-group-item text-muted">No results found</div>';
                        return;
                    }
                    data.forEach(function(item) {
                        if (item.id === currentIoId) return;
                        var a = document.createElement('a');
                        a.href = '#';
                        a.className = 'list-group-item list-group-item-action';
                        a.textContent = item.name;
                        a.dataset.id = item.id;
                        a.dataset.name = item.name;
                        a.addEventListener('click', function(e) {
                            e.preventDefault();
                            hiddenInput.value = this.dataset.id;
                            selectedDiv.innerHTML = '<div class="alert alert-success mb-0"><strong>Selected new parent:</strong> ' + this.dataset.name + '</div>';
                            resultsDiv.innerHTML = '';
                            searchInput.value = this.dataset.name;
                            submitBtn.disabled = false;
                        });
                        resultsDiv.appendChild(a);
                    });
                });
        }, 300);
    });

    // Close results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
            resultsDiv.innerHTML = '';
        }
    });
});
</script>
@endpush
