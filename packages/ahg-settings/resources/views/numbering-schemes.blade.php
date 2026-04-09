@extends('theme::layouts.2col')
@section('title', 'Numbering Schemes')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1><i class="fas fa-hashtag me-2"></i>Numbering Schemes</h1>
@endsection

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <form method="get" class="d-inline-flex gap-2">
          <select name="sector" class="form-select form-select-sm" style="width:auto;">
            <option value="">All Sectors</option>
            @foreach($sectors ?? [] as $code => $label)
              <option value="{{ $code }}" {{ ($sectorFilter ?? '') === $code ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
          <button type="submit" class="btn btn-sm atom-btn-white">Filter</button>
        </form>
      </div>
      <a href="{{ route('settings.numbering-scheme-edit') }}" class="btn atom-btn-white"><i class="fas fa-plus me-1"></i>Add Scheme</a>
    </div>

    @if(empty($schemes))
      <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No numbering schemes configured. Click "Add Scheme" to create one.</div>
    @else
      <div class="table-responsive">
        <table class="table table-hover">
          <thead class="table-dark">
            <tr><th>Name</th><th>Sector</th><th>Pattern</th><th>Preview</th><th>Counter</th><th>Reset</th><th class="text-center">Default</th><th class="text-center">Active</th><th>Actions</th></tr>
          </thead>
          <tbody>
            @foreach($schemes as $scheme)
              <tr>
                <td>
                  <strong>{{ $scheme->name }}</strong>
                  @if($scheme->description)<br><small class="text-muted">{{ $scheme->description }}</small>@endif
                </td>
                <td><span class="badge bg-{{ match($scheme->sector) { 'archive' => 'primary', 'library' => 'success', 'museum' => 'info', 'gallery' => 'warning', 'dam' => 'secondary', default => 'dark' } }}">{{ ucfirst($scheme->sector) }}</span></td>
                <td><code>{{ $scheme->pattern }}</code></td>
                <td><code>{{ $scheme->preview ?? '-' }}</code></td>
                <td>{{ $scheme->counter ?? 0 }}</td>
                <td>{{ $scheme->reset_period ?? 'Never' }}</td>
                <td class="text-center">@if($scheme->is_default ?? false)<i class="fas fa-check text-success"></i>@endif</td>
                <td class="text-center">@if($scheme->is_active ?? false)<i class="fas fa-check text-success"></i>@else<i class="fas fa-times text-danger"></i>@endif</td>
                <td>
                  <a href="{{ route('settings.numbering-scheme-edit', ['id' => $scheme->id]) }}" class="btn btn-sm atom-btn-white"><i class="fas fa-edit"></i></a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
@endsection
