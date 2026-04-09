@extends('theme::layouts.2col')
@section('title', 'Languages')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1>Languages</h1>
@endsection

@section('content')
<div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="lang-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#lang-collapse" aria-expanded="true">
            Language settings
          </button>
        </h2>
        <div id="lang-collapse" class="accordion-collapse collapse show" aria-labelledby="lang-heading">
          <div class="accordion-body">

            <div class="alert alert-info">
              <i class="fas fa-info-circle me-2"></i>
              Adding or removing languages requires rebuilding the search index.
            </div>

            {{-- Current languages --}}
            <table class="table table-bordered table-sm mb-4">
              <thead>
                <tr>
                  <th>Language code</th>
                  <th>Language name</th>
                  <th style="width: 80px">Actions</th>
                </tr>
              </thead>
              <tbody>
                @foreach($languages as $lang)
                  <tr>
                    <td><code>{{ $lang->name }}</code></td>
                    <td>{{ \Locale::getDisplayLanguage($lang->name, 'en') ?: ucfirst($lang->name) }}</td>
                    <td>
                      @php
                        $setting = \Illuminate\Support\Facades\DB::table('setting')->where('id', $lang->id)->first();
                      @endphp
                      @if($setting && $setting->deleteable)
                        <form method="post" action="{{ route('settings.languages') }}" class="d-inline" onsubmit="return confirm('Remove language {{ $lang->name }}?')">
                          @csrf
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="delete_id" value="{{ $lang->id }}">
                          <button type="submit" class="btn btn-sm atom-btn-outline-danger" title="Remove"><i class="fas fa-times"></i></button>
                        </form>
                      @else
                        <i class="fas fa-lock text-muted" title="Cannot delete"></i>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>

            {{-- Add language --}}
            <form method="post" action="{{ route('settings.languages') }}" class="row g-2 align-items-end">
              @csrf
              <input type="hidden" name="action" value="add">
              <div class="col-auto">
                <label class="form-label">Language code <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="languageCode" class="form-control" placeholder="e.g. fr" maxlength="3" style="width: 100px">
              </div>
              <div class="col-auto">
                <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-plus me-1"></i>Add</button>
              </div>
            </form>

          </div>
        </div>
      </div>
    </div>
@endsection
