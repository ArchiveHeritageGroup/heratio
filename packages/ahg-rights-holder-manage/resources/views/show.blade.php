@extends('theme::layouts.1col')

@section('title', $rightsHolder->authorized_form_of_name ?? 'Rights holder')
@section('body-class', 'view rightsholder')

@section('content')
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <h1>{{ $rightsHolder->authorized_form_of_name }}</h1>

  @auth
    <div class="mb-3">
      <a href="{{ route('rightsholder.edit', $rightsHolder->slug) }}" class="btn btn-sm btn-outline-primary">Edit</a>
      <a href="{{ route('rightsholder.confirmDelete', $rightsHolder->slug) }}" class="btn btn-sm btn-outline-danger">Delete</a>
      <a href="{{ route('rightsholder.create') }}" class="btn btn-sm btn-outline-success">Add new</a>
    </div>
  @endauth

  {{-- Identity area --}}
  <section class="mb-4">
    <h2 class="fs-5 border-bottom pb-2">Identity area</h2>

    @if($rightsHolder->authorized_form_of_name)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Authorized form of name</div>
        <div class="col-md-9">{{ $rightsHolder->authorized_form_of_name }}</div>
      </div>
    @endif
  </section>

  {{-- Contact information --}}
  @if(isset($contacts) && $contacts->isNotEmpty())
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Contact information</h2>
      @foreach($contacts as $contact)
        <div class="card mb-2">
          <div class="card-body">
            @if($contact->contact_person) <div><strong>Contact:</strong> {{ $contact->contact_person }}</div> @endif
            @if($contact->street_address) <div>{{ $contact->street_address }}</div> @endif
            @if($contact->city || $contact->region || $contact->postal_code)
              <div>{{ $contact->city ?? '' }}{{ $contact->region ? ', ' . $contact->region : '' }} {{ $contact->postal_code ?? '' }}</div>
            @endif
            @if($contact->country_code) <div>{{ $contact->country_code }}</div> @endif
            @if($contact->telephone) <div><strong>Tel:</strong> {{ $contact->telephone }}</div> @endif
            @if($contact->email) <div><strong>Email:</strong> {{ $contact->email }}</div> @endif
            @if($contact->website) <div><strong>Web:</strong> <a href="{{ $contact->website }}" target="_blank">{{ $contact->website }}</a></div> @endif
          </div>
        </div>
      @endforeach
    </section>
  @endif

  {{-- Related rights --}}
  @if($rights->isNotEmpty())
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Related rights</h2>

      @foreach($rights as $right)
        <div class="card mb-2">
          <div class="card-body">
            @if($right->basis_id && isset($basisNames[$right->basis_id]))
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Basis</div>
                <div class="col-md-9">{{ $basisNames[$right->basis_id] }}</div>
              </div>
            @endif

            @if($right->start_date || $right->end_date)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Dates</div>
                <div class="col-md-9">{{ $right->start_date ?? '?' }} - {{ $right->end_date ?? '?' }}</div>
              </div>
            @endif

            @if($right->copyright_jurisdiction)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Copyright jurisdiction</div>
                <div class="col-md-9">{{ $right->copyright_jurisdiction }}</div>
              </div>
            @endif

            @if($right->rights_note)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Rights note</div>
                <div class="col-md-9">{!! nl2br(e($right->rights_note)) !!}</div>
              </div>
            @endif

            @if($right->copyright_note)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Copyright note</div>
                <div class="col-md-9">{!! nl2br(e($right->copyright_note)) !!}</div>
              </div>
            @endif

            @if($right->license_terms)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">License terms</div>
                <div class="col-md-9">{!! nl2br(e($right->license_terms)) !!}</div>
              </div>
            @endif

            @if($right->license_note)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">License note</div>
                <div class="col-md-9">{!! nl2br(e($right->license_note)) !!}</div>
              </div>
            @endif

            @if($right->statute_jurisdiction)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Statute jurisdiction</div>
                <div class="col-md-9">{{ $right->statute_jurisdiction }}</div>
              </div>
            @endif

            @if($right->statute_note)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Statute note</div>
                <div class="col-md-9">{!! nl2br(e($right->statute_note)) !!}</div>
              </div>
            @endif
          </div>
        </div>
      @endforeach
    </section>
  @endif
@endsection
