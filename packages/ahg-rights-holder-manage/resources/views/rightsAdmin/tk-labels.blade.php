@extends('theme::layouts.2col')

@section('title', 'TK Labels')
@section('body-class', 'admin rights-admin tk-labels')

@section('sidebar')
  @include('ahg-rights-holder-manage::rightsAdmin._sidebar')
@endsection

@section('title-block')
  <h1 class="mb-0">{{ __('Traditional Knowledge Labels') }}</h1>
@endsection

@section('content')
<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h5 class="mb-0">{{ __('Configured TK Labels') }}</h5>
  </div>
  <div class="card-body p-0">
    @if(isset($tkLabels) && count($tkLabels) > 0)
      <table class="table table-striped table-hover mb-0">
        <thead>
          <tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
            <th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Category') }}</th><th>URI</th><th class="text-end">{{ __('Objects') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($tkLabels as $tk)
            <tr>
              <td><span class="badge" style="background-color:{{ $tk->color ?? '#6c757d' }};">{{ $tk->code ?? '' }}</span></td>
              <td>{{ $tk->name ?? '' }}</td>
              <td>{{ $tk->category_name ?? '' }}</td>
              <td>@if($tk->uri ?? null)<a href="{{ $tk->uri }}" target="_blank">{{ Str::limit($tk->uri, 30) }}</a>@else - @endif</td>
              <td class="text-end">{{ number_format($tk->usage_count ?? 0) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <div class="text-center py-4 text-muted">No TK Labels configured.</div>
    @endif
  </div>
</div>
@endsection
