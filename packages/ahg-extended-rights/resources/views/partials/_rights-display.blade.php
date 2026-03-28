{{-- Rights display component for show pages --}}
{{-- Usage: @include('ahg-extended-rights::partials._rights-display', ['rights' => $rightsRecords]) --}}
@if(!empty($rights) && count($rights))
<div class="rights-display mb-3">
  @foreach($rights as $right)
  <div class="card card-body mb-2 p-2">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        @if(!empty($right->statement_name))
          <span class="badge bg-primary">{{ e($right->statement_name) }}</span>
        @endif
        @if(!empty($right->cc_license_name))
          <span class="badge bg-success"><i class="fab fa-creative-commons"></i> {{ e($right->cc_license_name) }}</span>
        @endif
        @if(!empty($right->basis))
          <small class="text-muted ms-2">Basis: {{ e($right->basis) }}</small>
        @endif
      </div>
      @if(!empty($right->rights_date))
        <small class="text-muted">{{ $right->rights_date }}</small>
      @endif
    </div>
    @if(!empty($right->rights_note))
      <small class="text-muted mt-1">{{ e($right->rights_note) }}</small>
    @endif
  </div>
  @endforeach
</div>
@endif
