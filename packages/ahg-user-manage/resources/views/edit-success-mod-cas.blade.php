{{-- CAS user edit variant - ported from AtoM ahgThemeB5Plugin/modules/user/templates/editSuccess.mod_cas.php --}}
{{-- CAS users cannot change username/password, only groups + access control --}}
@extends('theme::layouts.1col')

@section('title')
  <h1>{{ __('User :name', ['name' => $user->username ?? '']) }}</h1>
@endsection

@section('content')

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('user.update', ['slug' => $user->slug]) }}" method="POST" id="editForm">
    @csrf

    <div class="accordion mb-3">
      @if(auth()->user()->id !== $user->id)
        <div class="accordion-item">
          <h2 class="accordion-header" id="basic-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#basic-collapse" aria-expanded="false" aria-controls="basic-collapse">
              {{ __('Basic info') }}
            </button>
          </h2>
          <div id="basic-collapse" class="accordion-collapse collapse" aria-labelledby="basic-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">{{ __('Active') }}</label>
                <select name="active" class="form-select">
                  <option value="1" @selected(old('active', $user->active ?? 1) == 1)>{{ __('Yes') }}</option>
                  <option value="0" @selected(old('active', $user->active ?? 1) == 0)>{{ __('No') }}</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      @endif

      <div class="accordion-item">
        <h2 class="accordion-header" id="access-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#access-collapse" aria-expanded="false" aria-controls="access-collapse">
            {{ __('Access control') }}
          </button>
        </h2>
        <div id="access-collapse" class="accordion-collapse collapse" aria-labelledby="access-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label class="form-label">{{ __('User groups') }}</label>
              <select name="groups[]" class="form-select" multiple>
                @foreach($allGroups ?? [] as $group)
                  <option value="{{ $group->id }}" @selected(in_array($group->id, $userGroupIds ?? []))>{{ $group->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">{{ __('Allowed languages for translation') }}</label>
              <select name="translate[]" class="form-select" multiple>
                @foreach($languages ?? [] as $lang)
                  <option value="{{ $lang->id }}" @selected(in_array($lang->id, $userLanguages ?? []))>{{ $lang->name }}</option>
                @endforeach
              </select>
            </div>

            @if($restEnabled ?? false)
              <div class="mb-3">
                <label class="form-label">
                  {{ __('REST API access key') }}
                  @if(isset($restApiKey))
                    : <code class="ms-2">{{ $restApiKey }}</code>
                  @endif
                </label>
                <div>
                  <button type="button" name="regenerateRestApiKey" class="btn btn-sm atom-btn-white">{{ __('Regenerate') }}</button>
                </div>
              </div>
            @endif

            @if($oaiEnabled ?? false)
              <div class="mb-3">
                <label class="form-label">
                  {{ __('OAI-PMH API access key') }}
                  @if(isset($oaiApiKey))
                    : <code class="ms-2">{{ $oaiApiKey }}</code>
                  @endif
                </label>
                <div>
                  <button type="button" name="regenerateOaiApiKey" class="btn btn-sm atom-btn-white">{{ __('Regenerate') }}</button>
                </div>
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('user.show', ['slug' => $user->slug]) }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Save') }}"></li>
    </ul>

  </form>

@endsection
