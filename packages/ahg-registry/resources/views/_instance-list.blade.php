{{-- Cloned from atom-ahg-plugins/ahgRegistryPlugin/modules/registry/templates/_instanceList.php --}}
@php
    $statusColors = [
        'online' => 'success',
        'offline' => 'danger',
        'maintenance' => 'warning',
        'decommissioned' => 'secondary',
    ];
@endphp
@if (!empty($instances))
<div class="list-group list-group-flush">
  @foreach ($instances as $inst)
    @php
      $sColor = $statusColors[$inst->status ?? 'offline'] ?? 'secondary';
      $statusLabel = ucfirst($inst->status ?? 'offline');
      $editHref = !empty($canEdit) && \Illuminate\Support\Facades\Route::has('registry.myInstitutionInstanceEdit')
        ? route('registry.myInstitutionInstanceEdit', ['id' => (int) $inst->id])
        : null;
    @endphp
    <div class="list-group-item px-0">
      <div class="d-flex align-items-start">
        <!-- Status indicator -->
        <div class="me-2 mt-1 flex-shrink-0">
          <span class="d-inline-block rounded-circle bg-{{ $sColor }}" style="width: 10px; height: 10px;" title="{{ $statusLabel }}"></span>
        </div>

        <div class="flex-grow-1 min-width-0">
          <!-- Name and URL -->
          <strong class="small">{{ $inst->name ?? '' }}</strong>
          @if (!empty($inst->url))
            <br><a href="{{ $inst->url }}" class="small text-decoration-none" target="_blank" rel="noopener">
              {{ preg_replace('#^https?://#', '', $inst->url) }}
              <i class="fas fa-external-link-alt ms-1" style="font-size: 0.7em;"></i>
            </a>
          @endif

          <!-- Type badge + software -->
          <div class="mt-1">
            @if (!empty($inst->instance_type))
              <span class="badge bg-light text-dark border">{{ ucfirst(str_replace('_', ' ', $inst->instance_type)) }}</span>
            @endif
            @if (!empty($inst->software))
              <small class="text-muted">
                {{ $inst->software }}
                @if (!empty($inst->software_version))
                  <span class="badge bg-secondary">v{{ $inst->software_version }}</span>
                @endif
              </small>
            @elseif (!empty($inst->software_version))
              <span class="badge bg-secondary">v{{ $inst->software_version }}</span>
            @endif
          </div>

          <!-- Sync status -->
          @if (!empty($inst->sync_enabled))
            <div class="mt-1">
              <small class="text-muted">
                <i class="fas fa-sync-alt me-1"></i>
                @if (!empty($inst->last_heartbeat_at))
                  @php
                    $hbTime = strtotime($inst->last_heartbeat_at);
                    $hbDiff = time() - $hbTime;
                    if ($hbDiff < 60) $hbAgo = __('just now');
                    elseif ($hbDiff < 3600) $hbAgo = sprintf(__('%d min ago'), (int) floor($hbDiff / 60));
                    elseif ($hbDiff < 86400) $hbAgo = sprintf(__('%d hours ago'), (int) floor($hbDiff / 3600));
                    else $hbAgo = sprintf(__('%d days ago'), (int) floor($hbDiff / 86400));
                  @endphp
                  {{ __('Last sync: :ago', ['ago' => $hbAgo]) }}
                @else
                  {{ __('Never synced') }}
                @endif
              </small>
            </div>
          @endif

          <!-- Technical details -->
          <div class="mt-1">
            @if (!empty($inst->os_environment))
              <small class="text-muted me-2"><i class="fas fa-desktop me-1"></i>{{ $inst->os_environment }}</small>
            @endif
            @if (!empty($inst->hosting))
              <small class="text-muted me-2"><i class="fas fa-cloud me-1"></i>{{ ucfirst(str_replace('_', ' ', $inst->hosting)) }}</small>
            @endif
            @if (!empty($inst->descriptive_standard))
              <small class="text-muted me-2"><i class="fas fa-book me-1"></i>{{ $inst->descriptive_standard }}</small>
            @endif
            @if (!empty($inst->storage_gb))
              <small class="text-muted"><i class="fas fa-hdd me-1"></i>{{ number_format((float) $inst->storage_gb, 1) }} GB</small>
            @endif
          </div>

          <!-- Description -->
          @if (!empty($inst->description))
            <div class="mt-1">
              <small class="text-muted">{{ mb_strimwidth($inst->description, 0, 200, '...') }}</small>
            </div>
          @endif

          <!-- Record counts -->
          @if (!empty($inst->record_count) || !empty($inst->digital_object_count))
            <div class="mt-1">
              @if (!empty($inst->record_count))
                <small class="text-muted me-2"><i class="fas fa-database me-1"></i>{{ number_format((int) $inst->record_count) }} {{ __('records') }}</small>
              @endif
              @if (!empty($inst->digital_object_count))
                <small class="text-muted"><i class="fas fa-file-image me-1"></i>{{ number_format((int) $inst->digital_object_count) }} {{ __('digital objects') }}</small>
              @endif
            </div>
          @endif
        </div>

        <div class="ms-2 flex-shrink-0">
          <a href="/registry/instances/{{ (int) $inst->id }}" class="btn btn-sm btn-outline-primary" title="{{ __('View') }}">
            <i class="fas fa-eye"></i>
          </a>
          @if ($editHref)
          <a href="{{ $editHref }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Edit') }}">
            <i class="fas fa-edit"></i>
          </a>
          @endif
        </div>
      </div>
    </div>
  @endforeach
</div>
@else
<p class="text-muted small mb-0">{{ __('No instances listed.') }}</p>
@endif
