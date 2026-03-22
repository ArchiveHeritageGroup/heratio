@extends('theme::layouts.1col')

@section('title', $rightsHolder->authorized_form_of_name ?? 'Rights holder')
@section('body-class', 'view rightsholder')

@section('content')
  <h1>{{ $rightsHolder->authorized_form_of_name }}</h1>

  @auth
    <div class="mb-3">
      <a href="{{ route('rightsholder.edit', $rightsHolder->slug) }}" class="btn btn-sm atom-btn-white">Edit</a>
      <a href="{{ route('rightsholder.confirmDelete', $rightsHolder->slug) }}" class="btn btn-sm atom-btn-outline-danger">Delete</a>
      <a href="{{ route('rightsholder.create') }}" class="btn btn-sm atom-btn-outline-success">Add new</a>
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

  {{-- Extended rights --}}
  @if(isset($extendedRights) && $extendedRights->isNotEmpty())
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Extended rights</h2>

      @foreach($extendedRights as $er)
        <div class="card mb-2">
          <div class="card-body">
            @if($er->object_title)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Related object</div>
                <div class="col-md-9">
                  @if($er->object_slug)
                    <a href="{{ route('informationobject.show', $er->object_slug) }}">{{ $er->object_title }}</a>
                  @else
                    {{ $er->object_title }}
                  @endif
                </div>
              </div>
            @endif

            @if($er->rights_statement_name || $er->rights_statement_code)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Rights statement</div>
                <div class="col-md-9">
                  @if($er->rights_statement_uri)
                    <a href="{{ $er->rights_statement_uri }}" target="_blank">{{ $er->rights_statement_name ?? $er->rights_statement_code }}</a>
                  @else
                    {{ $er->rights_statement_name ?? $er->rights_statement_code }}
                  @endif
                </div>
              </div>
            @endif

            @if($er->cc_license_code)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Creative Commons</div>
                <div class="col-md-9">
                  @if($er->cc_license_uri)
                    <a href="{{ $er->cc_license_uri }}" target="_blank">CC {{ strtoupper($er->cc_license_code) }}</a>
                  @else
                    CC {{ strtoupper($er->cc_license_code) }}
                  @endif
                </div>
              </div>
            @endif

            @if($er->rights_holder)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Rights holder</div>
                <div class="col-md-9">
                  @if($er->rights_holder_uri)
                    <a href="{{ $er->rights_holder_uri }}" target="_blank">{{ $er->rights_holder }}</a>
                  @else
                    {{ $er->rights_holder }}
                  @endif
                </div>
              </div>
            @endif

            @if($er->copyright_notice)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Copyright notice</div>
                <div class="col-md-9">{!! nl2br(e($er->copyright_notice)) !!}</div>
              </div>
            @endif

            @if($er->usage_conditions)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Usage conditions</div>
                <div class="col-md-9">{!! nl2br(e($er->usage_conditions)) !!}</div>
              </div>
            @endif

            @if($er->rights_note)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Rights note</div>
                <div class="col-md-9">{!! nl2br(e($er->rights_note)) !!}</div>
              </div>
            @endif

            @if($er->rights_date)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Rights date</div>
                <div class="col-md-9">{{ $er->rights_date }}@if($er->expiry_date) &ndash; {{ $er->expiry_date }}@endif</div>
              </div>
            @endif

            @if(isset($extendedRightsTkLabels[$er->id]) && $extendedRightsTkLabels[$er->id]->isNotEmpty())
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">TK Labels</div>
                <div class="col-md-9">
                  @foreach($extendedRightsTkLabels[$er->id] as $tkl)
                    <span class="badge bg-secondary me-1">{{ $tkl->code ?? $tkl->id }}</span>
                  @endforeach
                </div>
              </div>
            @endif
          </div>
        </div>
      @endforeach
    </section>
  @endif
@endsection
