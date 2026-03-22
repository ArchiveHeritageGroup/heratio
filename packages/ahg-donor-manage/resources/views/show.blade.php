@extends('theme::layouts.1col')

@section('title', 'View donor')
@section('body-class', 'view donor')

@section('title-block')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">View donor</h1>
    <span class="small" id="heading-label">{{ $donor->authorized_form_of_name ?: '[Untitled]' }}</span>
  </div>
@endsection

@section('content')

  {{-- ===== Basic info ===== --}}
  <section class="section border-bottom" id="basicInfo">
    <h2 class="h6 mb-0 py-2 px-3 rounded-top" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#identity-collapse">Basic info</a>
      @auth
        <a href="{{ route('donor.edit', $donor->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit"><i class="fas fa-pencil-alt"></i></a>
      @endauth
    </h2>
    <div id="identity-collapse">

      @if($donor->authorized_form_of_name)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Authorized form of name</h3>
          <div class="col-9 p-2">{{ $donor->authorized_form_of_name }}</div>
        </div>
      @endif

    </div>
  </section>

  {{-- ===== Contact area ===== --}}
  <section class="section border-bottom" id="contactArea">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#contact-collapse">Contact area</a>
      @auth
        <a href="{{ route('donor.edit', $donor->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit"><i class="fas fa-pencil-alt"></i></a>
      @endauth
    </h2>
    <div id="contact-collapse">

      @if(isset($contacts) && $contacts->isNotEmpty())
        @foreach($contacts as $contactItem)
          <div class="mb-3">
            @if($contactItem->contact_person)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Contact person</h3>
                <div class="col-9 p-2">{{ $contactItem->contact_person }}</div>
              </div>
            @endif
            @if($contactItem->street_address)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Street address</h3>
                <div class="col-9 p-2">{{ $contactItem->street_address }}</div>
              </div>
            @endif
            @if($contactItem->city)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">City</h3>
                <div class="col-9 p-2">{{ $contactItem->city }}</div>
              </div>
            @endif
            @if($contactItem->region)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Region/province</h3>
                <div class="col-9 p-2">{{ $contactItem->region }}</div>
              </div>
            @endif
            @if($contactItem->postal_code)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Postal code</h3>
                <div class="col-9 p-2">{{ $contactItem->postal_code }}</div>
              </div>
            @endif
            @if($contactItem->country_code)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Country</h3>
                <div class="col-9 p-2">{{ $contactItem->country_code }}</div>
              </div>
            @endif
            @if($contactItem->telephone)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Telephone</h3>
                <div class="col-9 p-2">{{ $contactItem->telephone }}</div>
              </div>
            @endif
            @if($contactItem->fax)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Fax</h3>
                <div class="col-9 p-2">{{ $contactItem->fax }}</div>
              </div>
            @endif
            @if($contactItem->email)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Email</h3>
                <div class="col-9 p-2">{{ $contactItem->email }}</div>
              </div>
            @endif
            @if($contactItem->website)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Website</h3>
                <div class="col-9 p-2"><a href="{{ $contactItem->website }}" target="_blank">{{ $contactItem->website }}</a></div>
              </div>
            @endif
            @if($contactItem->note ?? null)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Note</h3>
                <div class="col-9 p-2">{{ $contactItem->note }}</div>
              </div>
            @endif
          </div>
        @endforeach
      @endif

    </div>
  </section>

  {{-- ===== Accession area ===== --}}
  <section class="section" id="accessionArea">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      Accession area
    </h2>
    <div>

      @if(isset($accessions) && $accessions->isNotEmpty())
        @foreach($accessions as $accession)
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Related accession(s)</h3>
            <div class="col-9 p-2">
              <a href="{{ route('accession.show', $accession->slug) }}">{{ $accession->title ?: $accession->identifier ?: '[Untitled]' }}</a>
            </div>
          </div>
        @endforeach
      @endif

    </div>
  </section>

@endsection

@section('after-content')
  @auth
    <ul class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
      <li><a href="{{ route('donor.edit', $donor->slug) }}" class="btn atom-btn-outline-light">Edit</a></li>
      <li><a href="{{ route('donor.confirmDelete', $donor->slug) }}" class="btn atom-btn-outline-danger">Delete</a></li>
      <li><a href="{{ route('donor.create') }}" class="btn atom-btn-outline-light">Add new</a></li>
    </ul>
  @endauth
@endsection
