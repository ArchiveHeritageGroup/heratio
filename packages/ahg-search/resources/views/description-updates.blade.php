@extends('theme::layouts.1col')

@section('title', 'Description updates')
@section('body-class', 'search description-updates')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-history me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Description updates</h1>
      <span class="small text-muted">Browse recently added or modified descriptions</span>
    </div>
  </div>

  {{-- Filter form --}}
  <form action="{{ route('search.descriptionUpdates') }}" method="get" class="card mb-4">
    <div class="card-body">
      <div class="row g-3">
        {{-- Entity type --}}
        <div class="col-md-3">
          <label for="className" class="form-label">Entity type</label>
          <select name="className" id="className" class="form-select">
            @foreach($entityTypes as $value => $label)
              <option value="{{ $value }}" {{ $className === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        {{-- Date start --}}
        <div class="col-md-2">
          <label for="dateStart" class="form-label">Date start</label>
          <input type="date" name="dateStart" id="dateStart" class="form-control" value="{{ $dateStart }}">
        </div>

        {{-- Date end --}}
        <div class="col-md-2">
          <label for="dateEnd" class="form-label">Date end</label>
          <input type="date" name="dateEnd" id="dateEnd" class="form-control" value="{{ $dateEnd }}">
        </div>

        {{-- Date of --}}
        <div class="col-md-2">
          <label class="form-label">Date of</label>
          <div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="dateOf" id="dateOfCreated" value="created" {{ $dateOf === 'created' ? 'checked' : '' }}>
              <label class="form-check-label" for="dateOfCreated">Created</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="dateOf" id="dateOfUpdated" value="updated" {{ $dateOf === 'updated' ? 'checked' : '' }}>
              <label class="form-check-label" for="dateOfUpdated">Updated</label>
            </div>
          </div>
        </div>

        {{-- Publication status --}}
        <div class="col-md-3">
          <label class="form-label">Publication status</label>
          <div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="publicationStatus" id="pubAll" value="" {{ $publicationStatus === '' ? 'checked' : '' }}>
              <label class="form-check-label" for="pubAll">All</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="publicationStatus" id="pubPublished" value="published" {{ $publicationStatus === 'published' ? 'checked' : '' }}>
              <label class="form-check-label" for="pubPublished">Published</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="publicationStatus" id="pubDraft" value="draft" {{ $publicationStatus === 'draft' ? 'checked' : '' }}>
              <label class="form-check-label" for="pubDraft">Draft</label>
            </div>
          </div>
        </div>

        {{-- User --}}
        <div class="col-md-3">
          <label for="user" class="form-label">User</label>
          <select name="user" id="user" class="form-select">
            <option value="">All users</option>
            @foreach($users as $userId => $displayName)
              <option value="{{ $userId }}" {{ (string) $userName === (string) $userId ? 'selected' : '' }}>{{ $displayName }}</option>
            @endforeach
          </select>
        </div>

        {{-- Buttons --}}
        <div class="col-md-9 d-flex align-items-end gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-search" aria-hidden="true"></i> Search
          </button>
          <a href="{{ route('search.descriptionUpdates') }}" class="btn btn-outline-secondary">
            <i class="fas fa-undo" aria-hidden="true"></i> Reset
          </a>
        </div>
      </div>
    </div>
  </form>

  {{-- Results --}}
  @if($results !== null && $results->count() > 0)
    <div class="mb-2 text-muted small">
      Showing {{ number_format($pager->getNbResults()) }} result(s)
    </div>

    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th>Title / Name</th>
          <th>Entity type</th>
          <th>Date</th>
          @if($results->first() && $results->first()->username)
            <th>User</th>
          @endif
        </tr>
      </thead>
      <tbody>
        @foreach($results as $row)
          <tr>
            <td>
              @if($row->slug)
                <a href="/{{ $row->slug }}">{{ $row->title }}</a>
              @else
                {{ $row->title }}
              @endif
            </td>
            <td>
              <span class="badge bg-secondary">{{ $row->entity_type }}</span>
            </td>
            <td>{{ $row->date ? \Carbon\Carbon::parse($row->date)->format('Y-m-d H:i') : '' }}</td>
            @if($results->first() && $results->first()->username)
              <td>{{ $row->username }}</td>
            @endif
          </tr>
        @endforeach
      </tbody>
    </table>

    @include('ahg-core::components.pager', ['pager' => $pager])
  @elseif($results !== null)
    <div class="alert alert-info">
      <i class="fas fa-info-circle" aria-hidden="true"></i>
      No description updates found matching the current filters.
    </div>
  @endif
@endsection
