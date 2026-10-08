@extends('theme::layouts.1col')
@section('title', __('Bulk edit'))
@section('body-class', 'admin bulk-edit')

@section('content')
@php
  $in = fn (string $k, $d = '') => old($k, $input[$k] ?? $d);
  $kind = $in('kind', request('kind', 'find_replace'));
  $nonce = function_exists('csp_nonce') ? csp_nonce() : '';
@endphp
<h1><i class="fas fa-layer-group me-2" aria-hidden="true"></i>{{ __('Bulk edit') }}</h1>
<p class="text-muted">{{ __('Change many archival descriptions at once. Every change is previewed first, runs in the background, is written to the audit log, and can be undone.') }}</p>

@if($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="post" action="{{ route('bulk-edit.index') }}" class="card mb-4" id="bulkEditForm">
  @csrf
  <div class="card-body">
    <fieldset class="mb-3">
      <legend class="h6">{{ __('1. What to change') }}</legend>
      <select name="kind" id="kind" class="form-select w-auto" aria-label="{{ __('Operation') }}">
        <option value="find_replace" @selected($kind === 'find_replace')>{{ __('Find and replace text') }}</option>
        <option value="set_field" @selected($kind === 'set_field')>{{ __('Set a field (level, repository, publication status)') }}</option>
        <option value="rename" @selected($kind === 'rename')>{{ __('Rename from a pattern') }}</option>
        <option value="sort_children" @selected($kind === 'sort_children')>{{ __('Sort children by identifier') }}</option>
      </select>
    </fieldset>

    <fieldset class="mb-3">
      <legend class="h6">{{ __('2. Which descriptions') }}</legend>
      <div class="row g-2">
        <div class="col-md-6">
          <label for="scope_slug" class="form-label">{{ __('A fonds or series (slug), with everything beneath it') }}</label>
          <input type="text" name="scope_slug" id="scope_slug" class="form-control" value="{{ $in('scope_slug') }}" maxlength="255">
          <div class="form-text" data-kind-help="sort_children">{{ __('For sorting: the record whose children are reordered.') }}</div>
        </div>
        <div class="col-md-6" data-kind-hide="sort_children">
          <label for="repository_id" class="form-label">{{ __('or every description of a repository') }}</label>
          <select name="repository_id" id="repository_id" class="form-select">
            <option value="">{{ __('-- None --') }}</option>
            @foreach($repositories as $id => $name)
              <option value="{{ $id }}" @selected((string) $in('repository_id') === (string) $id)>{{ $name }}</option>
            @endforeach
          </select>
        </div>
      </div>
    </fieldset>

    <fieldset class="mb-3" data-kind="find_replace">
      <legend class="h6">{{ __('3. Find and replace') }}</legend>
      <div class="row g-2">
        <div class="col-md-4">
          <label for="column" class="form-label">{{ __('Field') }}</label>
          <select name="column" id="column" class="form-select">
            @foreach($columns as $col => $label)
              <option value="{{ $col }}" @selected($in('column') === $col)>{{ __($label) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label for="pattern" class="form-label">{{ __('Find') }}</label>
          <input type="text" name="pattern" id="pattern" class="form-control" value="{{ $in('pattern') }}" maxlength="1000">
        </div>
        <div class="col-md-4">
          <label for="replacement" class="form-label">{{ __('Replace with') }}</label>
          <input type="text" name="replacement" id="replacement" class="form-control" value="{{ $in('replacement') }}" maxlength="1000">
        </div>
      </div>
      <div class="form-check mt-2">
        <input type="hidden" name="case_sensitive" value="0">
        <input class="form-check-input" type="checkbox" name="case_sensitive" id="case_sensitive" value="1" @checked($in('case_sensitive', '1'))>
        <label class="form-check-label" for="case_sensitive">{{ __('Match case') }}</label>
      </div>
    </fieldset>

    <fieldset class="mb-3" data-kind="set_field">
      <legend class="h6">{{ __('3. Set a field') }}</legend>
      <div class="row g-2">
        <div class="col-md-4">
          <label for="field" class="form-label">{{ __('Field') }}</label>
          <select name="field" id="field" class="form-select">
            @foreach($setFields as $f => $label)
              <option value="{{ $f }}" @selected($in('field') === $f)>{{ __($label) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-8">
          <label for="value" class="form-label">{{ __('New value') }}</label>
          <select name="value" id="value" class="form-select">
            <option value="">{{ __('-- None (clear) --') }}</option>
            <optgroup label="{{ __('Level of description') }}" data-field="level_of_description_id">
              @foreach($levels as $id => $name)<option value="{{ $id }}" @selected((string) $in('value') === (string) $id)>{{ $name }}</option>@endforeach
            </optgroup>
            <optgroup label="{{ __('Repository') }}" data-field="repository_id">
              @foreach($repositories as $id => $name)<option value="{{ $id }}" @selected((string) $in('value') === (string) $id)>{{ $name }}</option>@endforeach
            </optgroup>
            <optgroup label="{{ __('Publication status') }}" data-field="publication_status_id">
              @foreach($statuses as $id => $name)<option value="{{ $id }}" @selected((string) $in('value') === (string) $id)>{{ $name }}</option>@endforeach
            </optgroup>
          </select>
        </div>
      </div>
    </fieldset>

    <fieldset class="mb-3" data-kind="rename">
      <legend class="h6">{{ __('3. Title pattern') }}</legend>
      <input type="text" name="template" id="template" class="form-control" value="{{ $in('template', '{title}') }}" maxlength="1024" aria-describedby="templateHelp">
      <div class="form-text" id="templateHelp">{{ __('Use {title} for the current title, {identifier} for the identifier and {n} for the position in the list. Example: Box {n}: {title}') }}</div>
    </fieldset>

    <fieldset class="mb-3" data-kind="sort_children">
      <legend class="h6">{{ __('3. Sort order') }}</legend>
      <select name="mode" id="mode" class="form-select w-auto">
        <option value="natural" @selected($in('mode', 'natural') === 'natural')>{{ __('Natural (A2 before A10)') }}</option>
        <option value="numeric" @selected($in('mode') === 'numeric')>{{ __('By the first number in the identifier') }}</option>
      </select>
      <div class="form-text">{{ __('Children are ordered by identifier, or by title where there is none.') }}</div>
    </fieldset>

    <button type="submit" class="btn atom-btn-outline-light">{{ __('Preview changes') }}</button>
  </div>
</form>

@if($preview)
  <div class="card mb-4">
    <div class="card-header">{{ trans_choice('{0} Nothing would change|{1} 1 change|[2,*] :n changes', $preview['total'], ['n' => $preview['total']]) }}</div>
    @if($preview['total'] > 0)
      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
          <thead><tr><th scope="col">{{ __('Description') }}</th><th scope="col">{{ __('Before') }}</th><th scope="col">{{ __('After') }}</th></tr></thead>
          <tbody>
            @foreach($preview['changes'] as $c)
              @if($c['field'] === '_order')
                <tr><td colspan="3"><strong>{{ __('New order of the children') }}:</strong>
                  <ol class="mb-0">@foreach($c['order_titles'] as $t)<li>{{ $t }}</li>@endforeach</ol></td></tr>
              @else
                <tr>
                  <td>@if($c['slug'])<a href="{{ url('/'.$c['slug']) }}">{{ $c['title'] ?: '#'.$c['object_id'] }}</a>@else #{{ $c['object_id'] }} @endif</td>
                  <td class="text-break"><del>{{ \Illuminate\Support\Str::limit((string) $c['before'], 300) }}</del></td>
                  <td class="text-break"><ins>{{ \Illuminate\Support\Str::limit((string) $c['after'], 300) }}</ins></td>
                </tr>
              @endif
            @endforeach
          </tbody>
        </table>
      </div>
      @if($preview['total'] > count($preview['changes']))
        <div class="card-body small text-muted">{{ __('Showing the first :n.', ['n' => count($preview['changes'])]) }}</div>
      @endif
      <div class="card-body">
        <form method="post" action="{{ route('bulk-edit.run') }}" onsubmit="return confirm(@json(__('Apply these changes? They can be undone from the batch page.')));">
          @csrf
          @foreach(['kind', 'scope_slug', 'repository_id', 'column', 'pattern', 'replacement', 'case_sensitive', 'field', 'value', 'template', 'mode'] as $k)
            <input type="hidden" name="{{ $k }}" value="{{ $input[$k] ?? '' }}">
          @endforeach
          <button type="submit" class="btn atom-btn-outline-success">{{ __('Apply changes') }}</button>
        </form>
      </div>
    @endif
  </div>
@endif

<h2 class="h5">{{ __('Recent batches') }}</h2>
<div class="table-responsive">
  <table class="table table-sm table-bordered">
    <thead><tr><th scope="col">#</th><th scope="col">{{ __('Operation') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col">{{ __('Changed') }}</th><th scope="col">{{ __('Queued') }}</th></tr></thead>
    <tbody>
      @forelse($batches as $b)
        <tr><td><a href="{{ route('bulk-edit.show', $b->id) }}">{{ $b->id }}</a></td><td>{{ $b->kind }}</td><td>{{ $b->status }}</td><td>{{ $b->changed }}</td><td>{{ $b->created_at }}</td></tr>
      @empty
        <tr><td colspan="5" class="text-muted">{{ __('No bulk edits yet.') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<script nonce="{{ $nonce }}">
(function () {
  var kind = document.getElementById('kind');
  function sync() {
    document.querySelectorAll('[data-kind]').forEach(function (el) { el.hidden = el.dataset.kind !== kind.value; });
    document.querySelectorAll('[data-kind-hide]').forEach(function (el) { el.hidden = el.dataset.kindHide === kind.value; });
    document.querySelectorAll('[data-kind-help]').forEach(function (el) { el.hidden = el.dataset.kindHelp !== kind.value; });
  }
  kind.addEventListener('change', sync);
  sync();
})();
</script>
@endsection
