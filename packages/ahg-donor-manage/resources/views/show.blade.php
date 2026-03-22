@extends('theme::layouts.1col')

@section('title', $donor->authorized_form_of_name ?? 'Donor')
@section('body-class', 'view donor')

@section('content')
  <h1>{{ $donor->authorized_form_of_name ?? '[Untitled]' }}</h1>

  @auth
    <div class="mb-3">
      <a href="{{ route('donor.edit', $donor->slug) }}" class="btn btn-sm atom-btn-white">Edit</a>
      <a href="{{ route('donor.confirmDelete', $donor->slug) }}" class="btn btn-sm atom-btn-outline-danger">Delete</a>
      <a href="{{ route('donor.create') }}" class="btn btn-sm atom-btn-outline-success">Add new</a>
    </div>
  @endauth

  {{-- Identity area --}}
  <section class="mb-4">
    <h2 class="fs-5 border-bottom pb-2">Identity area</h2>

    @if($donor->authorized_form_of_name)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Authorized form of name</div>
        <div class="col-md-9">{{ $donor->authorized_form_of_name }}</div>
      </div>
    @endif

    @if($donor->description_identifier)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Identifier</div>
        <div class="col-md-9">{{ $donor->description_identifier }}</div>
      </div>
    @endif
  </section>

  {{-- Description area --}}
  @if($donor->history)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Description area</h2>

      <div class="row mb-2">
        <div class="col-md-3 fw-bold">History</div>
        <div class="col-md-9">{!! nl2br(e($donor->history)) !!}</div>
      </div>
    </section>
  @endif

  {{-- Contact information --}}
  @if($contacts->isNotEmpty())
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Contact information</h2>
      @foreach($contacts as $contact)
        <div class="card mb-2">
          <div class="card-body">
            @if($contact->contact_person) <div><strong>Contact:</strong> {{ $contact->contact_person }}</div> @endif
            @if($contact->street_address) <div>{{ $contact->street_address }}</div> @endif
            @if($contact->city) <div>{{ $contact->city }}{{ $contact->region ? ', ' . $contact->region : '' }} {{ $contact->postal_code ?? '' }}</div> @endif
            @if($contact->country_code) <div>{{ $contact->country_code }}</div> @endif
            @if($contact->telephone) <div><strong>Tel:</strong> {{ $contact->telephone }}</div> @endif
            @if($contact->email) <div><strong>Email:</strong> {{ $contact->email }}</div> @endif
            @if($contact->website) <div><strong>Web:</strong> <a href="{{ $contact->website }}">{{ $contact->website }}</a></div> @endif
          </div>
        </div>
      @endforeach
    </section>
  @endif

  {{-- Related accessions --}}
  @if($accessions->isNotEmpty())
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Related accessions</h2>
      <div class="table-responsive">
        <table class="table table-bordered table-striped mb-0">
          <thead>
            <tr style="background:var(--ahg-primary);color:#fff">
              <th>Identifier</th>
              <th>Title</th>
            </tr>
          </thead>
          <tbody>
            @foreach($accessions as $accession)
              <tr>
                <td>{{ $accession->identifier ?? '' }}</td>
                <td>
                  <a href="{{ route('accession.show', $accession->slug) }}">
                    {{ $accession->title ?: '[Untitled]' }}
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>
  @endif
@endsection
