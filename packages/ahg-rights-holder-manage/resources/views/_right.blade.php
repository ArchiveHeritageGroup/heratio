<div class="field">
  <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Related right') }}</h3>
  <div>
    @if(Auth::check() && Auth::user()->can('update', $relatedObject))
      <div>
        <a href="{{ route('right.edit', ['slug' => $resource->slug]) }}">{{ __('Edit') }}</a> |
        <a href="{{ route('right.delete', ['slug' => $resource->slug]) }}">{{ __('Delete') }}</a>
      </div>
    @endif

    <div>
      @if(isset($inherit))
        <a href="{{ route('informationobject.show', ['slug' => $inherit->slug]) }}" title="{{ __('Inherited from %1%', ['%1%' => $inherit->authorized_form_of_name ?? $inherit->title ?? '']) }}">
          {{ $inherit->authorized_form_of_name ?? $inherit->title ?? '' }}
        </a>
      @endif

      <div class="field">
        <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Basis') }}</h3>
        <div>{{ $resource->basis }}</div>
      </div>

      <div class="field">
        <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Start date') }}</h3>
        <div>{{ \AhgCore\Helpers\DateHelper::renderDate($resource->startDate) }}</div>
      </div>

      <div class="field">
        <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('End date') }}</h3>
        <div>{{ \AhgCore\Helpers\DateHelper::renderDate($resource->endDate) }}</div>
      </div>

      <div class="field">
        <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Documentation Identifier Type') }}</h3>
        <div>{{ $resource->identifierType }}</div>
      </div>

      <div class="field">
        <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Documentation Identifier Value') }}</h3>
        <div>{{ $resource->identifierValue }}</div>
      </div>

      <div class="field">
        <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Documentation Identifier Role') }}</h3>
        <div>{{ $resource->identifierRole }}</div>
      </div>

      @if(isset($resource->rightsHolder))
        <div class="field">
          <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Rights holder') }}</h3>
          <div><a href="{{ route('rightsholder.show', ['slug' => $resource->rightsHolder->slug]) }}">{{ $resource->rightsHolder }}</a></div>
        </div>
      @endif

      <div class="field">
        <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Rights note(s)') }}</h3>
        <div>{{ $resource->getRightsNote(['cultureFallback' => true]) }}</div>
      </div>

      @if(\AhgCore\Constants\QubitTerm::RIGHT_BASIS_COPYRIGHT_ID == $resource->basisId)

        <div class="field">
          <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Copyright status') }}</h3>
          <div>{{ $resource->copyrightStatus }}</div>
        </div>

        <div class="field">
          <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Copyright status determination date') }}</h3>
          <div>{{ $resource->copyrightStatusDate }}</div>
        </div>

        <div class="field">
          <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Copyright jurisdiction') }}</h3>
          <div>{{ $resource->copyrightJurisdiction }}</div>
        </div>

        <div class="field">
          <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Copyright note') }}</h3>
          <div>{{ $resource->getCopyrightNote(['cultureFallback' => true]) }}</div>
        </div>

      @elseif(\AhgCore\Constants\QubitTerm::RIGHT_BASIS_LICENSE_ID == $resource->basisId)

        <div class="field">
          <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('License identifier') }}</h3>
          <div>{{ $resource->getIdentifierValue(['cultureFallback' => true]) }}</div>
        </div>

        <div class="field">
          <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('License terms') }}</h3>
          <div>{{ $resource->getLicenseTerms(['cultureFallback' => true]) }}</div>
        </div>

        <div class="field">
          <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('License note') }}</h3>
          <div>{{ $resource->getLicenseNote(['cultureFallback' => true]) }}</div>
        </div>

      @elseif(\AhgCore\Constants\QubitTerm::RIGHT_BASIS_STATUTE_ID == $resource->basisId)

        <div class="field">
          <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Statute jurisdiction') }}</h3>
          <div>{{ $resource->getStatuteJurisdiction(['cultureFallback' => true]) }}</div>
        </div>

        @if(null !== $statuteCitation = $resource->statuteCitation)
          <div class="field">
            <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Statute citation') }}</h3>
            <div>{{ $statuteCitation->getName(['cultureFallback' => true]) }}</div>
          </div>
        @endif

        <div class="field">
          <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Statute determination date') }}</h3>
          <div>{{ $resource->statuteDeterminationDate }}</div>
        </div>

        <div class="field">
          <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Statute note') }}</h3>
          <div>{{ $resource->getStatuteNote(['cultureFallback' => true]) }}</div>
        </div>

      @endif

      <blockquote class="border-bottom m-0 mt-1">
        @foreach($resource->grantedRights as $grantedRight)
          <div class="border border-bottom-0 px-2 py-1">
            <div class="field">
              <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Act') }}</h3>
              <div>{{ $grantedRight->act }}</div>
            </div>
            <div class="field">
              <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Restriction') }}</h3>
              <div>{{ \AhgCore\Models\GrantedRight::getRestrictionString($grantedRight->restriction) }}</div>
            </div>
            <div class="field">
              <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Start date') }}</h3>
              <div>{{ \AhgCore\Helpers\DateHelper::renderDate($grantedRight->startDate) }}</div>
            </div>
            <div class="field">
              <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('End date') }}</h3>
              <div>{{ \AhgCore\Helpers\DateHelper::renderDate($grantedRight->endDate) }}</div>
            </div>
            <div class="field">
              <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Notes') }}</h3>
              <div>{{ $grantedRight->notes }}</div>
            </div>
          </div>
        @endforeach
      </blockquote>
    </div>
  </div>
</div>
