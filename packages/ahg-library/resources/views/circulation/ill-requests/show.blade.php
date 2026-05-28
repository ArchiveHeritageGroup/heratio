<x-library-layout>
  @section('title', 'ILL Request: ' . $ill->ill_number)

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4">ILL: {{ $ill->ill_number }}</h2>
    <div>
      <a href="{{ route('library.ill-requests.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
      <form method="POST" action="{{ route('library.ill-requests.destroy', $ill->id) }}" class="d-inline"
        onsubmit="return confirm('Delete this ILL request?');">
        @csrf @method('DELETE')
        <button class="btn btn-outline-danger btn-sm">Delete</button>
      </form>
    </div>
  </div>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  <div class="row g-3">
    {{-- Status + transitions --}}
    <div class="col-md-4">
      <div class="card border-primary">
        <div class="card-header">Status</div>
        <div class="card-body text-center">
          <span class="badge bg-{{ $ill->status === 'overdue' ? 'danger' : ($ill->status === 'received' ? 'success' : 'secondary') }}"
            style="font-size:1.1rem; padding: 0.5rem 1rem;">
            {{ ucfirst($ill->status) }}
          </span>

          @if($ill->edi_message_id)
            <div class="mt-2"><small class="text-success"><i class="bi bi-check-circle"></i> EDI: {{ $ill->edi_message_id }}</small></div>
          @endif

          @if($partner)
            <div class="mt-2"><small>Partner: <code>{{ $partner->edi_partner_code }}</code> ({{ $partner->edi_type }})</small></div>
          @endif
        </div>

        @if(count($availableTransitions) > 0)
          <div class="card-footer">
            <form method="POST" action="{{ route('library.ill-requests.transition', $ill->id) }}">
              @csrf
              <div class="input-group input-group-sm">
                <select name="new_status" class="form-select">
                  @foreach($availableTransitions as $t)
                    <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                  @endforeach
                </select>
                <button type="submit" class="btn btn-primary">Transition</button>
              </div>
              <input name="note" class="form-control form-control-sm mt-1" placeholder="Optional note...">
            </form>
          </div>
        @endif
      </div>

      {{-- EDI send --}}
      <div class="card mt-3">
        <div class="card-header">EDI Transmission</div>
        <div class="card-body">
          @if($partner)
            <form method="POST" action="{{ route('library.ill-requests.send-edi', $ill->id) }}">
              @csrf
              <input type="hidden" name="trading_partner_id" value="{{ $partner->id }}">
              <p class="small text-muted mb-2">
                Send ILL request via {{ $partner->edi_partner_code }} ({{ $partner->edi_type }})
                @if($partner->test_mode)
                  <span class="badge bg-warning text-dark">TEST</span>
                @endif
              </p>
              <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                <i class="bi bi-send"></i> Send EDI Message
              </button>
            </form>
          @else
            <p class="small text-muted">No EDI partner linked to this request.</p>
            <a href="{{ route('library.trading-partners.index') }}" class="btn btn-outline-secondary btn-sm w-100">
              Manage Trading Partners
            </a>
          @endif
        </div>
      </div>
    </div>

    {{-- Details --}}
    <div class="col-md-8">
      <div class="card">
        <table class="table table-sm mb-0">
          <tbody>
            <tr><th class="w-25">Type</th><td><span class="badge bg-primary">{{ $ill->type }}</span> @if($ill->request_type) {{ $ill->request_type }} @endif</td></tr>
            <tr><th>Protocol</th><td>{{ $ill->borrowing_protocol ?? '—' }}</td></tr>
            <tr><th>Material</th><td>{{ $ill->material_type ?? '—' }}</td></tr>
            <tr><th>Title</th><td>{{ $ill->title }}</td></tr>
            <tr><th>Author</th><td>{{ $ill->author ?: '—' }}</td></tr>
            <tr><th>ISBN</th><td>{{ $ill->isbn ?: '—' }}</td></tr>
            <tr><th>ISSN</th><td>{{ $ill->issn ?: '—' }}</td></tr>
            <tr><th>Publisher</th><td>{{ $ill->publisher ?: '—' }}</td></tr>
            <tr><th>Year</th><td>{{ $ill->publication_year ?: '—' }}</td></tr>
            <tr><th>Volume / Issue / Pages</th><td>{{ [$ill->volume, $ill->issue, $ill->pages] | array_filter | join(' / ') ?: '—' }}</td></tr>
            <tr><th>Citation</th><td>{{ $ill->citation ?: '—' }}</td></tr>
            <tr><th>Library</th><td>{{ $ill->library_name ?: '—' }}</td></tr>
            <tr><th>Symbol</th><td><code>{{ $ill->library_symbol ?: '—' }}</code></td></tr>
            <tr><th>Request Date</th><td>{{ $ill->request_date ?: '—' }}</td></tr>
            <tr><th>Needed By</th><td class="{{ $ill->needed_by_date && \Carbon\Carbon::parse($ill->needed_by_date)->isPast() ? 'text-danger fw-bold' : '' }}">{{ $ill->needed_by_date ?: '—' }}</td></tr>
            <tr><th>Due Date</th><td class="{{ $ill->due_date && \Carbon\Carbon::parse($ill->due_date)->isPast() && !in_array($ill->status, ['returned','lost']) ? 'text-danger fw-bold' : '' }}">{{ $ill->due_date ?: '—' }}</td></tr>
            <tr><th>Cost</th><td>{{ $ill->cost_currency && $ill->cost_amount ? $ill->cost_currency . ' ' . number_format($ill->cost_amount, 2) : '—' }}</td></tr>
            <tr><th>Shipping</th><td>{{ $ill->shipping_method ?: '—' }}</td></tr>
            <tr><th>Renewals</th><td>{{ $ill->renewal_count }} / {{ $ill->max_renewals ?? 2 }}</td></tr>
            @if($ill->edi_message_id)
              <tr><th>EDI Message ID</th><td><code>{{ $ill->edi_message_id }}</code></td></tr>
            @endif
            @if($ill->closed_at)
              <tr><th>Closed At</th><td>{{ $ill->closed_at }}</td></tr>
              <tr><th>Closed Reason</th><td>{{ $ill->closed_reason ?: '—' }}</td></tr>
            @endif
            @if($ill->staff_note)
              <tr><th>Staff Notes</th><td><small>{!! nl2br(e($ill->staff_note)) !!}</small></td></tr>
            @endif
          </tbody>
        </table>
      </div>

      {{-- Update form --}}
      <div class="card mt-3">
        <div class="card-header">Update Details</div>
        <div class="card-body">
          <form method="POST" action="{{ route('library.ill-requests.update', $ill->id) }}">
            @csrf @method('PATCH')
            <div class="row g-2">
              <div class="col-md-4">
                <label class="form-label small">Due Date</label>
                <input type="date" name="due_date" value="{{ old('due_date', $ill->due_date) }}" class="form-control form-control-sm">
              </div>
              <div class="col-md-4">
                <label class="form-label small">Max Renewals</label>
                <input type="number" name="max_renewals" value="{{ old('max_renewals', $ill->max_renewals ?? 2) }}" class="form-control form-control-sm" min="0" max="10">
              </div>
              <div class="col-md-4">
                <label class="form-label small">Cost (ZAR)</label>
                <input type="number" step="0.01" name="cost_amount" value="{{ old('cost_amount', $ill->cost_amount) }}" class="form-control form-control-sm">
              </div>
              <div class="col-12">
                <label class="form-label small">Responder Note</label>
                <textarea name="responder_note" class="form-control form-control-sm" rows="2">{{ old('responder_note', $ill->responder_note) }}</textarea>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</x-library-layout>
