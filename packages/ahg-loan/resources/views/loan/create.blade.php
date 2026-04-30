@extends('theme::layouts.1col')

@section('title', 'Create Loan')
@section('body-class', 'create loan')

@section('content')

  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0"><i class="fas fa-handshake me-2"></i>{{ __('Create Loan') }}</h1>
    <span class="small text-muted">{{ __('Create a new loan agreement') }}</span>
  </div>

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @php
    $loanObjectId = old('object_id', request('object_id', ''));
    $loanObjectTitle = null;
    if ($loanObjectId) {
        $loanObjectTitle = \Illuminate\Support\Facades\DB::table('information_object_i18n')
            ->where('id', $loanObjectId)->where('culture', app()->getLocale())->value('title');
    }
  @endphp
  @if($loanObjectTitle)
    <div class="alert alert-info d-flex align-items-center mb-3">
      <i class="fas fa-info-circle me-2"></i>
      Creating loan for: <strong class="ms-1">{{ $loanObjectTitle }}</strong>
      <a href="{{ url('/' . (\Illuminate\Support\Facades\DB::table('slug')->where('object_id', $loanObjectId)->value('slug') ?? '')) }}" class="ms-auto btn btn-sm btn-outline-info">
        <i class="fas fa-arrow-left me-1"></i>{{ __('Back to record') }}
      </a>
    </div>
  @endif

  <form method="POST" action="{{ route('loan.store') }}" id="loanForm">
    @csrf
    <input type="hidden" name="object_id" value="{{ $loanObjectId }}">

    <div class="accordion mb-3" id="loanAccordion">

      {{-- General Information --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="general-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#general-collapse" aria-expanded="true">
            <i class="fas fa-info-circle me-2"></i>{{ __('General Information') }}
          </button>
        </h2>
        <div id="general-collapse" class="accordion-collapse collapse show" aria-labelledby="general-heading">
          <div class="accordion-body">
            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="loan_type" class="form-label">
                  Loan Type <span class="text-danger">*</span>
                 <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                <select name="loan_type" id="loan_type" class="form-select @error('loan_type') is-invalid @enderror" required>
                  <option value="out" {{ old('loan_type', request('type', 'out')) === 'out' ? 'selected' : '' }}>{{ __('Outgoing (lending)') }}</option>
                  <option value="in" {{ old('loan_type', request('type', 'out')) === 'in' ? 'selected' : '' }}>{{ __('Incoming (borrowing)') }}</option>
                </select>
                @error('loan_type')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-4 mb-3">
                <label for="sector" class="form-label">
                  Sector <span class="text-danger">*</span>
                 <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                <select name="sector" id="sector" class="form-select @error('sector') is-invalid @enderror" required>
                  @php $sectorVal = old('sector', request('sector', '')); @endphp
                  <option value="">-- Select sector --</option>
                  <option value="museum" {{ $sectorVal === 'museum' ? 'selected' : '' }}>{{ __('Museum') }}</option>
                  <option value="archive" {{ $sectorVal === 'archive' ? 'selected' : '' }}>{{ __('Archive') }}</option>
                  <option value="library" {{ $sectorVal === 'library' ? 'selected' : '' }}>{{ __('Library') }}</option>
                  <option value="gallery" {{ $sectorVal === 'gallery' ? 'selected' : '' }}>{{ __('Gallery') }}</option>
                </select>
                @error('sector')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-4 mb-3">
                <label for="purpose" class="form-label">Purpose <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <select name="purpose" id="purpose" class="form-select @error('purpose') is-invalid @enderror">
                  <option value="exhibition" {{ old('purpose', 'exhibition') === 'exhibition' ? 'selected' : '' }}>{{ __('Exhibition') }}</option>
                  <option value="research" {{ old('purpose') === 'research' ? 'selected' : '' }}>{{ __('Research') }}</option>
                  <option value="conservation" {{ old('purpose') === 'conservation' ? 'selected' : '' }}>{{ __('Conservation') }}</option>
                  <option value="photography" {{ old('purpose') === 'photography' ? 'selected' : '' }}>{{ __('Photography') }}</option>
                  <option value="education" {{ old('purpose') === 'education' ? 'selected' : '' }}>{{ __('Education') }}</option>
                  <option value="display" {{ old('purpose') === 'display' ? 'selected' : '' }}>{{ __('Display') }}</option>
                  <option value="other" {{ old('purpose') === 'other' ? 'selected' : '' }}>{{ __('Other') }}</option>
                </select>
                @error('purpose')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>

            <div class="mb-3">
              <label for="title" class="form-label">Title <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                     value="{{ old('title', $prefill['title'] ?? '') }}" placeholder="{{ __('Descriptive title for this loan') }}">
              @error('title')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="description" class="form-label">Description <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror"
                        rows="3" placeholder="{{ __('Description of the loan...') }}">{{ old('description', $prefill['description'] ?? '') }}</textarea>
              @error('description')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="notes" class="form-label">Notes <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <textarea name="notes" id="notes" class="form-control @error('notes') is-invalid @enderror"
                        rows="2" placeholder="{{ __('Internal notes...') }}">{{ old('notes') }}</textarea>
              @error('notes')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
        </div>
      </div>

      {{-- Partner Information --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="partner-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#partner-collapse" aria-expanded="true">
            <i class="fas fa-building me-2"></i>{{ __('Partner Information') }}
          </button>
        </h2>
        <div id="partner-collapse" class="accordion-collapse collapse show" aria-labelledby="partner-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="partner_institution" class="form-label">
                Partner Institution <span class="text-danger">*</span>
               <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <input type="text" name="partner_institution" id="partner_institution"
                     class="form-control @error('partner_institution') is-invalid @enderror"
                     value="{{ old('partner_institution') }}" required>
              @error('partner_institution')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="partner_contact_name" class="form-label">Contact Name <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" name="partner_contact_name" id="partner_contact_name"
                       class="form-control @error('partner_contact_name') is-invalid @enderror"
                       value="{{ old('partner_contact_name') }}">
                @error('partner_contact_name')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-4 mb-3">
                <label for="partner_contact_email" class="form-label">Contact Email <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="email" name="partner_contact_email" id="partner_contact_email"
                       class="form-control @error('partner_contact_email') is-invalid @enderror"
                       value="{{ old('partner_contact_email') }}">
                @error('partner_contact_email')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-4 mb-3">
                <label for="partner_contact_phone" class="form-label">Contact Phone <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" name="partner_contact_phone" id="partner_contact_phone"
                       class="form-control @error('partner_contact_phone') is-invalid @enderror"
                       value="{{ old('partner_contact_phone') }}">
                @error('partner_contact_phone')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>

            <div class="mb-3">
              <label for="partner_address" class="form-label">Address <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <textarea name="partner_address" id="partner_address"
                        class="form-control @error('partner_address') is-invalid @enderror"
                        rows="2">{{ old('partner_address') }}</textarea>
              @error('partner_address')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
        </div>
      </div>

      {{-- Dates --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="dates-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#dates-collapse">
            <i class="fas fa-calendar me-2"></i>{{ __('Dates') }}
          </button>
        </h2>
        <div id="dates-collapse" class="accordion-collapse collapse show" aria-labelledby="dates-heading">
          <div class="accordion-body">
            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="request_date" class="form-label">Request Date <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="date" name="request_date" id="request_date"
                       class="form-control @error('request_date') is-invalid @enderror"
                       value="{{ old('request_date', date('Y-m-d')) }}">
                @error('request_date')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-4 mb-3">
                <label for="start_date" class="form-label">Start Date <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="date" name="start_date" id="start_date"
                       class="form-control @error('start_date') is-invalid @enderror"
                       value="{{ old('start_date') }}">
                @error('start_date')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-4 mb-3">
                <label for="end_date" class="form-label">End Date <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="date" name="end_date" id="end_date"
                       class="form-control @error('end_date') is-invalid @enderror"
                       value="{{ old('end_date') }}">
                @error('end_date')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Insurance & Fees --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="insurance-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#insurance-collapse">
            <i class="fas fa-shield-alt me-2"></i>{{ __('Insurance &amp; Fees') }}
          </button>
        </h2>
        <div id="insurance-collapse" class="accordion-collapse collapse show" aria-labelledby="insurance-heading">
          <div class="accordion-body">
            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="insurance_type" class="form-label">Insurance Type <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <select name="insurance_type" id="insurance_type" class="form-select @error('insurance_type') is-invalid @enderror">
                  <option value="borrower" {{ old('insurance_type', 'borrower') === 'borrower' ? 'selected' : '' }}>{{ __('Borrower') }}</option>
                  <option value="lender" {{ old('insurance_type') === 'lender' ? 'selected' : '' }}>{{ __('Lender') }}</option>
                  <option value="shared" {{ old('insurance_type') === 'shared' ? 'selected' : '' }}>{{ __('Shared') }}</option>
                  <option value="waived" {{ old('insurance_type') === 'waived' ? 'selected' : '' }}>{{ __('Waived') }}</option>
                  <option value="government_indemnity" {{ old('insurance_type') === 'government_indemnity' ? 'selected' : '' }}>{{ __('Government Indemnity') }}</option>
                </select>
                @error('insurance_type')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-4 mb-3">
                <label for="insurance_value" class="form-label">Insurance Value <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <div class="input-group">
                  <select name="insurance_currency" class="form-select" style="max-width: 80px;">
                    <option value="ZAR" {{ old('insurance_currency', 'ZAR') === 'ZAR' ? 'selected' : '' }}>{{ __('ZAR') }}</option>
                    <option value="USD" {{ old('insurance_currency') === 'USD' ? 'selected' : '' }}>{{ __('USD') }}</option>
                    <option value="EUR" {{ old('insurance_currency') === 'EUR' ? 'selected' : '' }}>{{ __('EUR') }}</option>
                    <option value="GBP" {{ old('insurance_currency') === 'GBP' ? 'selected' : '' }}>{{ __('GBP') }}</option>
                  </select>
                  <input type="number" name="insurance_value" id="insurance_value"
                         class="form-control @error('insurance_value') is-invalid @enderror"
                         value="{{ old('insurance_value') }}" step="0.01" min="0">
                </div>
                @error('insurance_value')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-4 mb-3">
                <label for="insurance_provider" class="form-label">Insurance Provider <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" name="insurance_provider" id="insurance_provider"
                       class="form-control @error('insurance_provider') is-invalid @enderror"
                       value="{{ old('insurance_provider') }}">
                @error('insurance_provider')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="insurance_policy_number" class="form-label">Policy Number <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" name="insurance_policy_number" id="insurance_policy_number"
                       class="form-control @error('insurance_policy_number') is-invalid @enderror"
                       value="{{ old('insurance_policy_number') }}">
                @error('insurance_policy_number')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-4 mb-3">
                <label for="loan_fee" class="form-label">Loan Fee <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <div class="input-group">
                  <select name="loan_fee_currency" class="form-select" style="max-width: 80px;">
                    <option value="ZAR" {{ old('loan_fee_currency', 'ZAR') === 'ZAR' ? 'selected' : '' }}>{{ __('ZAR') }}</option>
                    <option value="USD" {{ old('loan_fee_currency') === 'USD' ? 'selected' : '' }}>{{ __('USD') }}</option>
                    <option value="EUR" {{ old('loan_fee_currency') === 'EUR' ? 'selected' : '' }}>{{ __('EUR') }}</option>
                    <option value="GBP" {{ old('loan_fee_currency') === 'GBP' ? 'selected' : '' }}>{{ __('GBP') }}</option>
                  </select>
                  <input type="number" name="loan_fee" id="loan_fee"
                         class="form-control @error('loan_fee') is-invalid @enderror"
                         value="{{ old('loan_fee') }}" step="0.01" min="0">
                </div>
                @error('loan_fee')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-4 mb-3">
                <label for="repository_id" class="form-label">Repository ID <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="number" name="repository_id" id="repository_id"
                       class="form-control @error('repository_id') is-invalid @enderror"
                       value="{{ old('repository_id') }}">
                @error('repository_id')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn atom-btn-outline-success">
        <i class="fas fa-save me-1"></i>{{ __('Create Loan') }}
      </button>
      <a href="{{ route('loan.index') }}" class="btn atom-btn-white">Cancel</a>
    </div>
  </form>

@endsection
