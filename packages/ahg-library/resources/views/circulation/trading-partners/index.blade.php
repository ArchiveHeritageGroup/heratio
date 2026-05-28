<x-library-layout>
  @section('title', 'EDI Trading Partners')

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4">EDI Trading Partners</h2>
    <a href="{{ route('library.trading-partners.create') }}" class="btn btn-primary btn-sm">
      + New Partner
    </a>
  </div>

  {{-- Stats row --}}
  <div class="row g-2 mb-3">
    <div class="col-md-3">
      <div class="card text-center border-primary">
        <div class="card-body py-2"><strong>{{ $stats['total'] }}</strong><br><small>Total</small></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center border-success">
        <div class="card-body py-2"><strong>{{ $stats['active'] }}</strong><br><small>Active</small></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center border-warning">
        <div class="card-body py-2"><strong>{{ $stats['errors'] }}</strong><br><small>Errors</small></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body py-2"><strong>{{ $stats['sftp'] }} / {{ $stats['as2'] }}</strong><br><small>SFTP / AS2</small></div>
      </div>
    </div>
  </div>

  {{-- Filter bar --}}
  <form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
      <input name="search" value="{{ request('search') }}" class="form-control form-control-sm"
        placeholder="Search partner code...">
    </div>
    <div class="col-md-2">
      <select name="edi_type" class="form-select form-select-sm">
        <option value="">All types</option>
        @foreach(['EANCOM','X12','UN/EDIFACT','CUSTOM'] as $t)
          <option value="{{ $t }}" {{ request('edi_type')==$t ? 'selected' : '' }}>{{ $t }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <select name="active" class="form-select form-select-sm">
        <option value="">All</option>
        <option value="1" {{ request('active')==='1' ? 'selected' : '' }}>Active</option>
        <option value="0" {{ request('active')==='0' ? 'selected' : '' }}>Inactive</option>
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-secondary btn-sm w-100">Filter</button>
    </div>
    <div class="col-md-2">
      <a href="{{ route('library.trading-partners.index') }}" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
    </div>
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
          <th>Partner Code</th>
          <th>EDI Type</th>
          <th>Profile</th>
          <th>Endpoint</th>
          <th>Vendor</th>
          <th>Status</th>
          <th>Last Outbound</th>
          <th>Last Error</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($partners as $p)
          <tr>
            <td><code>{{ $p->edi_partner_code }}</code></td>
            <td><span class="badge bg-info">{{ $p->edi_type }}</span></td>
            <td><small>{{ $p->message_profile }}</small></td>
            <td><small>{{ $p->config_summary }}</small></td>
            <td><small>{{ $p->vendor?->name ?: '—' }}</small></td>
            <td>
              @if($p->is_active)
                <span class="badge bg-success">Active</span>
              @else
                <span class="badge bg-secondary">Inactive</span>
              @endif
              @if($p->test_mode)
                <span class="badge bg-warning text-dark">TEST</span>
              @endif
            </td>
            <td>
              @if($p->last_outbound_at)
                <small>{{ $p->last_outbound_at->diffForHumans() }}</small>
              @else
                <small class="text-muted">Never</small>
              @endif
            </td>
            <td>
              @if($p->last_error_at)
                <span class="text-danger" title="{{ $p->last_error_message }}">
                  <small>{{ $p->last_error_at->diffForHumans() }}</small>
                </span>
              @else
                <small class="text-muted">—</small>
              @endif
            </td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary btn-test"
                  data-id="{{ $p->id }}"
                  title="Test connection">
                  <i class="bi bi-plug"></i>
                </button>
                <a href="{{ route('library.trading-partners.edit', $p->id) }}" class="btn btn-outline-secondary">
                  <i class="bi bi-pencil"></i>
                </a>
                <form method="POST" action="{{ route('library.trading-partners.toggle', $p->id) }}" class="d-inline">
                  @csrf @method('PATCH')
                  <button class="btn btn-outline-warning" title="{{ $p->is_active ? 'Deactivate' : 'Activate' }}">
                    <i class="bi bi-toggle-{{ $p->is_active ? 'on' : 'off' }}"></i>
                  </button>
                </form>
                <form method="POST" action="{{ route('library.trading-partners.destroy', $p->id) }}" class="d-inline"
                  onsubmit="return confirm('Delete this partner?');">
                  @csrf @method('DELETE')
                  <button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="9" class="text-center text-muted py-3">No trading partners found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3">{{ $partners->withQueryString()->links() }}</div>
</x-library-layout>

@push('scripts')
<script>
document.querySelectorAll('.btn-test').forEach(btn => {
  btn.addEventListener('click', async () => {
    const id = btn.dataset.id;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    try {
      const r = await fetch(`/library-manage/trading-partners/${id}/test`);
      const d = await r.json();
      alert((d.ok ? 'OK' : 'FAIL') + ': ' + d.message);
    } catch(e) {
      alert('Test failed: ' + e.message);
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-plug"></i>';
    }
  });
});
</script>
@endpush
