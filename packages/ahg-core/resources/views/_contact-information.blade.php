<section class="contact-info">
  @if(!empty($contactInformation->contactPerson))
    <div class="field">
      <div class="agent">
        <span class="text-primary">
          {{ $contactInformation->contactPerson }}
        </span>
        @if($contactInformation->primaryContact ?? false)
          <span class="primary-contact">
            {{ __('Primary contact') }}
          </span>
        @endif
      </div>
    </div>
  @endif

  @if($contactInformation->contactType ?? ($contactInformation->contact_type ?? null))
    <div class="field">
      <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Type') }}</h3>
      <div>{{ $contactInformation->contactType ?? $contactInformation->contact_type ?? '' }}</div>
    </div>
  @endif

  <div class="field adr">
    <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Address') }}</h3>
    <div>

      @if($contactInformation->streetAddress ?? null)
        <div class="field">
          <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Street address') }}</h3>
          <div>{{ $contactInformation->streetAddress }}</div>
        </div>
      @endif

      @if($contactInformation->city ?? null)
        <div class="field">
          <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Locality') }}</h3>
          <div>{{ $contactInformation->city }}</div>
        </div>
      @endif

      @if($contactInformation->region ?? null)
        <div class="field">
          <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Region') }}</h3>
          <div>{{ $contactInformation->region }}</div>
        </div>
      @endif

      @if($contactInformation->countryCode ?? null)
        <div class="field">
          <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Country name') }}</h3>
          <div>{{ locale_get_display_region('-' . $contactInformation->countryCode, app()->getLocale()) }}</div>
        </div>
      @endif

      @if($contactInformation->postalCode ?? null)
        <div class="field">
          <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Postal code') }}</h3>
          <div>{{ $contactInformation->postalCode }}</div>
        </div>
      @endif

    </div>

  </div>

  @if($contactInformation->telephone ?? null)
    <div class="field">
      <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Telephone') }}</h3>
      <div class="tel">{{ $contactInformation->telephone }}</div>
    </div>
  @endif

  @if($contactInformation->fax ?? null)
    <div class="field">
      <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Fax') }}</h3>
      <div class="fax">{{ $contactInformation->fax }}</div>
    </div>
  @endif

  @if($contactInformation->email ?? null)
    <div class="field">
      <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Email') }}</h3>
      <div class="email">{{ $contactInformation->email }}</div>
    </div>
  @endif

  @if($contactInformation->website ?? null)
    <div class="field">
      <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('URL') }}</h3>
      <div class="url">{{ $contactInformation->website }}</div>
    </div>
  @endif

  @if($contactInformation->note ?? null)
    <div class="field">
      <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Note') }}</h3>
      <div class="note">{!! nl2br(e($contactInformation->note)) !!}</div>
    </div>
  @endif
</section>
