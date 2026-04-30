@extends('theme::layouts.1col')

@section('title', __('Translation drafts'))

@section('content')
  <div class="container-fluid py-3">
    <h1 class="h3 mb-3">
      <i class="fas fa-language me-2"></i>{{ __('Translation drafts') }}
      <small class="text-muted ms-2">{{ number_format($total) }} {{ __('total') }}</small>
    </h1>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if(session('notice'))
      <div class="alert alert-info">{{ session('notice') }}</div>
    @endif

    {{-- Filter bar --}}
    <form method="GET" class="card mb-3">
      <div class="card-body py-2">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label small mb-1">{{ __('Status') }}</label>
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
              <option value="draft"    {{ $status === 'draft' ? 'selected' : '' }}>{{ __('Pending') }}</option>
              <option value="applied"  {{ $status === 'applied' ? 'selected' : '' }}>{{ __('Applied') }}</option>
              <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>{{ __('Rejected') }}</option>
              <option value="all"      {{ $status === 'all' ? 'selected' : '' }}>{{ __('All') }}</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">{{ __('Target culture') }}</label>
            <select name="target_culture" class="form-select form-select-sm" onchange="this.form.submit()">
              <option value="">{{ __('All') }}</option>
              @foreach($cultures as $c)
                <option value="{{ $c }}" {{ $cultureFilter === $c ? 'selected' : '' }}>{{ $c }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">{{ __('Object ID') }}</label>
            <input type="number" name="object_id" value="{{ $objectFilter ?: '' }}" class="form-control form-control-sm" placeholder="{{ __('any') }}">
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-1">{{ __('Per page') }}</label>
            <select name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
              @foreach([20, 50, 100, 200] as $n)
                <option value="{{ $n }}" {{ $perPage == $n ? 'selected' : '' }}>{{ $n }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-1 text-end">
            <button type="submit" class="btn btn-sm btn-primary">
              <i class="fas fa-filter"></i>
            </button>
          </div>
        </div>
      </div>
    </form>

    @if($drafts->isEmpty())
      <div class="alert alert-info mb-0">
        <i class="fas fa-info-circle me-1"></i>{{ __('No drafts match the current filter.') }}
      </div>
    @else
      <form method="POST" action="{{ route('ahgtranslation.draft-batch') }}">
        @csrf
        <div class="d-flex gap-2 mb-2 align-items-center">
          <button type="submit" name="action" value="approve" class="btn btn-sm btn-success" onclick="return confirm('{{ __('Approve all selected drafts?') }}')">
            <i class="fas fa-check me-1"></i>{{ __('Approve selected') }}
          </button>
          <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger" onclick="return confirm('{{ __('Reject all selected drafts?') }}')">
            <i class="fas fa-times me-1"></i>{{ __('Reject selected') }}
          </button>
          <span class="text-muted small ms-2"><span id="sel-count">0</span> {{ __('selected') }}</span>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:30px;"><input type="checkbox" id="sel-all"></th>
                <th>{{ __('Object') }}</th>
                <th>{{ __('Field') }}</th>
                <th>{{ __('Source') }} → {{ __('Target') }}</th>
                <th>{{ __('Source text') }}</th>
                <th>{{ __('Translation') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('By') }}</th>
                <th>{{ __('Created') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($drafts as $d)
                <tr>
                  <td><input type="checkbox" name="ids[]" value="{{ $d->id }}" class="sel-row"></td>
                  <td>
                    @if($d->slug)
                      <a href="{{ url('/' . $d->slug) }}" class="text-decoration-none">
                        <code class="small">{{ $d->slug }}</code>
                      </a>
                    @else
                      <code class="small text-muted">#{{ $d->object_id }}</code>
                    @endif
                    <br><small class="text-muted">{{ $d->class_name ?? '?' }}</small>
                  </td>
                  <td><code class="small">{{ $d->field_name }}</code></td>
                  <td>
                    <span class="badge bg-secondary">{{ $d->source_culture }}</span>
                    <i class="fas fa-arrow-right small text-muted"></i>
                    <span class="badge bg-primary">{{ $d->target_culture }}</span>
                  </td>
                  <td style="max-width:240px;">
                    <div class="text-muted small" style="overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;">
                      {{ $d->source_text }}
                    </div>
                  </td>
                  <td style="max-width:240px;">
                    <div style="overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;">
                      {{ $d->translated_text }}
                    </div>
                  </td>
                  <td>
                    @if($d->status === 'draft')
                      <span class="badge bg-warning text-dark">{{ __('Pending') }}</span>
                    @elseif($d->status === 'applied')
                      <span class="badge bg-success">{{ __('Applied') }}</span>
                    @elseif($d->status === 'rejected')
                      <span class="badge bg-danger">{{ __('Rejected') }}</span>
                    @else
                      <span class="badge bg-secondary">{{ $d->status }}</span>
                    @endif
                  </td>
                  <td><small>{{ $d->created_by_email ?? '—' }}</small></td>
                  <td><small class="text-muted">{{ \Carbon\Carbon::parse($d->created_at)->diffForHumans() }}</small></td>
                  <td class="text-end">
                    @if($d->status === 'draft')
                      <button type="submit" formaction="{{ route('ahgtranslation.draft-approve', $d->id) }}" class="btn btn-sm btn-outline-success" title="{{ __('Approve & apply') }}">
                        <i class="fas fa-check"></i>
                      </button>
                      <button type="submit" formaction="{{ route('ahgtranslation.draft-reject', $d->id) }}" class="btn btn-sm btn-outline-danger" title="{{ __('Reject') }}">
                        <i class="fas fa-times"></i>
                      </button>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </form>
    @endif
  </div>

  <script>
    (function () {
      const all = document.getElementById('sel-all');
      const rows = document.querySelectorAll('.sel-row');
      const counter = document.getElementById('sel-count');
      function update() {
        const n = document.querySelectorAll('.sel-row:checked').length;
        if (counter) counter.textContent = n;
      }
      if (all) {
        all.addEventListener('change', () => {
          rows.forEach(cb => cb.checked = all.checked);
          update();
        });
      }
      rows.forEach(cb => cb.addEventListener('change', update));
    })();
  </script>
@endsection
