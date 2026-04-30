{{--
  SHACL validation result page for one entity.

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')

@section('title', 'Validate ' . $typeLabel . ' #' . $id)
@section('body-class', 'admin ric validate')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="fas fa-check-double me-2"></i> {{ __('SHACL Validation') }}</h1>
  <a href="javascript:history.back()" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
</div>

<p class="text-muted small">
  Validating <strong>{{ $typeLabel }} #{{ $id }}</strong> against the
  <a href="https://openric.org/spec/mapping.html" target="_blank" rel="noopener">OpenRiC</a> SHACL shape set
  (<code>packages/ahg-ric/tools/ric_shacl_shapes.ttl</code>) and the ISAD/ISAAR/ISDIAH/ISDF mandatory-fields list.
</p>

@if($errorMessage)
  <div class="alert alert-danger">
    <i class="fas fa-exclamation-triangle me-2"></i>
    {{ $errorMessage }}
  </div>
@elseif(! $entity)
  <div class="alert alert-warning">
    <i class="fas fa-question-circle me-2"></i>
    Could not load entity {{ $type }}#{{ $id }} — the serializer returned an empty result.
  </div>
@elseif($result['valid'] && empty($result['errors']) && empty($result['warnings']))
  <div class="card border-success mb-3">
    <div class="card-header bg-success text-white">
      <i class="fas fa-check-circle me-2"></i>{{ __('Validation passed') }}
    </div>
    <div class="card-body">
      No mandatory-field violations and no SHACL constraint violations were detected for this record.
    </div>
  </div>
@else
  @if(! empty($result['errors']))
    <div class="card border-danger mb-3">
      <div class="card-header bg-danger text-white">
        <i class="fas fa-times-circle me-2"></i>{{ count($result['errors']) }} error(s)
      </div>
      <ul class="list-group list-group-flush">
        @foreach($result['errors'] as $err)
          <li class="list-group-item">
            <i class="fas fa-exclamation-circle text-danger me-1"></i>
            @if(is_string($err))
              {{ $err }}
            @else
              <code>{{ json_encode($err) }}</code>
            @endif
          </li>
        @endforeach
      </ul>
    </div>
  @endif

  @if(! empty($result['warnings']))
    <div class="card border-warning mb-3">
      <div class="card-header bg-warning text-dark">
        <i class="fas fa-exclamation-triangle me-2"></i>{{ count($result['warnings']) }} warning(s)
      </div>
      <ul class="list-group list-group-flush">
        @foreach($result['warnings'] as $w)
          <li class="list-group-item">
            <i class="fas fa-exclamation-circle text-warning me-1"></i>
            @if(is_string($w))
              {{ $w }}
            @else
              <code>{{ json_encode($w) }}</code>
            @endif
          </li>
        @endforeach
      </ul>
    </div>
  @endif
@endif

@if($entity)
  <details class="mt-4">
    <summary class="text-muted small"><i class="fas fa-code me-1"></i>Show serialised RiC entity (JSON)</summary>
    <pre class="bg-light border p-3 mt-2 small" style="max-height: 400px; overflow:auto;">{{ json_encode($entity, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
  </details>
@endif

<div class="mt-4 small text-muted">
  Full SHACL graph-shape validation requires the <code>pyshacl</code> + <code>rdflib</code> Python packages on the host.
  Without them, only mandatory-fields and referential-integrity checks run. See
  <a href="{{ url('/help/article/shacl-validation-howto') }}">SHACL validation — how to install &amp; extend</a>.
</div>
@endsection
