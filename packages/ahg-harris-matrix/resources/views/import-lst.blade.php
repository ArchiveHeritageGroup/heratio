@extends('theme::layouts.1col')
@section('title', __('Import LST'))
@section('content')
<div class="container py-4">

  <h1 class="h3 mb-3"><i class="fas fa-file-import me-2"></i>{{ __('Import LST') }}</h1>
  <p class="text-secondary">
    {{ __('LST is the stratigraphic list format BASP Harris, Stratify and ArchEd write. Units are matched to existing contexts by their context number.') }}
  </p>

  <form method="post" action="{{ route('harris.import.lst', $siteId) }}" enctype="multipart/form-data" class="mb-4">
    @csrf
    <div class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label" for="lst">{{ __('LST file') }}</label>
        <input type="file" name="lst" id="lst" class="form-control" accept=".lst,text/plain" required>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn atom-btn-white">{{ __('Preview') }}</button>
      </div>
    </div>
  </form>

  @if($parsed && $parsed['error'])
    <div class="alert alert-danger">{{ $parsed['error'] }}</div>
  @elseif($parsed)
    <div class="alert alert-info">
      {{ __(':units unit(s) in the file, :matched matched to a context here, :rel relationship(s) parsed.', [
        'units' => count($parsed['units']), 'matched' => $matched, 'rel' => count($parsed['rows'])]) }}
    </div>

    @if(!empty($unmatched))
      {{-- Named and listed, never silently dropped: an import that quietly
           discarded half a site archive would look like a success. --}}
      <div class="alert alert-warning">
        <strong>{{ __('No context here matches these units, so their relationships will be skipped:') }}</strong>
        <div class="mt-1"><code>{{ implode(', ', array_slice($unmatched, 0, 40)) }}{{ count($unmatched) > 40 ? ' ...' : '' }}</code></div>
      </div>
    @endif

    @if(!empty($parsed['contemporary']))
      <div class="alert alert-secondary">
        <strong>{{ __('Contemporary-with pairs are reported, not imported.') }}</strong>
        {{ __('Heratio records same_as, meaning one unit recorded twice. "Contemporary with" is a chronological claim about two distinct units and is a different statement; importing it as same_as would merge contexts that are not the same context.') }}
        <div class="mt-1"><code>{{ count($parsed['contemporary']) }} {{ __('pair(s)') }}</code></div>
      </div>
    @endif

    @if($committed)
      <div class="alert alert-success">
        {{ __(':added relationship(s) added, :duplicate already recorded, :skipped skipped.', [
            'added' => $committed['added'], 'duplicate' => $committed['duplicate'], 'skipped' => $committed['skipped']]) }}
      </div>
      @if(!empty($committed['warnings']))
        <div class="alert alert-warning">
          <strong>{{ __('Rows that were not imported:') }}</strong>
          <ul class="mb-0 mt-1 small">
            @foreach(array_slice($committed['warnings'], 0, 50) as $w)<li>{{ $w }}</li>@endforeach
          </ul>
        </div>
      @endif
    @elseif(!empty($parsed['rows']))
      <form method="post" action="{{ route('harris.import.lst', $siteId) }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="commit" value="1">
        <div class="alert alert-light border">
          {{ __('Re-select the file and commit to write these relationships.') }}
          <div class="row g-2 align-items-end mt-2">
            <div class="col-md-6"><input type="file" name="lst" class="form-control" accept=".lst,text/plain" required></div>
            <div class="col-auto"><button type="submit" class="btn btn-primary">{{ __('Commit import') }}</button></div>
          </div>
        </div>
      </form>
    @endif
  @endif

</div>
@endsection
