@extends('theme::layouts.1col')

@section('title', 'New ILL Request')

@section('content')
<div class="container py-4">
  <div class="d-flex align-items-center mb-4">
    <a href="{{ route('library.ill') }}" class="text-muted me-2">
      <i class="fas fa-arrow-left"></i>
    </a>
    <h1 class="mb-0">New Interlibrary Loan Request</h1>
  </div>

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
      </ul>
    </div>
  @endif

  <form method="post" action="{{ route('library.ill-store') }}">
    @csrf

    <div class="row">
      <div class="col-md-8">

        {{-- Type + title ─────────────────────────────────────────────── --}}
        <div class="card mb-4">
          <div class="card-header">
            <i class="fas fa-book me-1"></i>{{ __('Item Details') }}
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">{{ __('Type') }}</label>
              <select name="type" class="form-select">
                <option value="borrow">{{ __('Borrow (we request from another library)') }}</option>
                <option value="lend">{{ __('Lend (another library requests from us)') }}</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
              <input type="text" name="title" value="{{ old('title') }}" class="form-control" required maxlength="500">
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('Author') }}</label>
                <input type="text" name="author" value="{{ old('author') }}" class="form-control" maxlength="300">
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">{{ __('ISBN') }}</label>
                <input type="text" name="isbn" value="{{ old('isbn') }}" class="form-control" maxlength="20">
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">{{ __('ISSN') }}</label>
                <input type="text" name="issn" value="{{ old('issn') }}" class="form-control" maxlength="20">
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
                <label class="form-label">{{ __('Pages') }}</label>
                <input type="text" name="pages" value="{{ old('pages') }}" class="form-control" maxlength="50">
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">{{ __('Year') }}</label>
                <input type="number" name="publication_year" value="{{ old('publication_year') }}"
                       class="form-control" min="1000" max="{{ date('Y') + 5 }}">
              </div>
            </div>
          </div>
        </div>

        {{-- Partner library ─────────────────────────────────────────── --}}
        <div class="card mb-4">
          <div class="card-header">
            <i class="fas fa-building me-1"></i>{{ __('Partner Library') }}
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-8 mb-3">
                <label class="form-label">{{ __('Library Name') }} <span class="text-danger">*</span></label>
                <input type="text" name="library_name" value="{{ old('library_name') }}"
                       class="form-control" required maxlength="300">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">{{ __('Library Symbol / Code') }}</label>
                <input type="text" name="library_symbol" value="{{ old('library_symbol') }}"
                       class="form-control" maxlength="50" placeholder="e.g. SABINET, NAZ">
              </div>
            </div>
          </div>
        </div>

        {{-- Patron / dates ──────────────────────────────────────────── --}}
        <div class="card mb-4">
          <div class="card-header">
            <i class="fas fa-user me-1"></i>{{ __('Patron & Dates') }}
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">{{ __('Patron ID') }}</label>
                <input type="number" name="patron_id" value="{{ old('patron_id') }}" class="form-control">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">{{ __('Due Date') }}</label>
                <input type="date" name="due_date" value="{{ old('due_date') }}" class="form-control"
                       min="{{ date('Y-m-d', strtotime('+1 day')) }}">
              </div>
            </div>
            <div class="mb-0">
              <label class="form-label">{{ __('Requester Note') }}</label>
              <textarea name="requester_note" rows="3" class="form-control"
                        maxlength="2000" placeholder="{{ __('Additional instructions or justification…') }}">{{ old('requester_note') }}</textarea>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save me-1"></i>{{ __('Create ILL Request') }}
        </button>
        <a href="{{ route('library.ill') }}" class="btn btn-secondary ms-2">
          {{ __('Cancel') }}
        </a>

      </div>

      {{-- ISO 10160 info sidebar ─────────────────────────────────────── --}}
      <div class="col-md-4">
        <div class="card">
          <div class="card-header">
            <i class="fas fa-info-circle me-1"></i>{{ __('ISO 10160 State Machine') }}
          </div>
          <div class="card-body small">
            <p>Borrow (we request):</p>
            <ol class="ps-3">
              <li><strong>Pending</strong> — awaiting transmission</li>
              <li><strong>Requested</strong> — request sent to lender</li>
              <li><strong>Shipped</strong> — item in transit</li>
              <li><strong>Received</strong> — item received</li>
              <li><strong>Returned</strong> — item returned to lender</li>
              <li class="text-muted">Cancelled / Lost / Unfulfilled (terminal)</li>
            </ol>
            <p class="mt-2">Lend (they request from us):</p>
            <ol class="ps-3">
              <li><strong>Pending</strong></li>
              <li><strong>Shipped</strong> — item sent to borrower</li>
              <li><strong>Received</strong> — item returned (terminal)</li>
            </ol>
          </div>
        </div>
      </div>

    </div>
  </form>
</div>
@endsection