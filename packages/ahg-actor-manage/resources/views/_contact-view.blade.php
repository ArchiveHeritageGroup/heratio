@php
$contactRepo = new \AhgCore\Repositories\ContactInformationRepository();
$contacts = $contactRepo->getByActorId($resource->id);

if ($contacts->isEmpty()) {
    return;
} @endphp

@foreach($contacts as $contact)
  @if($contact->contact_person)
    <div class="field">
      <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Contact person') }}</h3>
      <div>{{ e($contact->contact_person) }}</div>
    </div>
  @endif

  @php $addressParts = array_filter([
      $contact->street_address,
      $contact->city,
      $contact->region,
      $contact->postal_code,
      $contact->country_code
  ]);
  @endphp
  @if(!empty($addressParts))
    <div class="field">
      <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Address') }}</h3>
      <div>{{ e(implode(', ', $addressParts)) }}</div>
    </div>
  @endif

  @if($contact->telephone)
    <div class="field">
      <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Telephone') }}</h3>
      <div><a href="tel:{{ e($contact->telephone) }}">{{ e($contact->telephone) }}</a></div>
    </div>
  @endif

  @if($contact->fax)
    <div class="field">
      <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Fax') }}</h3>
      <div>{{ e($contact->fax) }}</div>
    </div>
  @endif

  @if($contact->email)
    <div class="field">
      <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Email') }}</h3>
      <div><a href="mailto:{{ e($contact->email) }}">{{ e($contact->email) }}</a></div>
    </div>
  @endif

  @if($contact->website)
    <div class="field">
      <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Website') }}</h3>
      <div><a href="{{ e($contact->website) }}" target="_blank" rel="noopener">{{ e($contact->website) }} <i class="fas fa-external-link-alt fa-xs"></i></a></div>
    </div>
  @endif

  @if($contact->note)
    <div class="field">
      <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Note') }}</h3>
      <div>{!! nl2br(e($contact->note)) !!}</div>
    </div>
  @endif

  @if($contact->primary_contact)
    <div class="field">
      <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Primary contact') }}</h3>
      <div><span class="badge bg-success">{{ __('Yes') }}</span></div>
    </div>
  @endif

  @if($contacts->count() > 1 && $contact !== $contacts->last())
    <hr class="my-3">
  @endif
@endforeach
