<x-library-layout>
  @section('title', 'New ILL Request')

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4">New ILL Request</h2>
    <a href="{{ route('library.ill-requests.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
  </div>

  <form method="POST" action="{{ route('library.ill-requests.store') }}">
    @csrf

    <div class="row g-3">
      {{-- ILL Number + type --}}
      <div class="col-md-3">
        <label class="form-label">ILL Number</label>
        <input name="ill_number" value="{{ old('ill_number', $illNumber) }}" class="form-control" readonly>
      </div>
      <div class="col-md-3">
        <label class="form-label">Type *</label>
        <select name="type" class="form-select" required>
          <option value="borrow" {{ old('type','borrow')=='borrow' ? 'selected' : '' }}>Borrow (request from another library)</option>
          <option value="lend" {{ old('type')=='lend' ? 'selected' : '' }}>Lend (another library requests from us)</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Request Type</label>
        <select name="request_type" class="form-select">
          @foreach(['BORROW','SUPPLY','PHOTOCOPY','LOAN_RENEWAL','STATUS_CHECK'] as $t)
            <option value="{{ $t }}" {{ old('request_type')==$t ? 'selected' : '' }}>{{ $t }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Borrowing Protocol</label>
        <select name="borrowing_protocol" class="form-select">
          @foreach($protocols as $p)
            <option value="{{ $p }}" {{ old('borrowing_protocol', 'AARC')==$p ? 'selected' : '' }}>{{ $p }}</option>
          @endforeach
        </select>
      </div>

      {{-- Partner + vendor --}}
      <div class="col-md-4">
        <label class="form-label">EDI Trading Partner</label>
        <select name="trading_partner_id" class="form-select">
          <option value="">— none —</option>
          @foreach($partners as $tp)
            <option value="{{ $tp->id }}" {{ old('trading_partner_id')==$tp->id ? 'selected' : '' }}>
              {{ $tp->edi_partner_code }} ({{ $tp->edi_type }})
            </option>
          @endforeach
        </select>
        @if($partners->isEmpty())
          <small class="text-muted">No active trading partners. <a href="{{ route('library.trading-partners.create') }}">Add one first.</a></small>
        @endif
      </div>
      <div class="col-md-4">
        <label class="form-label">Responder Library</label>
        <select name="responder_library_id" class="form-select">
          <option value="">— unknown —</option>
          @foreach($vendors as $v)
            <option value="{{ $v->id }}" {{ old('responder_library_id')==$v->id ? 'selected' : '' }}>
              {{ $v->name }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Material Type</label>
        <select name="material_type" class="form-select">
          @foreach(['BOOK','SERIAL_ISSUE','CONFERENCE_PAPER','THESIS','PATENT','REPORT','OTHER'] as $m)
            <option value="{{ $m }}" {{ old('material_type','BOOK')==$m ? 'selected' : '' }}>{{ str_replace('_',' ',$m) }}</option>
          @endforeach
        </select>
      </div>

      {{-- Bibliography --}}
      <div class="col-12"><hr><h6 class="text-muted">Bibliographic Details</h6></div>
      <div class="col-12">
        <label class="form-label">Title *</label>
        <input name="title" value="{{ old('title') }}" class="form-control" required>
        @error('title') <div class="text-danger small">{{ $message }}</div> @enderror
      </div>
      <div class="col-md-4">
        <label class="form-label">Author</label>
        <input name="author" value="{{ old('author') }}" class="form-control">
      </div>
      <div class="col-md-4">
        <label class="form-label">Publisher</label>
        <input name="publisher" value="{{ old('publisher') }}" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">Year</label>
        <input name="publication_year" value="{{ old('publication_year') }}" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">ISBN</label>
        <input name="isbn" value="{{ old('isbn') }}" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">ISSN</label>
        <input name="issn" value="{{ old('issn') }}" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">Volume</label>
        <input name="volume" value="{{ old('volume') }}" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">Issue</label>
        <input name="issue" value="{{ old('issue') }}" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">Pages</label>
        <input name="pages" value="{{ old('pages') }}" class="form-control">
      </div>
      <div class="col-md-4">
        <label class="form-label">Citation (where this ILL is cited)</label>
        <input name="citation" value="{{ old('citation') }}" class="form-control" placeholder="e.g. Thesis Ch.4, Article p.12">
      </div>
      <div class="col-md-4">
        <label class="form-label">OCLC Number</label>
        <input name="oclc_number" value="{{ old('oclc_number') }}" class="form-control">
      </div>

      {{-- Dates --}}
      <div class="col-12"><hr><h6 class="text-muted">Dates &amp; Cost</h6></div>
      <div class="col-md-3">
        <label class="form-label">Request Date</label>
        <input type="date" name="request_date" value="{{ old('request_date', now()->toDateString()) }}" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Needed By</label>
        <input type="date" name="needed_by_date" value="{{ old('needed_by_date') }}" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Due Date</label>
        <input type="date" name="due_date" value="{{ old('due_date') }}" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Max Renewals</label>
        <input type="number" name="max_renewals" value="{{ old('max_renewals', 2) }}" class="form-control" min="0" max="10">
      </div>
      <div class="col-md-3">
        <label class="form-label">Cost Amount</label>
        <input type="number" name="cost_amount" step="0.01" value="{{ old('cost_amount') }}" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Cost Currency</label>
        <input name="cost_currency" value="{{ old('cost_currency', 'ZAR') }}" class="form-control" maxlength="3">
      </div>
      <div class="col-md-3">
        <label class="form-label">Shipping Method</label>
        <input name="shipping_method" value="{{ old('shipping_method') }}" class="form-control">
      </div>

      {{-- Notes --}}
      <div class="col-12"><hr><h6 class="text-muted">Notes</h6></div>
      <div class="col-md-6">
        <label class="form-label">Requester Note</label>
        <textarea name="requester_note" class="form-control" rows="3">{{ old('requester_note') }}</textarea>
      </div>
      <div class="col-md-6">
        <label class="form-label">Staff Note</label>
        <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
      </div>

      <div class="col-12">
        <button type="submit" class="btn btn-primary">Create ILL Request</button>
      </div>
    </div>
  </form>
</x-library-layout>
