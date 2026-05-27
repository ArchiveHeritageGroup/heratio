@extends('theme::layouts.1col')

@section('title', 'Request Interlibrary Loan')

@section('content')
<div class="container py-4">
  <div class="d-flex align-items-center mb-4">
    <a href="{{ route('library.opac') }}" class="text-muted me-2">
      <i class="fas fa-arrow-left"></i>
    </a>
    <h1 class="mb-0">Request an Interlibrary Loan</h1>
  </div>

  @if(session('ill_success'))
    <div class="alert alert-success">{{ session('ill_success') }}</div>
    <a href="{{ route('library.opac') }}" class="btn btn-secondary">
      <i class="fas fa-home me-1"></i>{{ __('Return to OPAC') }}
    </a>
  @else

  <div class="alert alert-info mb-4">
    <i class="fas fa-info-circle me-1"></i>
    Use this form to request books or articles that are not available in our collection.
    Our staff will contact you when the item arrives. A due date will be set by the lending library.
    Standard loan periods are <strong>28 days</strong>; some items may have shorter periods.
  </div>

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
      </ul>
    </div>
  @endif

  <form method="post" action="{{ route('library.opac-ill-store') }}">
    @csrf

    {{-- Item details ─────────────────────────────────────────────────── --}}
    <div class="card mb-4">
      <div class="card-header">
        <i class="fas fa-book me-1"></i>{{ __('Item Details') }}
      </div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
          <input type="text" name="title" value="{{ old('title') }}"
                 class="form-control" required maxlength="500"
                 placeholder="{{ __('Full title of the book or article') }}">
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">{{ __('Author / Editor') }}</label>
            <input type="text" name="author" value="{{ old('author') }}"
                   class="form-control" maxlength="300"
                   placeholder="{{ __('Author surname, first name') }}">
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">{{ __('ISBN') }}</label>
            <input type="text" name="isbn" value="{{ old('isbn') }}"
                   class="form-control" maxlength="20" placeholder="978-0-">
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">{{ __('ISSN') }}</label>
            <input type="text" name="issn" value="{{ old('issn') }}"
                   class="form-control" maxlength="20" placeholder="1234-5678">
          </div>
        </div>
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">{{ __('Volume') }}</label>
            <input type="text" name="volume" value="{{ old('volume') }}" class="form-control" maxlength="50">
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">{{ __('Issue') }}</label>
            <input type="text" name="issue" value="{{ old('issue') }}" class="form-control" maxlength="20">
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">{{ __('Pages / Pages needed') }}</label>
            <input type="text" name="pages" value="{{ old('pages') }}" class="form-control" maxlength="50" placeholder="e.g. 1-15 or 150">
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">{{ __('Publication Year') }}</label>
            <input type="number" name="publication_year" value="{{ old('publication_year') }}"
                   class="form-control" min="1000" max="{{ date('Y') + 5 }}"
                   placeholder="{{ date('Y') }}">
          </div>
        </div>
      </div>
    </div>

    {{-- Note ────────────────────────────────────────────────────────── --}}
    <div class="card mb-4">
      <div class="card-header">
        <i class="fas fa-comment me-1"></i>{{ __('Additional Note') }}
      </div>
      <div class="card-body">
        <textarea name="requester_note" rows="3" class="form-control"
                  maxlength="1000"
                  placeholder="{{ __('Any special requirements, urgency, or justification for the request…') }}">{{ old('requester_note') }}</textarea>
        <div class="form-text">
          {{ __('You will receive a notification when the item arrives.') }}
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">
      <i class="fas fa-paper-plane me-1"></i>{{ __('Submit ILL Request') }}
    </button>
    <a href="{{ route('library.opac') }}" class="btn btn-secondary ms-2">
      {{ __('Cancel') }}
    </a>

  @endif
</div>
@endsection