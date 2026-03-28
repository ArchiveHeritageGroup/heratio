@extends('ahg-theme-b5::layout.1col')

@section('title')
  <h1><i class="fas fa-address-book me-2"></i>Contact Information</h1>
@endsection

@section('before-content')
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <a href="{{ route('actor.dashboard') }}">Authority Dashboard</a>
      </li>
      <li class="breadcrumb-item">
        <a href="{{ route('actor.show', $actor->slug ?? '') }}">{{ $actor->name ?? '' }}</a>
      </li>
      <li class="breadcrumb-item active">Contact</li>
    </ol>
  </nav>
@endsection

@section('content')

  <div class="card mb-3">
    <div class="card-header">
      <i class="fas fa-address-book me-1"></i>Contact details for {{ $actor->name ?? '' }}
    </div>
    <div class="card-body">
      @if($contacts->isEmpty())
        <p class="text-muted">No contact information available for this authority record.</p>
        <p>
          <a href="{{ route('actor.edit', $actor->slug ?? '') }}" class="btn btn-outline-primary">
            <i class="fas fa-edit me-1"></i>Edit actor record to add contacts
          </a>
        </p>
      @else
        <div class="row g-3">
          @foreach($contacts as $contact)
            <div class="col-md-6">
              <div class="card border">
                <div class="card-body">
                  @if(!empty($contact->contact_person))
                    <h6>{{ $contact->contact_person }}</h6>
                  @endif

                  @if(!empty($contact->street_address))
                    <p class="mb-1">
                      <i class="fas fa-map-marker-alt text-muted me-1"></i>
                      {!! nl2br(e($contact->street_address)) !!}
                      @if(!empty($contact->city))<br>{{ $contact->city }}@endif
                      @if(!empty($contact->region)), {{ $contact->region }}@endif
                      @if(!empty($contact->postal_code)) {{ $contact->postal_code }}@endif
                      @if(!empty($contact->country_code))<br>{{ $contact->country_code }}@endif
                    </p>
                  @endif

                  @if(!empty($contact->telephone))
                    <p class="mb-1"><i class="fas fa-phone text-muted me-1"></i>{{ $contact->telephone }}</p>
                  @endif
                  @if(!empty($contact->email))
                    <p class="mb-1"><i class="fas fa-envelope text-muted me-1"></i>
                      <a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>
                    </p>
                  @endif
                  @if(!empty($contact->website))
                    <p class="mb-0"><i class="fas fa-globe text-muted me-1"></i>
                      <a href="{{ $contact->website }}" target="_blank" rel="noopener">{{ $contact->website }}</a>
                    </p>
                  @endif
                </div>
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </div>
  </div>

@endsection
