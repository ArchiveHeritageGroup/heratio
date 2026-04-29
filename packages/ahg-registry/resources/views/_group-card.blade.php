{{-- Cloned from atom-ahg-plugins/ahgRegistryPlugin/modules/registry/templates/_groupCard.php --}}
@php
    $gtBg = [
        'regional' => 'bg-primary',
        'topic' => 'bg-info text-dark',
        'software' => 'bg-success',
        'institutional' => 'bg-warning text-dark',
    ];
    $gt = $item->group_type ?? '';
    $gtClass = $gtBg[$gt] ?? 'bg-secondary';
    $href = \Illuminate\Support\Facades\Route::has('registry.groupView')
        ? route('registry.groupView', ['id' => (int) ($item->id ?? 0)])
        : url('/registry/group/' . ($item->id ?? 0));
@endphp
<div class="col">
  <div class="card h-100">
    <div class="card-body">
      <div class="d-flex align-items-start mb-2">
        @if (!empty($item->logo_path))
          <img src="{{ $item->logo_path }}" alt="" class="rounded me-3 flex-shrink-0" style="width: 48px; height: 48px; object-fit: contain;">
        @else
          <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
            <i class="fas fa-users text-muted"></i>
          </div>
        @endif
        <div class="min-width-0">
          <h6 class="card-title mb-1">
            <a href="{{ $href }}" class="text-decoration-none stretched-link">
              {{ $item->name ?? '' }}
            </a>
            @if (!empty($item->is_verified))
              <i class="fas fa-check-circle text-primary ms-1" title="{{ __('Verified') }}"></i>
            @endif
          </h6>
          <span class="badge {{ $gtClass }}">{{ ucfirst(str_replace('_', ' ', $gt)) }}</span>
        </div>
      </div>

      @if (!empty($item->is_virtual))
        <div class="small text-muted mb-2">
          <span class="badge bg-light text-dark border"><i class="fas fa-globe me-1"></i>{{ __('Virtual') }}</span>
        </div>
      @elseif (!empty($item->city) || !empty($item->country))
        <div class="small text-muted mb-2">
          <i class="fas fa-map-marker-alt me-1"></i>
          {{ implode(', ', array_filter([$item->city ?? '', $item->country ?? ''])) }}
        </div>
      @endif

      <div class="small text-muted mb-2">
        <i class="fas fa-users me-1"></i>
        {{ (int) ($item->member_count ?? 0) }} {{ __('members') }}
      </div>

      @if (!empty($item->meeting_frequency))
      <div class="small text-muted mb-2">
        <i class="fas fa-calendar me-1"></i>
        {{ ucfirst(str_replace('_', ' ', $item->meeting_frequency)) }}
      </div>
      @endif

      @php $desc = $item->description ?? ''; @endphp
      @if (!empty($desc))
      <p class="card-text small text-muted mb-0">
        {{ mb_strimwidth(strip_tags($desc), 0, 100, '...') }}
      </p>
      @endif
    </div>
  </div>
</div>
