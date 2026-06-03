@extends('theme::layouts.1col')

@section('title', __('DSAR redaction scope'))

@section('content')
<div class="d-flex align-items-center mb-3">
  <a href="{{ route('ahgprivacy.dsar-view', ['id' => $dsar->id]) }}" class="btn btn-outline-secondary btn-sm me-3" title="{{ __('Back to DSAR') }}">
    <i class="fas fa-arrow-left"></i>
  </a>
  <h1 class="h2 mb-0">
    <i class="fas fa-user-shield me-2"></i>{{ __('Redaction scope') }} - {{ $dsar->reference_number }}
  </h1>
</div>

@if (session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<p class="text-muted">
  {{ __('Add the archival descriptions covered by this request. Each one gets a privacy profile pre-populated (status: pending) so you can mark individual fields for redaction as part of the response.') }}
</p>

<div class="card mb-3">
  <div class="card-header">{{ __('Add a description to scope') }}</div>
  <div class="card-body">
    <form method="POST" action="{{ route('ahgprivacy.dsar-scope-add', ['id' => $dsar->id]) }}" class="row g-2">
      @csrf
      <div class="col-md-9">
        <label class="form-label">{{ __('Archival description (numeric id or slug)') }}</label>
        <input type="text" name="io" class="form-control" required placeholder="{{ __('e.g. 1234 or my-collection-slug') }}">
      </div>
      <div class="col-md-3 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus me-1"></i>{{ __('Add and pre-populate') }}</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header fw-bold">{{ __('Descriptions in scope') }}</div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>{{ __('Description') }}</th>
          <th>{{ __('Privacy status') }}</th>
          <th>{{ __('Added') }}</th>
          <th class="text-end">{{ __('Actions') }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($objects as $o)
          <tr>
            <td>
              <div class="fw-semibold">{{ $o->title ?: __('(untitled)') }}</div>
              <div class="text-muted small">#{{ $o->information_object_id }}</div>
            </td>
            <td>
              <span class="badge {{ $o->redaction_status === 'full' ? 'bg-danger' : ($o->redaction_status === 'partial' ? 'bg-warning text-dark' : 'bg-info text-dark') }}">
                {{ ucfirst($o->redaction_status ?? 'pending') }}
              </span>
            </td>
            <td class="small text-muted">{{ $o->created_at }}</td>
            <td class="text-end">
              <a href="{{ url('/admin/privacy/description/' . $o->information_object_id . '/redaction') }}" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-pen me-1"></i>{{ __('Redact fields') }}
              </a>
              <form method="POST" action="{{ route('ahgprivacy.dsar-scope-remove', ['id' => $dsar->id, 'ioId' => $o->information_object_id]) }}" class="d-inline" onsubmit="return confirm('{{ __('Remove from scope?') }}');">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-center text-muted p-4">{{ __('No descriptions in scope yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
