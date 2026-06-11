{{--
  heratio#1206 - "walk through what no longer exists": admin manage page.
  Link a catalogue record (a lost / destroyed place) to a walkable reconstruction
  space, and remove existing links.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3.0 or later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Reconstructions'))
@section('body-class', 'exhibition-space reconstructions-manage')

@section('content')
  <div class="d-flex flex-wrap align-items-baseline mb-2 gap-2">
    <h1 class="mb-0 flex-grow-1">
      <i class="fas fa-archway me-2"></i>{{ __('Reconstructions') }}
      <small class="text-muted">{{ __('walk through what no longer exists') }}</small>
    </h1>
    <a href="{{ route('exhibition-space.reconstructions') }}" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-eye me-1"></i>{{ __('View public gallery') }}
    </a>
  </div>

  <p class="text-muted small mb-3" style="max-width: 60rem;">
    {{ __('Link a record about a place or building that is lost, destroyed or no longer standing to a walkable exhibition-space twin that serves as its virtual reconstruction. Visitors can then walk through the reconstruction from the public gallery.') }}
    <br>
    <em>{{ __('A reconstruction is a virtual reconstruction for interpretation; it is not a claim about the original\'s exact appearance.') }}</em>
  </p>

  @if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger py-2">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card shadow-sm mb-4">
    <div class="card-header">{{ __('Link a record to a reconstruction') }}</div>
    <div class="card-body">
      <form method="POST" action="{{ route('exhibition-space.reconstructions.store') }}" class="row g-3">
        @csrf
        <div class="col-md-4">
          <label for="information_object_id" class="form-label">{{ __('Record ID (the lost place)') }}</label>
          <input type="number" min="1" name="information_object_id" id="information_object_id"
                 class="form-control" value="{{ old('information_object_id') }}" required>
          <div class="form-text">{{ __('The information-object ID of the record describing the lost place.') }}</div>
        </div>
        <div class="col-md-4">
          <label for="exhibition_space_id" class="form-label">{{ __('Reconstruction space') }}</label>
          <select name="exhibition_space_id" id="exhibition_space_id" class="form-select" required>
            <option value="">{{ __('Choose a walkable space…') }}</option>
            @foreach($spaces as $s)
              <option value="{{ $s->id }}" @selected((string) old('exhibition_space_id') === (string) $s->id)>
                {{ $s->name }}
              </option>
            @endforeach
          </select>
          <div class="form-text">{{ __('Any exhibition-space twin can stand as a reconstruction.') }}</div>
        </div>
        <div class="col-md-4">
          <label for="note" class="form-label">{{ __('Note (optional)') }}</label>
          <input type="text" name="note" id="note" class="form-control"
                 maxlength="5000" value="{{ old('note') }}"
                 placeholder="{{ __('e.g. based on 1890s survey plans and photographs') }}">
          <div class="form-text">{{ __('Sources or interpretive caveats shown in the public gallery.') }}</div>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-link me-1"></i>{{ __('Link reconstruction') }}
          </button>
        </div>
      </form>
    </div>
  </div>

  <h2 class="h5 mb-2">{{ __('Linked reconstructions') }}</h2>
  @if(empty($reconstructions))
    <p class="text-muted">{{ __('No reconstructions have been linked yet.') }}</p>
  @else
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>{{ __('Lost place (record)') }}</th>
            <th>{{ __('Reconstruction space') }}</th>
            <th>{{ __('Note') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($reconstructions as $r)
            <tr>
              <td>
                {{ $r->record_title ?: __('Untitled record') }}
                <span class="text-muted small">#{{ $r->information_object_id }}</span>
              </td>
              <td>{{ $r->space_name ?: __('(missing space)') }}</td>
              <td class="small text-muted">{{ $r->note }}</td>
              <td class="text-end text-nowrap">
                @if($r->space_slug)
                  <a href="{{ route('exhibition-space.walkthrough', $r->space_slug) }}"
                     class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                    <i class="fas fa-walking me-1"></i>{{ __('Walk') }}
                  </a>
                @endif
                <form method="POST" action="{{ route('exhibition-space.reconstructions.delete', $r->id) }}"
                      class="d-inline" onsubmit="return confirm('{{ __('Remove this reconstruction link?') }}');">
                  @csrf
                  <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-unlink me-1"></i>{{ __('Remove') }}
                  </button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection
