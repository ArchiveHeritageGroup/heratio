{{-- Extended Rights visual badges: RightsStatements.org, Creative Commons, TK Labels, Embargo --}}
@if((isset($extendedRights) && $extendedRights->isNotEmpty()) || (isset($activeEmbargo) && $activeEmbargo))
  <section id="rightsVisualBadges" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#rights-badges-collapse">Rights and licenses</a>
    </h2>
    <div id="rights-badges-collapse" class="p-3">
      <div class="d-flex flex-wrap gap-2 align-items-center">
        @if(isset($extendedRights))
          @foreach($extendedRights as $er)
            @if($er->rights_statement_code ?? null)
              <a href="{{ $er->rights_statement_uri ?? '#' }}" target="_blank" class="text-decoration-none" title="{{ $er->rights_statement_name ?? $er->rights_statement_code }}">
                <span class="badge bg-dark d-inline-flex align-items-center gap-1 py-2 px-3" style="font-size:.85rem;">
                  @if($er->rights_statement_icon ?? null)
                    <img src="{{ asset('vendor/ahg-theme-b5/images/rights/' . $er->rights_statement_icon) }}" alt="" style="height:16px;" class="me-1">
                  @else
                    <i class="fas fa-balance-scale me-1"></i>
                  @endif
                  {{ $er->rights_statement_name ?? $er->rights_statement_code }}
                </span>
              </a>
            @endif
            @if($er->cc_license_code ?? null)
              <a href="{{ $er->cc_license_uri ?? '#' }}" target="_blank" class="text-decoration-none" title="Creative Commons {{ strtoupper($er->cc_license_code) }}">
                <span class="badge d-inline-flex align-items-center gap-1 py-2 px-3" style="font-size:.85rem;background-color:#4a8c2a;color:#fff;">
                  <i class="fab fa-creative-commons me-1"></i> CC {{ strtoupper($er->cc_license_code) }}
                </span>
              </a>
            @endif
            @if(isset($extendedRightsTkLabels[$er->id]) && $extendedRightsTkLabels[$er->id]->isNotEmpty())
              @foreach($extendedRightsTkLabels[$er->id] as $tkl)
                <a href="{{ $tkl->uri ?? '#' }}" target="_blank" class="text-decoration-none" title="TK Label: {{ $tkl->code ?? '' }}">
                  <span class="badge d-inline-flex align-items-center gap-1 py-2 px-3" style="font-size:.85rem;background-color:{{ $tkl->color ?? '#6c757d' }};color:#fff;">
                    @if($tkl->icon_path ?? null)
                      <img src="{{ asset($tkl->icon_path) }}" alt="" style="height:16px;" class="me-1">
                    @else
                      <i class="fas fa-tag me-1"></i>
                    @endif
                    {{ $tkl->code ?? 'TK' }}
                  </span>
                </a>
              @endforeach
            @endif
          @endforeach
        @endif
        @if(isset($activeEmbargo) && $activeEmbargo)
          @php
            $embargoLabel = $activeEmbargo->is_perpetual ? 'Perpetual embargo' : 'Embargoed';
            $embargoEnd = $activeEmbargo->end_date ? ' until ' . $activeEmbargo->end_date : '';
            $embargoBadgeClass = $activeEmbargo->is_perpetual ? 'bg-danger' : 'bg-warning text-dark';
          @endphp
          <span class="badge {{ $embargoBadgeClass }} d-inline-flex align-items-center gap-1 py-2 px-3" style="font-size:.85rem;" title="{{ $activeEmbargo->embargo_type }}{{ $embargoEnd }}">
            <i class="fas fa-lock me-1"></i> {{ $embargoLabel }}{{ $embargoEnd }}
          </span>
        @endif
      </div>
    </div>
  </section>
@endif
