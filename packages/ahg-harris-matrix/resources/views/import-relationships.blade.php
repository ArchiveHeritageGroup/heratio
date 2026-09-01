@extends('theme::layouts.1col')
@section('title', __('Import relationships'))
@section('content')
<div class="container py-4">

  <h1 class="h3 mb-3"><i class="fas fa-diagram-project me-2"></i>{{ __('Import stratigraphic relationships') }}</h1>
  <p class="text-secondary">
    {{ __('A four-column CSV - siteCode, sourceID, stratRelationship, targetID - as PHASER writes it. Contexts are matched by their context number and must already exist on this site.') }}
  </p>

  <p>
    <a href="{{ route('harris.relationships.template') }}" class="btn btn-sm atom-btn-white">
      <i class="fas fa-download me-1"></i>{{ __('Download template') }}
    </a>
    <a href="{{ route('harris.export.phaser', $siteId) }}" class="btn btn-sm atom-btn-white">
      <i class="fas fa-file-csv me-1"></i>{{ __('Export this site') }}
    </a>
  </p>

  <form method="post" action="{{ route('harris.import.relationships', $siteId) }}" enctype="multipart/form-data" class="mb-4">
    @csrf
    <div class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label" for="csv">{{ __('Relationship CSV') }}</label>
        <input type="file" name="csv" id="csv" class="form-control" accept=".csv,text/csv,text/plain" required>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn atom-btn-white">{{ __('Preview') }}</button>
      </div>
    </div>
  </form>

  @if($parsed && $parsed['error'])
    <div class="alert alert-danger">{{ $parsed['error'] }}</div>
  @elseif($parsed)

    {{-- siteCode is read but never used to CHOOSE the site: the operator picked
         it already, and importing into whatever a file names would be a way to
         write another dig's stratigraphy into this one. Rows for other sites are
         reported so a multi-site file cannot look like a clean import. --}}
    @if(!empty($parsed['other_sites']))
      <div class="alert alert-warning">
        <strong>{{ __('Rows naming a different site were left out:') }}</strong>
        <div class="mt-1">
          @foreach($parsed['other_sites'] as $code => $n)
            <code>{{ $code }} ({{ $n }})</code>@if(!$loop->last), @endif
          @endforeach
        </div>
      </div>
    @endif

    <div class="alert alert-info">
      {{ __(':n row(s) in the file for this site.', ['n' => count($parsed['rows'])]) }}
    </div>

    @if($result)
      <div class="alert {{ $committed ? 'alert-success' : 'alert-light border' }}">
        <strong>
          {{ $committed ? __('Imported.') : __('Preview - nothing has been written yet.') }}
        </strong>
        <div class="mt-1">
          {{ __(':added added, :duplicate already recorded, :skipped skipped.', [
              'added' => $result['added'], 'duplicate' => $result['duplicate'], 'skipped' => $result['skipped']]) }}
        </div>
      </div>

      @if(!empty($result['warnings']))
        {{-- Every refusal is named with its line. A row dropped silently is the
             difference between an import that worked and one that looked like it. --}}
        <div class="alert alert-warning">
          <strong>{{ __('Rows that will not be imported:') }}</strong>
          <ul class="mb-0 mt-1 small">
            @foreach(array_slice($result['warnings'], 0, 50) as $w)
              <li>{{ $w }}</li>
            @endforeach
          </ul>
          @if(count($result['warnings']) > 50)
            <div class="small mt-1">{{ __('... and :n more.', ['n' => count($result['warnings']) - 50]) }}</div>
          @endif
        </div>
      @endif

      @if(!$committed && ($result['added'] > 0 || $result['duplicate'] > 0))
        <form method="post" action="{{ route('harris.import.relationships', $siteId) }}" enctype="multipart/form-data">
          @csrf
          <input type="hidden" name="commit" value="1">
          <div class="alert alert-light border">
            {{ __('Re-select the file and commit to write these relationships.') }}
            <div class="row g-2 align-items-end mt-2">
              <div class="col-md-6"><input type="file" name="csv" class="form-control" accept=".csv,text/csv,text/plain" required></div>
              <div class="col-auto"><button type="submit" class="btn btn-primary">{{ __('Commit import') }}</button></div>
            </div>
          </div>
        </form>
      @endif
    @endif
  @endif

</div>
@endsection
