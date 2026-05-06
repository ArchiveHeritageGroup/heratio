
  {{-- ===== Security Classification ===== --}}
  {{-- Pulled out as its own section so it has a visible green heading like the
       ISAD areas and isn't hidden when the Conditions area is toggled off. --}}
  @if(!empty($security) && (
        !empty($security->classification_name)
     || !empty($security->reason)
     || !empty($security->review_date)
     || !empty($security->declassify_date)
     || !empty($security->handling_instructions)
     || !empty($security->watermark_name)
  ))
  <section id="securityClassificationArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#security-collapse">
        {{ __('Security Classification') }}
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}#security-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Security Classification') }}">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="security-collapse">
      @if(!empty($security->classification_name))
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Classification level') }}</h3>
          <div class="col-9 p-2">
            <span class="badge"
                  style="background:{{ $security->classification_color ?: '#6c757d' }};color:#fff;">
              @if(!empty($security->classification_icon))<i class="{{ $security->classification_icon }} me-1"></i>@endif
              {{ $security->classification_name }}
            </span>
          </div>
        </div>
      @endif
      @if(!empty($security->reason))
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Classification reason') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($security->reason)) !!}</div>
        </div>
      @endif
      @if(!empty($security->review_date))
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Review date') }}</h3>
          <div class="col-9 p-2">{{ $security->review_date }}</div>
        </div>
      @endif
      @if(!empty($security->declassify_date))
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Declassify date') }}</h3>
          <div class="col-9 p-2">{{ $security->declassify_date }}</div>
        </div>
      @endif
      @if(!empty($security->handling_instructions))
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Handling instructions') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($security->handling_instructions)) !!}</div>
        </div>
      @endif
      @if(!empty($security->watermark_name))
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Watermark') }}</h3>
          <div class="col-9 p-2">{{ $security->watermark_name }}</div>
        </div>
      @endif
    </div>
  </section>
  @endif

