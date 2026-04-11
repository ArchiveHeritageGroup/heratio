@extends('theme::layouts.2col')

@section('title', 'Traditional Knowledge Labels')
@section('body-class', 'admin rights-admin tk-labels')

@section('sidebar')
  @include('ahg-extended-rights::admin._sidebar')
@endsection

@section('title-block')
  <div class="d-flex justify-content-between align-items-center">
    <h1 class="mb-0"><i class="fas fa-tags me-2"></i>Traditional Knowledge Labels</h1>
    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#assignModal">
      <i class="fas fa-plus me-1"></i> Assign Label
    </button>
  </div>
@endsection

@section('content')
  {{-- Available Labels --}}
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">Available TK Labels</h5>
    </div>
    <div class="card-body">
      <p class="text-muted mb-3">
        Traditional Knowledge Labels are part of the <a href="https://localcontexts.org" target="_blank">Local Contexts</a> initiative
        to support Indigenous communities in the management of their cultural heritage and intellectual property.
      </p>

      <div class="row">
        @php $currentCategory = ''; @endphp
        @foreach($tkLabels as $label)
          @if(($label->category ?? '') !== $currentCategory)
            @php $currentCategory = $label->category ?? ''; @endphp
            <div class="col-12 mt-3 mb-2">
              <h6 class="text-uppercase text-muted">
                @switch($currentCategory)
                  @case('tk') Traditional Knowledge Labels @break
                  @case('bc') Biocultural Labels @break
                  @case('attribution') Attribution Labels @break
                  @default {{ $currentCategory }}
                @endswitch
              </h6>
            </div>
          @endif

          <div class="col-md-6 col-lg-4 mb-3">
            <div class="d-flex align-items-start p-2 border rounded">
              <span class="badge me-3 text-white" style="background-color: {{ $label->color ?? '#6c757d' }}; min-width: 50px; padding: 8px;">
                {{ $label->code ?? '' }}
              </span>
              <div class="small">
                <strong>{{ $label->name ?? '' }}</strong>
                <br>
                <span class="text-muted">{{ $label->description ?? '' }}</span>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Current Assignments --}}
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">Label Assignments</h5>
    </div>
    <div class="card-body p-0">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Label</th>
            <th>Object</th>
            <th>Community</th>
            <th>Verified</th>
            <th>Assigned</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($assignments as $assign)
          <tr>
            <td>
              <span class="badge text-white" style="background-color: {{ $assign->color ?? '#6c757d' }};">
                {{ $assign->code ?? '' }}
              </span>
              {{ $assign->label_name ?? '' }}
            </td>
            <td>
              <a href="{{ $assign->slug ? url($assign->slug) : '#' }}">
                {{ $assign->object_title ?: 'ID: ' . $assign->object_id }}
              </a>
            </td>
            <td>{{ $assign->community_name ?: '-' }}</td>
            <td>
              @if($assign->verified ?? false)
                <i class="fas fa-check-circle text-success"></i>
                @if($assign->verified_by ?? null)
                  <small class="text-muted">{{ $assign->verified_by }}</small>
                @endif
              @else
                <i class="fas fa-clock text-warning"></i> Pending
              @endif
            </td>
            <td>{{ $assign->created_at ? \Carbon\Carbon::parse($assign->created_at)->format('d M Y') : '-' }}</td>
            <td>
              <a href="{{ route('ext-rights-admin.remove-tk-label', ['object_id' => $assign->object_id, 'label_id' => $assign->tk_label_id]) }}"
                 class="btn btn-sm btn-outline-danger"
                 onclick="return confirm('Remove this TK Label assignment?');">
                <i class="fas fa-times"></i>
              </a>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center text-muted py-4">No TK Labels have been assigned yet.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection

{{-- Assign Modal --}}
@push('modals')
<div class="modal fade" id="assignModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form action="{{ route('ext-rights-admin.assign-tk-label') }}" method="post">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Assign TK Label</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Object ID <span class="text-danger">*</span></label>
              <input type="number" name="object_id" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">TK Label <span class="text-danger">*</span></label>
              <select name="tk_label_id" class="form-select" required>
                <option value="">- Select Label -</option>
                @foreach($tkLabels as $label)
                <option value="{{ $label->id }}">{{ $label->code ?? '' }} - {{ $label->name ?? '' }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Community Name</label>
            <input type="text" name="community_name" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Community Contact</label>
            <textarea name="community_contact" class="form-control" rows="2" placeholder="Contact information for the community"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Custom Text</label>
            <textarea name="custom_text" class="form-control" rows="2" placeholder="Optional custom description from the community"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Assign Label</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endpush
