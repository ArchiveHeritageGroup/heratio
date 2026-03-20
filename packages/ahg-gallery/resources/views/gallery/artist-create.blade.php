@extends('theme::layouts.1col')

@section('title', 'Add gallery artist')
@section('body-class', 'create gallery-artist')

@section('content')
  <h1>Add gallery artist</h1>

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('gallery.browse') }}">Gallery</a></li>
      <li class="breadcrumb-item"><a href="{{ route('gallery.artists') }}">Artists</a></li>
      <li class="breadcrumb-item active" aria-current="page">Add new</li>
    </ol>
  </nav>

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('gallery.artists.store') }}">
    @csrf

    {{-- ===== Identity ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Identity</legend>

      <div class="mb-3">
        <label for="display_name" class="form-label">Display name <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('display_name') is-invalid @enderror" id="display_name" name="display_name"
               value="{{ old('display_name') }}" required>
        @error('display_name')
          <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">The name of the artist as it should appear in displays and labels (e.g. "Claude Monet" or "Banksy").</div>
      </div>

      <div class="mb-3">
        <label for="sort_name" class="form-label">Sort name</label>
        <input type="text" class="form-control" id="sort_name" name="sort_name"
               value="{{ old('sort_name') }}">
        <div class="form-text">The name used for alphabetical sorting, typically in inverted form (e.g. "Monet, Claude"). If left blank, the display name will be used.</div>
      </div>

      <div class="mb-3">
        <label for="actor_id" class="form-label">Linked authority record (ID)</label>
        <input type="number" class="form-control" id="actor_id" name="actor_id"
               value="{{ old('actor_id') }}">
        <div class="form-text">Optional numeric ID of an existing authority record to link this artist to the broader archival authority system.</div>
      </div>
    </fieldset>

    {{-- ===== Biographical ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Biographical information</legend>

      <div class="row">
        <div class="col-md-6">
          <div class="mb-3">
            <label for="birth_date" class="form-label">Birth date</label>
            <input type="text" class="form-control" id="birth_date" name="birth_date"
                   value="{{ old('birth_date') }}">
            <div class="form-text">The artist's date of birth. Use ISO format (YYYY-MM-DD) or free text (e.g. "1840", "ca. 1900").</div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="birth_place" class="form-label">Birth place</label>
            <input type="text" class="form-control" id="birth_place" name="birth_place"
                   value="{{ old('birth_place') }}">
            <div class="form-text">The place where the artist was born (e.g. "Paris, France").</div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-6">
          <div class="mb-3">
            <label for="death_date" class="form-label">Death date</label>
            <input type="text" class="form-control" id="death_date" name="death_date"
                   value="{{ old('death_date') }}">
            <div class="form-text">The artist's date of death. Leave blank if the artist is still living.</div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="death_place" class="form-label">Death place</label>
            <input type="text" class="form-control" id="death_place" name="death_place"
                   value="{{ old('death_place') }}">
            <div class="form-text">The place where the artist died.</div>
          </div>
        </div>
      </div>

      <div class="mb-3">
        <label for="nationality" class="form-label">Nationality</label>
        <input type="text" class="form-control" id="nationality" name="nationality"
               value="{{ old('nationality') }}">
        <div class="form-text">The nationality or cultural affiliation of the artist (e.g. "French", "South African", "Japanese-American").</div>
      </div>

      <div class="mb-3">
        <label for="active_period" class="form-label">Active period</label>
        <input type="text" class="form-control" id="active_period" name="active_period"
               value="{{ old('active_period') }}">
        <div class="form-text">The period during which the artist was professionally active (e.g. "1960-1995", "1980s-present").</div>
      </div>
    </fieldset>

    {{-- ===== Artistic Practice ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Artistic practice</legend>

      <div class="mb-3">
        <label for="artist_type" class="form-label">Artist type</label>
        <select class="form-select" id="artist_type" name="artist_type">
          <option value="">-- Select --</option>
          @foreach($artistTypes as $at)
            <option value="{{ $at }}" @selected(old('artist_type') == $at)>{{ $at }}</option>
          @endforeach
        </select>
        <div class="form-text">The primary classification of this artist based on their practice.</div>
      </div>

      <div class="mb-3">
        <label for="medium_specialty" class="form-label">Medium / Specialty</label>
        <input type="text" class="form-control" id="medium_specialty" name="medium_specialty"
               value="{{ old('medium_specialty') }}">
        <div class="form-text">The primary medium or artistic specialty (e.g. "Oil painting", "Bronze sculpture", "Digital photography").</div>
      </div>

      <div class="mb-3">
        <label for="movement_style" class="form-label">Movement / Style</label>
        <input type="text" class="form-control" id="movement_style" name="movement_style"
               value="{{ old('movement_style') }}">
        <div class="form-text">The art movement or style associated with the artist (e.g. "Impressionism", "Abstract Expressionism", "Contemporary").</div>
      </div>

      <div class="mb-3">
        <label for="represented" class="form-label">Represented by</label>
        <input type="text" class="form-control" id="represented" name="represented"
               value="{{ old('represented') }}">
        <div class="form-text">The gallery, agent, or estate that represents the artist commercially.</div>
      </div>
    </fieldset>

    {{-- ===== Biography / Statement / CV ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Biography / Statement / CV</legend>

      <div class="mb-3">
        <label for="biography" class="form-label">Biography</label>
        <textarea class="form-control" id="biography" name="biography" rows="5">{{ old('biography') }}</textarea>
        <div class="form-text">A narrative biography of the artist including education, career highlights, awards, and significant exhibitions.</div>
      </div>

      <div class="mb-3">
        <label for="artist_statement" class="form-label">Artist statement</label>
        <textarea class="form-control" id="artist_statement" name="artist_statement" rows="4">{{ old('artist_statement') }}</textarea>
        <div class="form-text">The artist's own statement about their work, practice, or creative philosophy.</div>
      </div>

      <div class="mb-3">
        <label for="cv" class="form-label">Curriculum Vitae</label>
        <textarea class="form-control" id="cv" name="cv" rows="5">{{ old('cv') }}</textarea>
        <div class="form-text">The artist's CV including education, exhibitions (solo and group), collections, publications, awards, and residencies.</div>
      </div>
    </fieldset>

    {{-- ===== Contact Information ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Contact information</legend>

      <div class="row">
        <div class="col-md-6">
          <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                   value="{{ old('email') }}">
            @error('email')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">The artist's contact email address.</div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="phone" class="form-label">Phone</label>
            <input type="text" class="form-control" id="phone" name="phone"
                   value="{{ old('phone') }}">
            <div class="form-text">The artist's contact phone number.</div>
          </div>
        </div>
      </div>

      <div class="mb-3">
        <label for="website" class="form-label">Website</label>
        <input type="text" class="form-control" id="website" name="website"
               value="{{ old('website') }}">
        <div class="form-text">The artist's personal website URL (e.g. "https://www.artistname.com").</div>
      </div>

      <div class="mb-3">
        <label for="studio_address" class="form-label">Studio address</label>
        <input type="text" class="form-control" id="studio_address" name="studio_address"
               value="{{ old('studio_address') }}">
        <div class="form-text">The physical address of the artist's studio or workspace.</div>
      </div>
    </fieldset>

    {{-- ===== Social Media ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Social media</legend>

      <div class="row">
        <div class="col-md-4">
          <div class="mb-3">
            <label for="instagram" class="form-label">Instagram</label>
            <input type="text" class="form-control" id="instagram" name="instagram"
                   value="{{ old('instagram') }}" placeholder="@handle">
            <div class="form-text">The artist's Instagram handle (e.g. "@artistname").</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="mb-3">
            <label for="twitter" class="form-label">Twitter / X</label>
            <input type="text" class="form-control" id="twitter" name="twitter"
                   value="{{ old('twitter') }}" placeholder="@handle">
            <div class="form-text">The artist's Twitter/X handle.</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="mb-3">
            <label for="facebook" class="form-label">Facebook</label>
            <input type="text" class="form-control" id="facebook" name="facebook"
                   value="{{ old('facebook') }}">
            <div class="form-text">The artist's Facebook page URL.</div>
          </div>
        </div>
      </div>
    </fieldset>

    {{-- ===== Notes / Status ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Notes / Status</legend>

      <div class="mb-3">
        <label for="notes" class="form-label">Notes</label>
        <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
        <div class="form-text">Internal notes about the artist for staff use. These notes are not displayed publicly.</div>
      </div>

      <div class="mb-3 form-check">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
               @checked(old('is_active', 1))>
        <label class="form-check-label" for="is_active">Active</label>
        <div class="form-text">Uncheck to mark this artist as inactive. Inactive artists will not appear in public browse listings.</div>
      </div>
    </fieldset>

    {{-- ===== Form actions ===== --}}
    <section class="actions mb-3">
      <ul class="actions mb-1 nav gap-2">
        <li><a href="{{ route('gallery.artists') }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Create"></li>
      </ul>
    </section>
  </form>
@endsection
