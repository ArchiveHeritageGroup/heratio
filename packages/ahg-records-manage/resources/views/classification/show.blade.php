{{--
  RM Classification Rule — detail + dry-run test (P4.2)
  @copyright Johan Pieterse / Plain Sailing Information Systems
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'Rule #' . $rule->id . ' — ' . $rule->name)
@section('body-class', 'admin records classification show')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0">
    <i class="fas fa-magic me-2"></i> {{ $rule->name }}
    @if($rule->is_active)<span class="badge bg-success ms-2">active</span>@else<span class="badge bg-secondary ms-2">inactive</span>@endif
  </h1>
  <div>
    <a href="{{ route('records.classification.edit', $rule->id) }}" class="btn btn-sm btn-primary"><i class="fas fa-pencil-alt me-1"></i>{{ __('Edit') }}</a>
    <a href="{{ route('records.classification.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-list me-1"></i>{{ __('All rules') }}</a>
  </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="row">
  <div class="col-md-7">
    <div class="card mb-3">
      <div class="card-header bg-light">Rule definition</div>
      <table class="table table-sm mb-0">
        <tr><th class="text-muted" style="width:30%">{{ __('Type') }}</th><td><span class="badge bg-secondary">{{ $rule->rule_type }}</span></td></tr>
        <tr><th class="text-muted">{{ __('Pattern') }}</th><td><code>{{ $rule->match_pattern }}</code></td></tr>
        <tr><th class="text-muted">{{ __('Priority') }}</th><td>{{ $rule->priority }}</td></tr>
        <tr><th class="text-muted">{{ __('Apply on') }}</th><td>{{ $rule->apply_on }}</td></tr>
        <tr><th class="text-muted">{{ __('File plan node') }}</th><td>{{ $rule->fileplan_code ?? '?' }} — {{ $rule->fileplan_title ?? '?' }}</td></tr>
        <tr><th class="text-muted">{{ __('Disposal class') }}</th><td>@if($rule->disposal_class_ref){{ $rule->disposal_class_ref }} — {{ $rule->disposal_class_title }}@else<em class="text-muted">inherit from node</em>@endif</td></tr>
        @if($rule->description)<tr><th class="text-muted">{{ __('Description') }}</th><td><small>{!! nl2br(e($rule->description)) !!}</small></td></tr>@endif
        <tr><th class="text-muted">{{ __('Created') }}</th><td><small>{{ $rule->created_at }}</small></td></tr>
      </table>
    </div>

    <div class="card">
      <div class="card-header bg-light"><i class="fas fa-history me-1"></i> Recent matches ({{ $logCount }} total)</div>
      <table class="table table-sm mb-0">
        <thead class="table-light"><tr><th>{{ __('Record') }}</th><th>{{ __('Match detail') }}</th><th>{{ __('When') }}</th></tr></thead>
        <tbody>
        @forelse($recent as $row)
          <tr>
            <td>
              @if($row->slug)<a href="{{ url('/' . $row->slug) }}">{{ $row->title ?: '[Untitled]' }}</a>
              @else <small>{{ $row->title ?: ('IO #' . $row->information_object_id) }}</small>
              @endif
            </td>
            <td><small><code>{{ \Illuminate\Support\Str::limit($row->match_detail, 80) }}</code></small></td>
            <td><small>{{ $row->classified_at }}</small></td>
          </tr>
        @empty
          <tr><td colspan="3" class="text-muted text-center py-3">No matches yet.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-md-5">
    @php $tr = session('test_result'); $tm = session('test_meta'); @endphp
    <div class="card border-info mb-3">
      <div class="card-header bg-info text-white"><i class="fas fa-vial me-1"></i> {{ __('Dry-run test') }}</div>
      <div class="card-body">
        <p class="text-muted small mb-2">Enter sample metadata; the engine returns whether this rule alone would fire (without consulting other rules and without writing anything).</p>
        @if($tr !== null)
          <div class="alert alert-{{ $tr['matched'] ? 'success' : 'warning' }}">
            @if($tr['matched'])
              <i class="fas fa-check-circle me-1"></i> <strong>{{ __('Match.') }}</strong> <code class="small">{{ $tr['detail'] }}</code>
            @else
              <i class="fas fa-times-circle me-1"></i> <strong>{{ __('No match.') }}</strong>
            @endif
          </div>
        @endif

        <form method="POST" action="{{ route('records.classification.test', $rule->id) }}">
          @csrf
          <div class="mb-2">
            <label class="form-label small mb-1">folder_path</label>
            <input type="text" name="folder_path" class="form-control form-control-sm" value="{{ $tm['folder_path'] ?? '' }}" placeholder="{{ __('/Projects/Bridge Construction') }}">
          </div>
          <div class="mb-2">
            <label class="form-label small mb-1">workspace</label>
            <input type="text" name="workspace" class="form-control form-control-sm" value="{{ $tm['workspace'] ?? '' }}">
          </div>
          <div class="mb-2">
            <label class="form-label small mb-1">department</label>
            <input type="text" name="department" class="form-control form-control-sm" value="{{ $tm['department'] ?? '' }}">
          </div>
          <div class="mb-2">
            <label class="form-label small mb-1">mime_type</label>
            <input type="text" name="mime_type" class="form-control form-control-sm" value="{{ $tm['mime_type'] ?? '' }}" placeholder="{{ __('application/pdf') }}">
          </div>
          <div class="mb-2">
            <label class="form-label small mb-1">tags (comma-separated)</label>
            <input type="text" name="tags" class="form-control form-control-sm" value="{{ isset($tm['tags']) ? implode(',', $tm['tags']) : '' }}">
          </div>
          <div class="row g-2 mb-2">
            <div class="col"><label class="form-label small mb-1">custom key</label><input type="text" name="custom_key" class="form-control form-control-sm"></div>
            <div class="col"><label class="form-label small mb-1">custom value</label><input type="text" name="custom_value" class="form-control form-control-sm"></div>
          </div>
          <button type="submit" class="btn btn-info btn-sm"><i class="fas fa-vial me-1"></i>{{ __('Test rule') }}</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
