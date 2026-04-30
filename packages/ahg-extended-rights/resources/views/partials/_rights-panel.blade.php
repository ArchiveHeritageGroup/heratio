{{--
  Unified Rights Panel Component
  Include in ISAD and Museum detail pages:
  @include('ahg-extended-rights::partials._rights-panel', ['resource' => $resource])

  Expects $resource with ->id and ->slug properties.
  Service data is loaded automatically.
--}}
@php
  $rightsService = app(\AhgExtendedRights\Services\ExtendedRightsService::class);
  $objectId = $resource->id ?? null;
  $canEdit = auth()->check() && (auth()->user()->is_admin ?? false);

  if ($objectId) {
      $extRights = $rightsService->getRightsForObject($objectId);
      $extEmbargo = $rightsService->getEmbargo($objectId);
      $extTkLabels = $rightsService->getTkLabelsForObject($objectId);
      $extOrphanWork = $rightsService->getOrphanWork($objectId);
      $extAccessCheck = $rightsService->checkAccess($objectId, auth()->id());
  } else {
      $extRights = collect();
      $extEmbargo = null;
      $extTkLabels = collect();
      $extOrphanWork = null;
      $extAccessCheck = ['accessible' => true, 'restrictions' => []];
  }

  $slug = $resource->slug ?? '';
@endphp

@if($objectId && ($extRights->count() > 0 || $extEmbargo || $extTkLabels->count() > 0 || $extOrphanWork))
<section id="rightsArea" class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <h5 class="mb-0">
            <i class="fas fa-balance-scale me-2"></i>{{ __('Rights') }}
        </h5>
        @if($canEdit)
            <a href="{{ route('ext-rights.add', $slug) }}" class="btn btn-sm btn-light">
                <i class="fas fa-plus me-1"></i>{{ __('Add rights') }}
            </a>
        @endif
    </div>

    <div class="card-body">
        @if(!($extAccessCheck['accessible'] ?? true))
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>{{ __('Access Restricted') }}</strong>
                @if(!empty($extAccessCheck['restrictions']))
                <ul class="mb-0 mt-2">
                    @foreach($extAccessCheck['restrictions'] as $restriction)
                    <li>{{ ucfirst($restriction['type'] ?? '') }}
                        @if(isset($restriction['reason'])) - {{ ucfirst(str_replace('_', ' ', $restriction['reason'])) }} @endif
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>
        @endif

        @if($extEmbargo)
            <div class="alert alert-danger mb-3">
                <div class="d-flex align-items-start">
                    <i class="fas fa-lock fa-2x me-3 mt-1"></i>
                    <div class="flex-grow-1">
                        <h6 class="alert-heading mb-1">{{ __('Under Embargo') }}</h6>
                        <p class="mb-1">
                            <strong>{{ __('Type:') }}</strong>
                            {{ ucwords(str_replace('_', ' ', $extEmbargo->embargo_type ?? '')) }}
                        </p>
                        <p class="mb-1">
                            <strong>{{ __('Reason:') }}</strong>
                            {{ ucwords(str_replace('_', ' ', $extEmbargo->reason ?? '')) }}
                        </p>
                        @if($extEmbargo->end_date)
                            <p class="mb-0">
                                <strong>{{ __('Until:') }}</strong>
                                {{ \Carbon\Carbon::parse($extEmbargo->end_date)->format('j F Y') }}
                                @if($extEmbargo->auto_release ?? false)
                                    <span class="badge bg-info ms-2">{{ __('Auto-release') }}</span>
                                @endif
                            </p>
                        @else
                            <p class="mb-0"><em>No end date specified</em></p>
                        @endif
                        @if($canEdit)
                            <div class="mt-2">
                                <a href="{{ route('ext-rights.edit-embargo', $slug) }}" class="btn btn-sm btn-outline-light">
                                    <i class="fas fa-edit me-1"></i>{{ __('Edit') }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if($extTkLabels->count() > 0)
            <div class="mb-3">
                <h6 class="text-muted mb-2">{{ __('Traditional Knowledge Labels') }}</h6>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($extTkLabels as $label)
                        <span class="badge" style="background-color: {{ $label->color ?? '#666' }}; font-size: 0.9em;"
                              data-bs-toggle="tooltip" title="{{ $label->description ?? '' }}">
                            {{ $label->name ?? '' }}
                        </span>
                    @endforeach
                </div>
                @if($canEdit)
                    <a href="{{ route('ext-rights.tk-labels', $slug) }}" class="btn btn-sm btn-link p-0 mt-1">
                        <i class="fas fa-edit me-1"></i>{{ __('Manage TK Labels') }}
                    </a>
                @endif
            </div>
        @endif

        @if($extOrphanWork)
            <div class="alert alert-info mb-3">
                <h6 class="alert-heading">
                    <i class="fas fa-search me-2"></i>{{ __('Orphan Work') }}
                </h6>
                <p class="mb-1">
                    <strong>{{ __('Status:') }}</strong>
                    @php
                        $owColor = match($extOrphanWork->status ?? '') {
                            'in_progress' => 'warning', 'completed' => 'success', 'rights_holder_found' => 'info', default => 'secondary'
                        };
                    @endphp
                    <span class="badge bg-{{ $owColor }}">{{ ucwords(str_replace('_', ' ', $extOrphanWork->status ?? '')) }}</span>
                </p>
                @if($extOrphanWork->search_completed_date ?? null)
                    <p class="mb-0 small">Diligent search completed: {{ \Carbon\Carbon::parse($extOrphanWork->search_completed_date)->format('j F Y') }}</p>
                @endif
                @if($canEdit)
                    <a href="{{ route('ext-rights.orphan-work', $slug) }}" class="btn btn-sm btn-link p-0 mt-1">View/Edit Details</a>
                @endif
            </div>
        @endif

        @if($extRights->count() === 0)
            <p class="text-muted mb-0">
                <i class="fas fa-info-circle me-1"></i>
                {{ __('No rights records have been added.') }}
            </p>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Basis') }}</th>
                            <th>{{ __('Rights Statement') }}</th>
                            <th>{{ __('Acts') }}</th>
                            <th>{{ __('Dates') }}</th>
                            @if($canEdit)
                                <th style="width: 100px;">{{ __('Actions') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($extRights as $right)
                            <tr>
                                <td>
                                    @php
                                        $bColor = match($right->basis ?? '') {
                                            'copyright' => 'primary', 'license' => 'success', 'statute' => 'info', 'donor' => 'secondary', 'policy' => 'warning', default => 'dark'
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $bColor }}">{{ ucfirst($right->basis ?? '') }}</span>
                                    @if(($right->basis ?? '') === 'copyright' && ($right->copyright_status ?? null))
                                        <br><small class="text-muted">{{ ucwords(str_replace('_', ' ', $right->copyright_status)) }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($right->rights_statement_name ?? null)
                                        <a href="{{ $right->rights_statement_uri }}" target="_blank" class="text-decoration-none">
                                            {{ $right->rights_statement_name }}
                                            <i class="fas fa-external-link-alt fa-xs ms-1"></i>
                                        </a>
                                    @elseif($right->cc_license_name ?? null)
                                        <a href="{{ $right->cc_license_uri }}" target="_blank" class="text-decoration-none">
                                            <i class="fab fa-creative-commons me-1"></i>
                                            {{ $right->cc_license_name }}
                                        </a>
                                    @elseif($right->rights_holder_name ?? null)
                                        {{ $right->rights_holder_name }}
                                    @else
                                        <span class="text-muted">&mdash;</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $grants = \Illuminate\Support\Facades\Schema::hasTable('rights_grant')
                                            ? \Illuminate\Support\Facades\DB::table('rights_grant')->where('rights_record_id', $right->id)->get()
                                            : collect();
                                    @endphp
                                    @if($grants->count() > 0)
                                        @foreach($grants as $grant)
                                            @php
                                                $gColor = match($grant->restriction ?? 'allow') {
                                                    'allow' => 'success', 'disallow' => 'danger', default => 'warning'
                                                };
                                            @endphp
                                            <span class="badge bg-{{ $gColor }} me-1">{{ ucfirst($grant->act ?? '') }}</span>
                                        @endforeach
                                    @else
                                        <span class="text-muted">&mdash;</span>
                                    @endif
                                </td>
                                <td class="small">
                                    @if(($right->start_date ?? null) || ($right->end_date ?? null))
                                        {{ $right->start_date ? \Carbon\Carbon::parse($right->start_date)->format('Y') : '...' }}
                                        &ndash;
                                        {{ $right->end_date ? \Carbon\Carbon::parse($right->end_date)->format('Y') : (($right->end_date_open ?? 0) ? 'Open' : '...') }}
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                @if($canEdit)
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('ext-rights.edit', [$slug, $right->id]) }}"
                                               class="btn btn-outline-secondary btn-sm" title="{{ __('Edit') }}">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                            @if($right->rights_note ?? null)
                                <tr class="table-light">
                                    <td colspan="{{ $canEdit ? 5 : 4 }}" class="small text-muted py-1 ps-4">
                                        <i class="fas fa-comment me-1"></i>{{ $right->rights_note }}
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
@endif
