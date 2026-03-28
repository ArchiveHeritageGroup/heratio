@extends('ahg-theme-b5::layout')

@section('title', 'Browse Rights')

@section('content')
<div class="container-fluid mt-3">
  @include('ahg-extended-rights::admin._sidebar')

  <h1><i class="fas fa-search"></i> Browse Rights</h1>

  {{-- Filters --}}
  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" class="row g-2">
        <div class="col-md-2">
          <label class="form-label">Type</label>
          <select name="type" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="rights_statement" {{ request('type') === 'rights_statement' ? 'selected' : '' }}>Rights Statement</option>
            <option value="cc_license" {{ request('type') === 'cc_license' ? 'selected' : '' }}>CC License</option>
            <option value="tk_label" {{ request('type') === 'tk_label' ? 'selected' : '' }}>TK Label</option>
            <option value="embargo" {{ request('type') === 'embargo' ? 'selected' : '' }}>Embargo</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Search</label>
          <input type="text" name="q" class="form-control form-control-sm" value="{{ request('q') }}" placeholder="Object title or ID">
        </div>
        <div class="col-md-2">
          <label class="form-label">Repository</label>
          <select name="repository" class="form-select form-select-sm">
            <option value="">All</option>
            @foreach($repositories ?? [] as $repo)
              <option value="{{ $repo->id }}" {{ request('repository') == $repo->id ? 'selected' : '' }}>{{ e($repo->name ?? '') }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end gap-1">
          <button type="submit" class="btn btn-sm btn-primary">Filter</button>
          <a href="{{ route('ext-rights-admin.browse') }}" class="btn btn-sm btn-secondary">Clear</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr><th>Object</th><th>Rights Type</th><th>Value</th><th>Date</th><th>Actions</th></tr>
        </thead>
        <tbody>
          @forelse($rights ?? [] as $right)
          <tr>
            <td>
              @if(!empty($right->slug))
                <a href="/{{ $right->slug }}">{{ e($right->title ?? 'Untitled') }}</a>
              @else
                {{ e($right->title ?? 'Object #' . ($right->object_id ?? '')) }}
              @endif
            </td>
            <td>{{ ucfirst(str_replace('_', ' ', $right->rights_type ?? '')) }}</td>
            <td>{{ e($right->rights_value ?? '') }}</td>
            <td>{{ $right->rights_date ?? '' }}</td>
            <td>
              @if(!empty($right->slug))
                <a href="{{ route('ext-rights.index', ['slug' => $right->slug]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
              @endif
            </td>
          </tr>
          @empty
          <tr><td colspan="5" class="text-muted">No rights records found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
