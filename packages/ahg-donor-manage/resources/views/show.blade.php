@extends('theme::layouts.1col')

@section('title', 'View donor')
@section('body-class', 'view donor')

@section('title-block')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">View donor</h1>
    <span class="small" id="heading-label">{{ $donor->authorized_form_of_name ?: '[Untitled]' }}</span>
  </div>
@endsection

@section('before-content')
  @auth
    @if(empty($donor->authorized_form_of_name))
      <div class="alert alert-danger" role="alert">
        <ul class="list-unstyled mb-0">
          <li>Authorized form of name - This is a mandatory field.</li>
        </ul>
      </div>
    @endif
  @endauth
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
          <section class="contact-info mb-3">
            {{-- Contact person with primary badge --}}
            @if($contactItem->contact_person)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">&nbsp;</h3>
                <div class="col-9 p-2">
                  <span class="text-primary">{{ $contactItem->contact_person }}</span>
                  @if($contactItem->primary_contact)
                    <span class="badge bg-secondary ms-1">Primary contact</span>
                  @endif
                </div>
              </div>
            @endif

            {{-- Contact type --}}
            @if($contactItem->contact_type ?? null)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Type</h3>
                <div class="col-9 p-2">{{ $contactItem->contact_type }}</div>
              </div>
            @endif

            {{-- Address group --}}
            @if($contactItem->street_address || ($contactItem->city ?? null) || ($contactItem->region ?? null) || $contactItem->country_code || $contactItem->postal_code)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Address</h3>
                <div class="col-9 p-2">
                  @if($contactItem->street_address)
                    <div class="field row g-0">
                      <h4 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-1 ps-0" style="font-size:.85rem;">Street address</h4>
                      <div class="col-9 p-1">{{ $contactItem->street_address }}</div>
                    </div>
                  @endif
                  @if($contactItem->city ?? null)
                    <div class="field row g-0">
                      <h4 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-1 ps-0" style="font-size:.85rem;">Locality</h4>
                      <div class="col-9 p-1">{{ $contactItem->city }}</div>
                    </div>
                  @endif
                  @if($contactItem->region ?? null)
                    <div class="field row g-0">
                      <h4 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-1 ps-0" style="font-size:.85rem;">Region</h4>
                      <div class="col-9 p-1">{{ $contactItem->region }}</div>
                    </div>
                  @endif
                  @if($contactItem->country_code)
                    <div class="field row g-0">
                      <h4 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-1 ps-0" style="font-size:.85rem;">Country name</h4>
                      <div class="col-9 p-1">{{ $contactItem->country_code }}</div>
                    </div>
                  @endif
                  @if($contactItem->postal_code)
                    <div class="field row g-0">
                      <h4 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-1 ps-0" style="font-size:.85rem;">Postal code</h4>
                      <div class="col-9 p-1">{{ $contactItem->postal_code }}</div>
                    </div>
                  @endif
                </div>
              </div>
            @endif

            {{-- Telephone --}}
            @if($contactItem->telephone)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Telephone</h3>
                <div class="col-9 p-2">{{ $contactItem->telephone }}</div>
              </div>
            @endif

            {{-- Fax --}}
            @if($contactItem->fax)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Fax</h3>
                <div class="col-9 p-2">{{ $contactItem->fax }}</div>
              </div>
            @endif

            {{-- Email --}}
            @if($contactItem->email)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Email</h3>
                <div class="col-9 p-2">{{ $contactItem->email }}</div>
              </div>
            @endif

            {{-- URL/Website --}}
            @if($contactItem->website)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">URL</h3>
                <div class="col-9 p-2"><a href="{{ $contactItem->website }}" target="_blank">{{ $contactItem->website }}</a></div>
              </div>
            @endif

            {{-- Note --}}
            @if($contactItem->note ?? null)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Note</h3>
                <div class="col-9 p-2">{{ $contactItem->note }}</div>
              </div>
            @endif
          </section>
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
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Related accession(s)</h3>
          <div class="col-9 p-2">
            @foreach($accessions as $accession)
              <a href="{{ route('accession.show', $accession->slug) }}">{{ $accession->title ?: $accession->identifier ?: '[Untitled]' }}</a>@if(!$loop->last), @endif
            @endforeach
          </div>
        </div>
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
