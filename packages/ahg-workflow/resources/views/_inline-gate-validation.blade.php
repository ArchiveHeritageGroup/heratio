{{-- Partial: Inline gate validation --}}
@props(['gates' => collect()])
<div class="gate-validation-inline">@foreach($gates as $gate)<div class="d-flex align-items-center gap-2 mb-1"><i class="fas fa-{{ ($gate->passed ?? false) ? 'check-circle text-success' : 'times-circle text-danger' }}"></i><small>{{ $gate->name ?? 'Gate' }}</small></div>@endforeach</div>
