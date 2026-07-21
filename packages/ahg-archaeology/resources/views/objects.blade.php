{{-- Archaeological finds browse --}}
@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="fas fa-box-archive"></i> {{ __('Archaeological finds') }}</h1>
    <a href="{{ route('archaeology.index') }}" class="btn btn-outline-secondary btn-sm">&larr; {{ __('Back') }}</a>
  </div>

  <form method="get" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label for="q" class="form-label small">{{ __('Search') }}</label>
        <input type="text" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm"
               placeholder="{{ __('Accession no., context, title') }}">
      </div>
      @foreach([
        ['key' => 'object_type_id', 'vocab' => 'object_type', 'label' => __('Object type')],
        ['key' => 'material_id',    'vocab' => 'material',    'label' => __('Material')],
        ['key' => 'period_id',      'vocab' => 'period',      'label' => __('Period')],
      ] as $f)
        <div class="col-md-3">
          <label for="{{ $f['key'] }}" class="form-label small">{{ $f['label'] }}</label>
          <select id="{{ $f['key'] }}" name="{{ $f['key'] }}" class="form-select form-select-sm">
            <option value="">{{ __('Any') }}</option>
            @foreach($vocab[$f['vocab']] ?? [] as $t)
              <option value="{{ $t->id }}" @selected(($filters[$f['key']] ?? null) == $t->id)>{{ $t->name }}</option>
            @endforeach
          </select>
        </div>
      @endforeach
      <div class="col-12">
        <button class="btn btn-primary btn-sm">{{ __('Filter') }}</button>
      </div>
    </div>
  </form>

  @if($objects->isEmpty())
    <div class="alert alert-info">{{ __('No finds match these criteria.') }}</div>
  @else
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead>
          <tr>
            <th>{{ __('Accession no.') }}</th>
            <th>{{ __('Title') }}</th>
            <th>{{ __('Object type') }}</th>
            <th>{{ __('Material') }}</th>
            <th>{{ __('Period') }}</th>
            <th>{{ __('Site') }}</th>
            <th class="text-end">{{ __('Items') }}</th>
          </tr>
        </thead>
        <tbody>
        @foreach($objects as $o)
          <tr>
            <td><a href="{{ route('archaeology.object', $o->id) }}">{{ $o->accession_number }}</a></td>
            <td>{{ $o->title ?: __('Untitled') }}</td>
            <td>{{ $o->object_type_name ?: '-' }}</td>
            <td>{{ $o->material_name ?: '-' }}</td>
            <td>{{ $o->period_name ?: '-' }}</td>
            <td>
              @if($o->site_id)
                <a href="{{ route('archaeology.site', $o->site_id) }}">{{ $o->site_number }}</a>
              @else
                <span class="badge bg-warning text-dark">{{ __('No site') }}</span>
              @endif
            </td>
            <td class="text-end">{{ number_format($o->item_count) }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    {{ $objects->links() }}
  @endif

</div>
@endsection
