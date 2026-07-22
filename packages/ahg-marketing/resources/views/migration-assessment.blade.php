{{--
  marketing::migration-assessment - free AtoM migration assessment lead form.

  @license AGPL-3.0-or-later
--}}
@extends('marketing::layout')

@section('title', 'Book a free AtoM migration assessment')
@section('meta_description', 'Book a free, no-obligation AtoM migration assessment. We review your AtoM instance, map the migration, and show your collection running in Heratio.')
@section('canonical', 'https://heratio.org/migration/assessment')

@section('content')
    <h1>{{ __('Book a free AtoM migration assessment') }}</h1>

    <p class="lede">Tell us about your current AtoM instance and we will review it, map the migration (EAD/CSV import, authority and repository records, digital objects), and show you your collection running in Heratio - with no obligation.</p>

    @if (session('status'))
        <div class="flash-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="errors">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form class="lead" method="POST" action="/migration/assessment">
        @csrf

        {{-- Honeypot: hidden from humans, tempting to bots. Leave blank. --}}
        <div class="hp" aria-hidden="true">
            <label for="website">{{ __('Website (leave this field empty)') }}</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="">
        </div>

        <label for="name">Name <span class="hint">(required)</span></label>
        <input type="text" id="name" name="name" required maxlength="200" value="{{ old('name') }}">

        <label for="email">Email <span class="hint">(required)</span></label>
        <input type="email" id="email" name="email" required maxlength="200" value="{{ old('email') }}">

        <label for="organisation">Organisation <span class="hint">(required)</span></label>
        <input type="text" id="organisation" name="organisation" required maxlength="200" value="{{ old('organisation') }}">

        <label for="current_atom_url">Current AtoM URL <span class="hint">(optional)</span></label>
        <input type="url" id="current_atom_url" name="current_atom_url" maxlength="300" placeholder="https://archives.example.org" value="{{ old('current_atom_url') }}">

        <label for="atom_version">AtoM version <span class="hint">(optional)</span></label>
        <input type="text" id="atom_version" name="atom_version" maxlength="60" placeholder="e.g. 2.7" value="{{ old('atom_version') }}">

        <label for="message">Anything else? <span class="hint">(optional)</span></label>
        <textarea id="message" name="message" maxlength="2000">{{ old('message') }}</textarea>

        <div class="form-actions">
            <button type="submit" class="btn">{{ __('Request my free assessment') }}</button>
        </div>
    </form>
@endsection
