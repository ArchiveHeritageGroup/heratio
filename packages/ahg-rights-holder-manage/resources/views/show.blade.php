@extends('theme::layouts.1col')

@section('title', 'View rights holder')
@section('body-class', 'view rightsholder')

@section('title-block')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">View rights holder</h1>
    <span class="small" id="heading-label">{{ $rightsHolder->authorized_form_of_name ?: '[Untitled]' }}</span>
  </div>
@endsection

@section('before-content')
  @auth
    @if(empty($rightsHolder->authorized_form_of_name))
      <div class="alert alert-danger" role="alert">
        <ul class="list-unstyled mb-0">
          <li>Authorized form of name - This is a mandatory element.</li>
        </ul>
      </div>
    @endif
  @endauth

  @if(!empty($translations))
    @include('ahg-core::_translation-links')
  @endif
@endsection

@section('content')

  {{-- ===== Identity area ===== --}}
  <section class="section border-bottom" id="identityArea">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">@auth<a href="{{ route('rightsholder.edit', $rightsHolder->slug) }}#identity-collapse" class="text-primary text-decoration-none">Identity area</a>@else Identity area @endauth</div></h2>
    <div id="identity-collapse">

      @if($rightsHolder->authorized_form_of_name)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Authorized form of name</h3>
          <div class="col-9 p-2">{{ $rightsHolder->authorized_form_of_name }}</div>
        </div>
      @endif

    </div>
  </section>

  {{-- ===== Contact area ===== --}}
  <section class="section border-bottom" id="contactArea">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">@auth<a href="{{ route('rightsholder.edit', $rightsHolder->slug) }}#contact-collapse" class="text-primary text-decoration-none">Contact area</a>@else Contact area @endauth</div></h2>
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
                      <h4 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-1 ps-0" style="font-size:.85rem;">City</h4>
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
                      <h4 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-1 ps-0" style="font-size:.85rem;">Country</h4>
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

  {{-- ===== Rights area (PREMIS rights linked to this rights holder) ===== --}}
  @if(isset($rights) && $rights->isNotEmpty())
    <section class="section border-bottom" id="rightsArea">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Rights area</div></h2>
      <div>
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-sm mb-0">
            <thead>
              <tr>
                <th>Basis</th>
                <th>Start date</th>
                <th>End date</th>
                <th>Rights note</th>
                <th>Copyright status</th>
                <th>Copyright jurisdiction</th>
                <th>Copyright note</th>
                <th>License terms</th>
                <th>License note</th>
                <th>Statute jurisdiction</th>
                <th>Statute note</th>
              </tr>
            </thead>
            <tbody>
              @foreach($rights as $right)
                <tr>
                  <td>{{ $basisNames[$right->basis_id] ?? '' }}</td>
                  <td>{{ $right->start_date }}</td>
                  <td>{{ $right->end_date }}</td>
                  <td>{{ $right->rights_note }}</td>
                  <td>{{ $right->copyright_status_id ? ($basisNames[$right->copyright_status_id] ?? $right->copyright_status_id) : '' }}</td>
                  <td>{{ $right->copyright_jurisdiction }}</td>
                  <td>{{ $right->copyright_note }}</td>
                  <td>{{ $right->license_terms }}</td>
                  <td>{{ $right->license_note }}</td>
                  <td>{{ $right->statute_jurisdiction }}</td>
                  <td>{{ $right->statute_note }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </section>
  @endif

  {{-- ===== Extended rights area ===== --}}
  @if(isset($extendedRights) && $extendedRights->isNotEmpty())
    <section class="section border-bottom" id="extendedRightsArea">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Extended rights</div></h2>
      <div>
        @foreach($extendedRights as $er)
          <div class="border-bottom p-3">
            {{-- Linked information object --}}
            @if($er->object_title || $er->object_slug)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Information object</h3>
                <div class="col-9 p-2">
                  @if($er->object_slug)
                    <a href="{{ route('informationobject.show', $er->object_slug) }}">{{ $er->object_title ?: '[Untitled]' }}</a>
                  @else
                    {{ $er->object_title ?: '[Untitled]' }}
                  @endif
                </div>
              </div>
            @endif

            {{-- Rights statement --}}
            @if($er->rights_statement_name || $er->rights_statement_code)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Rights statement</h3>
                <div class="col-9 p-2">
                  @if($er->rights_statement_uri)
                    <a href="{{ $er->rights_statement_uri }}" target="_blank">{{ $er->rights_statement_name ?: $er->rights_statement_code }}</a>
                  @else
                    {{ $er->rights_statement_name ?: $er->rights_statement_code }}
                  @endif
                  @if($er->rights_statement_code)
                    <span class="badge bg-info ms-1">{{ $er->rights_statement_code }}</span>
                  @endif
                </div>
              </div>
            @endif

            {{-- Creative Commons license --}}
            @if($er->cc_license_code || $er->cc_license_uri)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Creative Commons license</h3>
                <div class="col-9 p-2">
                  @if($er->cc_license_uri)
                    <a href="{{ $er->cc_license_uri }}" target="_blank">{{ $er->cc_license_code }}</a>
                  @else
                    {{ $er->cc_license_code }}
                  @endif
                  <span class="badge bg-success ms-1">CC</span>
                </div>
              </div>
            @endif

            {{-- Rights holder name --}}
            @if($er->rights_holder)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Rights holder</h3>
                <div class="col-9 p-2">
                  {{ $er->rights_holder }}
                  @if($er->rights_holder_uri)
                    <a href="{{ $er->rights_holder_uri }}" target="_blank" class="ms-1"><i class="fas fa-external-link-alt fa-xs"></i></a>
                  @endif
                </div>
              </div>
            @endif

            {{-- Rights date / Expiry date --}}
            @if($er->rights_date || $er->expiry_date)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Dates</h3>
                <div class="col-9 p-2">
                  @if($er->rights_date)
                    <span>From: {{ $er->rights_date }}</span>
                  @endif
                  @if($er->expiry_date)
                    <span class="ms-2">To: {{ $er->expiry_date }}</span>
                  @endif
                </div>
              </div>
            @endif

            {{-- Primary flag --}}
            @if($er->is_primary)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Primary</h3>
                <div class="col-9 p-2"><span class="badge bg-primary">Primary rights record</span></div>
              </div>
            @endif

            {{-- Rights note --}}
            @if($er->rights_note)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Rights note</h3>
                <div class="col-9 p-2">{{ $er->rights_note }}</div>
              </div>
            @endif

            {{-- Usage conditions --}}
            @if($er->usage_conditions)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Usage conditions</h3>
                <div class="col-9 p-2">{{ $er->usage_conditions }}</div>
              </div>
            @endif

            {{-- Copyright notice --}}
            @if($er->copyright_notice)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Copyright notice</h3>
                <div class="col-9 p-2">{{ $er->copyright_notice }}</div>
              </div>
            @endif

            {{-- TK Labels --}}
            @if(isset($extendedRightsTkLabels[$er->id]) && $extendedRightsTkLabels[$er->id]->isNotEmpty())
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">TK Labels</h3>
                <div class="col-9 p-2">
                  @foreach($extendedRightsTkLabels[$er->id] as $tkLabel)
                    <span class="badge me-1 mb-1" style="background-color:{{ $tkLabel->color ?: '#6c757d' }};">
                      @if($tkLabel->uri)
                        <a href="{{ $tkLabel->uri }}" target="_blank" class="text-white text-decoration-none">{{ $tkLabel->code }}</a>
                      @else
                        {{ $tkLabel->code }}
                      @endif
                    </span>
                  @endforeach
                </div>
              </div>
            @endif

            {{-- Created / Updated timestamps --}}
            @if($er->created_at || $er->updated_at)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Timestamps</h3>
                <div class="col-9 p-2">
                  @if($er->created_at)
                    <small class="text-muted">Created: {{ $er->created_at }}</small>
                  @endif
                  @if($er->updated_at)
                    <small class="text-muted ms-2">Updated: {{ $er->updated_at }}</small>
                  @endif
                </div>
              </div>
            @endif
          </div>
        @endforeach
      </div>
    </section>
  @endif

@endsection

@section('after-content')
  @auth
    @php $isAdmin = auth()->user()->is_admin; @endphp
    <ul class="actions mb-3 nav gap-2">
      {{-- Edit: any authenticated user --}}
      <li><a href="{{ route('rightsholder.edit', $rightsHolder->slug) }}" class="btn atom-btn-outline-light">Edit</a></li>
      {{-- Delete: admin only --}}
      @if($isAdmin)
      <li><a href="{{ route('rightsholder.confirmDelete', $rightsHolder->slug) }}" class="btn atom-btn-outline-danger">Delete</a></li>
      @endif
      {{-- Add new: any authenticated user --}}
      <li><a href="{{ route('rightsholder.create') }}" class="btn atom-btn-outline-light">Add new</a></li>
    </ul>
  @endauth
@endsection
