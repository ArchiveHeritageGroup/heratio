{{--
  Records Management — Email capture queue (P2.6)
  @copyright Johan Pieterse / Plain Sailing Information Systems
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'Email Capture')
@section('body-class', 'admin records emails')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="fas fa-envelope me-2"></i> Email Capture</h1>
  <div>
    <a href="{{ route('records.emails.upload-form') }}" class="btn btn-sm btn-success"><i class="fas fa-upload me-1"></i>{{ __('Upload .eml') }}</a>
    <a href="{{ route('records.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Records dashboard') }}</a>
  </div>
</div>

<p class="text-muted small">Captured emails sit here until classified to a file plan node and (optionally) declared as records under the RM lifecycle.</p>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="row g-2 mb-3">
  <div class="col"><div class="card border-secondary"><div class="card-body py-2"><small class="text-muted">{{ __('Total') }}</small><h4 class="mb-0">{{ $counts['total'] }}</h4></div></div></div>
  <div class="col"><div class="card border-warning"><div class="card-body py-2"><small class="text-muted">{{ __('Captured (unclassified)') }}</small><h4 class="mb-0 text-warning">{{ $counts['captured'] }}</h4></div></div></div>
  <div class="col"><div class="card border-info"><div class="card-body py-2"><small class="text-muted">{{ __('Classified') }}</small><h4 class="mb-0 text-info">{{ $counts['classified'] }}</h4></div></div></div>
  <div class="col"><div class="card border-success"><div class="card-body py-2"><small class="text-muted">{{ __('Declared as records') }}</small><h4 class="mb-0 text-success">{{ $counts['declared'] }}</h4></div></div></div>
</div>

<form method="GET" class="row g-2 align-items-end mb-3">
  <div class="col-md-3">
    <label class="form-label small mb-0">{{ __('Status') }}</label>
    <select name="status" class="form-select form-select-sm">
      <option value="">{{ __('All') }}</option>
      <option value="captured"   @selected($filters['status']==='captured')>{{ __('Captured') }}</option>
      <option value="classified" @selected($filters['status']==='classified')>{{ __('Classified') }}</option>
      <option value="declared"   @selected($filters['status']==='declared')>{{ __('Declared') }}</option>
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label small mb-0">{{ __('Source') }}</label>
    <select name="source" class="form-select form-select-sm">
      <option value="">{{ __('All') }}</option>
      <option value="eml_upload" @selected($filters['source']==='eml_upload')>{{ __('EML upload') }}</option>
      <option value="imap"       @selected($filters['source']==='imap')>{{ __('IMAP') }}</option>
      <option value="smtp_drop"  @selected($filters['source']==='smtp_drop')>{{ __('SMTP drop') }}</option>
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label small mb-0">{{ __('Search subject / from / to') }}</label>
    <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm">
  </div>
  <div class="col-md-2">
    <button type="submit" class="btn btn-sm btn-primary">{{ __('Filter') }}</button>
    <a href="{{ route('records.emails.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
  </div>
</form>

<div class="card">
  <table class="table table-hover table-sm mb-0">
    <thead class="table-light">
      <tr>
        <th>{{ __('From') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Sent') }}</th><th>{{ __('Source') }}</th><th>{{ __('File plan') }}</th><th>{{ __('Status') }}</th><th class="text-end"></th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $r)
      <tr>
        <td><small>{{ \Illuminate\Support\Str::limit($r->from_address, 40) }}</small></td>
        <td><strong>{{ \Illuminate\Support\Str::limit($r->subject ?: '[No subject]', 80) }}</strong>
            @if($r->attachment_count) <span class="badge bg-light text-dark"><i class="fas fa-paperclip"></i>{{ $r->attachment_count }}</span>@endif</td>
        <td><small>{{ $r->sent_at }}</small></td>
        <td><small>{{ $r->capture_source }}</small></td>
        <td><small>{{ $r->fileplan_code ? $r->fileplan_code . ' — ' . $r->fileplan_title : '—' }}</small></td>
        <td><span class="badge bg-{{ $r->status === 'declared' ? 'success' : ($r->status === 'classified' ? 'info text-dark' : 'warning text-dark') }}">{{ $r->status }}</span></td>
        <td class="text-end"><a href="{{ route('records.emails.show', $r->id) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-arrow-right"></i></a></td>
      </tr>
      @empty
      <tr><td colspan="7" class="text-center text-muted py-4">No captured emails. Use <strong>{{ __('Upload .eml') }}</strong> to capture one.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<div class="text-muted small mt-2">{{ $total }} email(s) total. Showing {{ count($rows) }}.</div>
@endsection
