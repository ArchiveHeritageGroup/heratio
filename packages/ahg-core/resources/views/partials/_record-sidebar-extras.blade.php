{{--
  Shared sidebar extras for all GLAM/DAM record show pages.
  Usage: @include('ahg-core::partials._record-sidebar-extras', ['objectId' => $io->id, 'slug' => $io->slug, 'title' => $io->title])
--}}
@php
  $__objId = $objectId ?? null;
  $__slug = $slug ?? null;
  $__title = $title ?? '';
@endphp

@if(!$__objId) @php return; @endphp @endif

{{-- Active Loans --}}
@if(\Illuminate\Support\Facades\Schema::hasTable('ahg_loan') && \Illuminate\Support\Facades\Schema::hasTable('ahg_loan_object'))
@php
  $__activeLoans = \Illuminate\Support\Facades\DB::table('ahg_loan')
      ->join('ahg_loan_object', 'ahg_loan.id', '=', 'ahg_loan_object.loan_id')
      ->where('ahg_loan_object.information_object_id', $__objId)
      ->whereNotIn('ahg_loan.status', ['returned', 'closed', 'cancelled'])
      ->select('ahg_loan.id', 'ahg_loan.loan_number', 'ahg_loan.loan_type', 'ahg_loan.status', 'ahg_loan.partner_institution', 'ahg_loan.end_date')
      ->get();
@endphp
@if($__activeLoans->isNotEmpty())
  <div class="card mb-3 border-warning">
    <div class="card-header bg-warning text-dark fw-bold">
      <i class="fas fa-exchange-alt me-1"></i> Active Loans ({{ $__activeLoans->count() }})
    </div>
    <div class="list-group list-group-flush">
      @foreach($__activeLoans as $__al)
        @php $__isOverdue = $__al->end_date && $__al->end_date < now()->toDateString(); @endphp
        <a href="{{ route('loan.show', $__al->id) }}" class="list-group-item list-group-item-action {{ $__isOverdue ? 'list-group-item-danger' : '' }}">
          <div class="d-flex justify-content-between">
            <strong>{{ $__al->loan_number }}</strong>
            <span class="badge bg-{{ $__al->loan_type === 'out' ? 'info' : 'warning' }}">{{ $__al->loan_type === 'out' ? 'Out' : 'In' }}</span>
          </div>
          <small>{{ $__al->partner_institution }}</small>
          @if($__isOverdue)<span class="badge bg-danger ms-1"><i class="fas fa-exclamation-triangle"></i> {{ __('Overdue') }}</span>@endif
        </a>
      @endforeach
    </div>
  </div>
@endif
@endif

{{-- Provenance --}}
@if(\Illuminate\Support\Facades\Route::has('provenance.view') && $__slug)
@php
  $__provRecord = \Illuminate\Support\Facades\Schema::hasTable('provenance_record')
    ? \Illuminate\Support\Facades\DB::table('provenance_record')->where('information_object_id', $__objId)->first()
    : null;
@endphp
@if($__provRecord || auth()->check())
  <div class="card mb-3">
    <div class="card-header bg-secondary text-white fw-bold">
      <i class="fas fa-history me-1"></i> {{ __('Provenance') }}
    </div>
    <div class="card-body py-2">
      @if($__provRecord && $__provRecord->current_status)
        <span class="badge bg-info">{{ ucfirst($__provRecord->current_status) }}</span>
      @endif
      @auth
        <a href="{{ route('provenance.view', $__slug) }}" class="btn btn-sm btn-outline-secondary ms-1">View</a>
        <a href="{{ route('provenance.edit', $__slug) }}" class="btn btn-sm btn-outline-primary ms-1">Edit</a>
      @endauth
    </div>
  </div>
@endif
@endif

{{-- Rights --}}
@auth
@if(\Illuminate\Support\Facades\Route::has('io.rights.extended') && $__slug)
@php
  $__hasExtRights = \Illuminate\Support\Facades\Schema::hasTable('extended_rights')
      && \Illuminate\Support\Facades\DB::table('extended_rights')->where('object_id', $__objId)->exists();
  $__activeEmbargo = \Illuminate\Support\Facades\Schema::hasTable('embargo')
      ? \Illuminate\Support\Facades\DB::table('embargo')->where('object_id', $__objId)->where('is_active', 1)->first()
      : null;
@endphp
  <div class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
      <i class="fas fa-copyright me-1"></i> {{ __('Rights') }}
    </div>
    <div class="card-body py-2">
      @if($__hasExtRights)<span class="badge bg-success me-1"><i class="fas fa-check-circle me-1"></i>{{ __('Extended rights') }}</span>@endif
      @if($__activeEmbargo)<span class="badge bg-danger me-1"><i class="fas fa-ban me-1"></i>{{ __('Embargo') }}</span>@endif
      @if(!$__hasExtRights && !$__activeEmbargo)<span class="badge bg-secondary">{{ __('No rights/embargo') }}</span>@endif
    </div>
    <div class="list-group list-group-flush">
      <a href="{{ route('io.rights.extended', $__slug) }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-copyright me-1"></i> {{ $__hasExtRights ? 'Edit' : 'Add' }} extended rights
      </a>
      @if($__activeEmbargo)
        <a href="{{ route('io.rights.embargo', $__slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-edit me-1"></i> {{ __('Edit embargo') }}
        </a>
      @else
        <a href="{{ route('io.rights.embargo', $__slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-lock me-1"></i> {{ __('Add embargo') }}
        </a>
      @endif
      <a href="{{ route('io.rights.export', $__slug) }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-download me-1"></i> {{ __('Export rights (JSON-LD)') }}
      </a>
    </div>
  </div>
@endif
@endauth

{{-- NER --}}
@auth
@if(\Illuminate\Support\Facades\Route::has('io.ai.review') && $__slug)
  <div class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
      <i class="fas fa-brain me-1"></i> NER
    </div>
    <div class="list-group list-group-flush">
      <a href="#" class="list-group-item list-group-item-action small" data-bs-toggle="modal" data-bs-target="#nerModal">
        <i class="fas fa-brain me-2"></i> {{ __('Extract Entities') }}
      </a>
      <a href="{{ route('io.ai.review') }}?object_id={{ $__objId }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-list-check me-2"></i> {{ __('Review Dashboard') }}
      </a>
    </div>
  </div>
@endif
@endauth

{{-- Export --}}
@if($__slug)
  <div class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
      <i class="fas fa-file-export me-1"></i> {{ __('Export') }}
    </div>
    <div class="list-group list-group-flush">
      @if(\Illuminate\Support\Facades\Route::has('informationobject.export.dc'))
        <a href="{{ route('informationobject.export.dc', $__slug) }}" class="list-group-item list-group-item-action small"><i class="fas fa-code me-1"></i> {{ __('Dublin Core XML') }}</a>
      @endif
      @if(\Illuminate\Support\Facades\Route::has('informationobject.export.ead'))
        <a href="{{ route('informationobject.export.ead', $__slug) }}" class="list-group-item list-group-item-action small"><i class="fas fa-code me-1"></i> {{ __('EAD 2002 XML') }}</a>
      @endif
      @if(\Illuminate\Support\Facades\Route::has('informationobject.export.ead3'))
        <a href="{{ route('informationobject.export.ead3', $__slug) }}" class="list-group-item list-group-item-action small"><i class="fas fa-code me-1"></i> {{ __('EAD3 XML') }}</a>
      @endif
      @if(\Illuminate\Support\Facades\Route::has('informationobject.export.ead4'))
        <a href="{{ route('informationobject.export.ead4', $__slug) }}" class="list-group-item list-group-item-action small"><i class="fas fa-code me-1"></i> {{ __('EAD 4 XML') }}</a>
      @endif
      @if(\Illuminate\Support\Facades\Route::has('informationobject.export.mods'))
        <a href="{{ route('informationobject.export.mods', $__slug) }}" class="list-group-item list-group-item-action small"><i class="fas fa-code me-1"></i> {{ __('MODS XML') }}</a>
      @endif
      @if(\Illuminate\Support\Facades\Route::has('informationobject.export.rico'))
        <a href="{{ route('informationobject.export.rico', $__slug) }}" class="list-group-item list-group-item-action small"><i class="fas fa-code me-1"></i> {{ __('RiC-O JSON-LD') }}</a>
      @endif
      @auth
        @if(\Illuminate\Support\Facades\Route::has('informationobject.export.csv'))
          <a href="{{ route('informationobject.export.csv', $__slug) }}" class="list-group-item list-group-item-action small"><i class="fas fa-file-csv me-1"></i> CSV</a>
        @endif
      @endauth
    </div>
  </div>
@endif
