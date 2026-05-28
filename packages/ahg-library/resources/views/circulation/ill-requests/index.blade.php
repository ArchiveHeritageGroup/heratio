<x-library-layout>
  @section('title', 'ILL Requests')

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4">ILL Requests</h2>
    <a href="{{ route('library.ill-requests.create') }}" class="btn btn-primary btn-sm">+ New ILL Request</a>
  </div>

  {{-- Status badges --}}
  <div class="row g-2 mb-3">
    @foreach($counts as $status => $cnt)
      <div class="col-auto">
        <span class="badge rounded-pill bg-{{ $status == 'overdue' ? 'danger' : 'secondary' }}">
          {{ $status }}: {{ $cnt }}
        </span>
      </div>
    @endforeach
  </div>

  <form method="GET" class="row g-2 mb-3">
    <div class="col-md-3">
      <input name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Search title, author, ISBN...">
    </div>
    <div class="col-md-2">
      <select name="status" class="form-select form-select-sm">
        <option value="">All statuses</option>
        @foreach($statuses as $s)
          <option value="{{ $s }}" {{ request('status')==$s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <select name="type" class="form-select form-select-sm">
        <option value="">All types</option>
        <option value="borrow" {{ request('type')=='borrow' ? 'selected' : '' }}>Borrow</option>
        <option value="lend" {{ request('type')=='lend' ? 'selected' : '' }}>Lend</option>
      </select>
    </div>
    <div class="col-md-1">
      <div class="form-check mt-1">
        <input type="checkbox" name="overdue" value="1" id="overdue" class="form-check-input" {{ request('overdue') ? 'checked' : '' }}>
        <label for="overdue" class="form-check-label small">Overdue</label>
      </div>
    </div>
    <div class="col-md-2"><button class="btn btn-secondary btn-sm w-100">Filter</button></div>
    <div class="col-md-2"><a href="{{ route('library.ill-requests.index') }}" class="btn btn-outline-secondary btn-sm w-100">Clear</a></div>
  </form>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="card">
    <table class="table table-hover mb-0">
      <thead class="table-light">
        <tr>
          <th>ILL Number</th>
          <th>Title</th>
          <th>Type</th>
          <th>Protocol</th>
          <th>Status</th>
          <th>Due Date</th>
          <th>Needed By</th>
          <th>EDI</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($requests as $r)
          <tr class="{{ $r->due_date && \Carbon\Carbon::parse($r->due_date)->isPast() && !in_array($r->status, ['returned','lost','cancelled']) ? 'table-danger' : '' }}">
            <td><code>{{ $r->ill_number }}</code></td>
            <td>
              <small>{{ Str::limit($r->title, 50) }}</small>
              @if($r->author)<br><small class="text-muted">{{ Str::limit($r->author, 40) }}</small>@endif
            </td>
            <td>
              <span class="badge bg-{{ $r->type === 'borrow' ? 'primary' : 'info' }}">{{ $r->type }}</span>
              @if($r->request_type)<br><small class="text-muted">{{ $r->request_type }}</small>@endif
            </td>
            <td><small>{{ $r->borrowing_protocol ?? '—' }}</small></td>
            <td>
              <span class="badge bg-{{ $r->status === 'overdue' ? 'danger' : ($r->status === 'received' ? 'success' : 'secondary') }}">
                {{ ucfirst($r->status) }}
              </span>
            </td>
            <td>
              @if($r->due_date)
                <small class="{{ \Carbon\Carbon::parse($r->due_date)->isPast() && !in_array($r->status, ['returned','lost']) ? 'text-danger fw-bold' : '' }}">
                  {{ $r->due_date }}
                </small>
              @else
                <small class="text-muted">—</small>
              @endif
            </td>
            <td>
              @if($r->needed_by_date)
                <small>{{ $r->needed_by_date }}</small>
              @else
                <small class="text-muted">—</small>
              @endif
            </td>
            <td>
              @if($r->edi_message_id)
                <small class="text-success" title="{{ $r->edi_message_id }}"><i class="bi bi-check-circle"></i> EDI</small>
              @else
                <small class="text-muted">Manual</small>
              @endif
            </td>
            <td>
              <a href="{{ route('library.ill-requests.show', $r->id) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-eye"></i>
              </a>
            </td>
          </tr>
        @empty
          <tr><td colspan="9" class="text-center text-muted py-3">No ILL requests found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3">{{ $requests->withQueryString()->links() }}</div>
</x-library-layout>
