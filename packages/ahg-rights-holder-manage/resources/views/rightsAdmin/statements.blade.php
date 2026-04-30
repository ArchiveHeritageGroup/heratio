@extends('theme::layouts.2col')

@section('title', 'Rights Statements')
@section('body-class', 'admin rights-admin statements')

@section('sidebar')
  @include('ahg-rights-holder-manage::rightsAdmin._sidebar')
@endsection

@section('title-block')
  <h1 class="mb-0">{{ __('Rights Statements') }}</h1>
@endsection

@section('content')
<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h5 class="mb-0">{{ __('Configured Rights Statements') }}</h5>
  </div>
  <div class="card-body p-0">
    @if(isset($statements) && count($statements) > 0)
      <table class="table table-striped table-hover mb-0">
        <thead>
          <tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
            <th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>URI</th><th class="text-end">{{ __('Objects') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($statements as $s)
            <tr>
              <td><span class="badge bg-secondary">{{ $s->code ?? '' }}</span></td>
              <td>{{ $s->name ?? '' }}</td>
              <td>@if($s->uri ?? null)<a href="{{ $s->uri }}" target="_blank">{{ Str::limit($s->uri, 40) }}</a>@else - @endif</td>
              <td class="text-end">{{ number_format($s->usage_count ?? 0) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <div class="text-center py-4 text-muted">No rights statements configured.</div>
    @endif
  </div>
</div>
@endsection
