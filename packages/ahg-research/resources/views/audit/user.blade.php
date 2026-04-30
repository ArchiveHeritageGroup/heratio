{{-- Audit User Activity - Migrated from AtoM: audit/userSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'User Activity: ' . e($user->username ?? ''))

@section('content')
<h1><i class="fas fa-user text-primary me-2"></i>User Activity: {{ e($user->username ?? '') }}</h1>

<div class="row mb-4">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header">Activity by Table</div>
      <ul class="list-group list-group-flush">
        @foreach($tableStats ?? [] as $stat)
          <li class="list-group-item d-flex justify-content-between">{{ $stat->table_name }}<span class="badge bg-primary">{{ $stat->count }}</span></li>
        @endforeach
      </ul>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card">
      <div class="card-header">Activity by Action</div>
      <ul class="list-group list-group-flush">
        @foreach($actionStats ?? [] as $stat)
          @php $ac = match($stat->action) { 'create' => 'success', 'update' => 'warning', 'delete' => 'danger', default => 'secondary' }; @endphp
          <li class="list-group-item d-flex justify-content-between"><span class="badge bg-{{ $ac }}">{{ ucfirst($stat->action) }}</span><span class="badge bg-primary">{{ $stat->count }}</span></li>
        @endforeach
      </ul>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">Recent Activity ({{ $totalCount ?? 0 }} total)</div>
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Action') }}</th><th>{{ __('Table') }}</th><th>{{ __('Record') }}</th><th></th></tr></thead>
      <tbody>
        @foreach($activity ?? [] as $entry)
          @php $ac = match($entry->action) { 'create' => 'success', 'update' => 'warning', 'delete' => 'danger', default => 'secondary' }; @endphp
          <tr>
            <td class="small">{{ $entry->created_at }}</td>
            <td><span class="badge bg-{{ $ac }}">{{ ucfirst($entry->action) }}</span></td>
            <td><code>{{ $entry->table_name }}</code></td>
            <td>#{{ $entry->record_id }}</td>
            <td><a href="{{ route('audit.view', $entry->id) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
<div class="mt-3"><a href="{{ route('audit.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a></div>
@endsection
