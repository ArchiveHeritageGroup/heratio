{{-- Object Security Classification - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/objectSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Security Classification')

@section('content')

<h1 class="multiline">
  Security classification
  <span class="sub">{{ $resource->title ?? $resource->identifier ?? '' }}</span>
</h1>

@if(request('success'))
  <div class="alert alert-success alert-dismissible fade show">
    @if(request('success') === 'classified')
      Security classification has been applied successfully.
    @elseif(request('success') === 'declassified')
      Security classification has been removed.
    @endif
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

<section id="content">

  {{-- Current Classification --}}
  <div class="card mb-4">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
      <h5 class="mb-0">
        <i class="fas fa-shield-alt me-2"></i>Security Classification
      </h5>
      <a href="{{ route('acl.classify', ['id' => $resource->id ?? 0]) }}" class="btn btn-sm btn-primary">
        <i class="fas fa-edit me-1"></i>{{ ($classification ?? null) ? 'Reclassify' : 'Classify' }}
      </a>
    </div>
    <div class="card-body">

      @if($classification ?? null)
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label text-muted small">{{ __('Classification Level') }}</label>
            <p class="mb-0">
              <span class="badge fs-6" style="background-color: {{ $classification->classificationColor ?? $classification->color ?? '#666' }};">
                <i class="{{ $classification->classificationIcon ?? $classification->icon ?? 'fa-lock' }} me-1"></i>
                {{ $classification->classificationName ?? $classification->name ?? '' }}
              </span>
            </p>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label text-muted small">{{ __('Classified By') }}</label>
            <p class="mb-0">{{ $classification->classifiedByUsername ?? $classification->classified_by_username ?? 'System' }}</p>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label text-muted small">{{ __('Classification Date') }}</label>
            <p class="mb-0">{{ ($classification->classifiedAt ?? $classification->classified_at ?? null) ? date('F j, Y', strtotime($classification->classifiedAt ?? $classification->classified_at)) : '-' }}</p>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label text-muted small">{{ __('Review Date') }}</label>
            <p class="mb-0">
              @if($classification->reviewDate ?? $classification->review_date ?? null)
                {{ date('F j, Y', strtotime($classification->reviewDate ?? $classification->review_date)) }}
                @if(strtotime($classification->reviewDate ?? $classification->review_date) <= time())
                  <span class="badge bg-warning text-dark ms-1">Due</span>
                @endif
              @else
                <span class="text-muted">Not set</span>
              @endif
            </p>
          </div>
          @if($classification->declassifyDate ?? $classification->declassify_date ?? null)
            <div class="col-md-6 mb-3">
              <label class="form-label text-muted small">{{ __('Auto-declassify Date') }}</label>
              <p class="mb-0">
                {{ date('F j, Y', strtotime($classification->declassifyDate ?? $classification->declassify_date)) }}
                @if(strtotime($classification->declassifyDate ?? $classification->declassify_date) <= time())
                  <span class="badge bg-info ms-1">Due</span>
                @endif
              </p>
            </div>
          @endif
          @if($classification->reason ?? null)
            <div class="col-12 mb-3">
              <label class="form-label text-muted small">{{ __('Classification Reason') }}</label>
              <p class="mb-0">{{ $classification->reason }}</p>
            </div>
          @endif
          @if($classification->handlingInstructions ?? $classification->handling_instructions ?? null)
            <div class="col-12">
              <label class="form-label text-muted small">{{ __('Handling Instructions') }}</label>
              <div class="alert alert-warning mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i>
                {{ $classification->handlingInstructions ?? $classification->handling_instructions }}
              </div>
            </div>
          @endif
        </div>

        {{-- Declassify Button --}}
        <div class="border-top mt-3 pt-3">
          <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#declassifyModal">
            <i class="fas fa-unlock me-1"></i>Remove Classification
          </button>
        </div>

      @else
        <div class="text-center py-4">
          <i class="fas fa-globe fa-3x text-success mb-3"></i>
          <h5>{{ __('This record is publicly accessible') }}</h5>
          <p class="text-muted">No security classification has been applied to this record.</p>
          <a href="{{ route('acl.classify', ['id' => $resource->id ?? 0]) }}" class="btn btn-primary">
            <i class="fas fa-lock me-1"></i>Apply Classification
          </a>
        </div>
      @endif

    </div>
  </div>

  {{-- Classification History --}}
  @if(!empty($history))
    <div class="card">
      <div class="card-header bg-light">
        <h5 class="mb-0">
          <i class="fas fa-history me-2"></i>Classification History
        </h5>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Action') }}</th>
                <th>{{ __('From') }}</th>
                <th>{{ __('To') }}</th>
                <th>{{ __('By') }}</th>
                <th>{{ __('Reason') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($history as $record)
                <tr>
                  <td>{{ date('Y-m-d H:i', strtotime($record->created_at)) }}</td>
                  <td>
                    <span class="badge {{ ($record->action ?? '') === 'declassified' ? 'bg-success' : (($record->action ?? '') === 'reclassified' ? 'bg-info' : 'bg-warning text-dark') }}">
                      {{ ucfirst($record->action ?? '') }}
                    </span>
                  </td>
                  <td>{{ $record->previous_name ?? '-' }}</td>
                  <td>{{ $record->new_name ?? '-' }}</td>
                  <td>{{ $record->changed_by_username ?? 'System' }}</td>
                  <td>{{ $record->reason ?? '-' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

</section>

{{-- Declassify Modal --}}
@if($classification ?? null)
<div class="modal fade" id="declassifyModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="{{ route('acl.declassify-store') }}">
        @csrf
        <input type="hidden" name="object_id" value="{{ $resource->id ?? '' }}">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title"><i class="fas fa-unlock me-2"></i>Remove Classification</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to remove the security classification from this record?</p>
          <p class="text-success"><i class="fas fa-globe me-1"></i>This record will become publicly accessible.</p>
          <div class="mb-3">
            <label class="form-label">Reason <span class="text-danger">*</span></label>
            <textarea name="reason" class="form-control" rows="2" required placeholder="{{ __('Enter reason for declassification...') }}"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-success"><i class="fas fa-unlock me-1"></i>Remove Classification</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

<section class="actions mt-4">
  <ul class="list-unstyled d-flex gap-2">
    <li><a href="{{ url('/' . ($resource->slug ?? $resource->id ?? '')) }}" class="btn btn-outline-secondary">View record</a></li>
  </ul>
</section>

@endsection
