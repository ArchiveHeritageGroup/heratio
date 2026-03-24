{{-- CAS user show/index variant - ported from AtoM ahgThemeB5Plugin/modules/user/templates/indexSuccess.mod_cas.php --}}
{{-- Same as ext-auth show: displays user details without password fields --}}

<h1>{{ __('User :name', ['name' => $user->username ?? '']) }}</h1>

@if(!($user->active ?? true))
  <div class="alert alert-danger" role="alert">
    {{ __('This user is inactive') }}
  </div>
@endif

<section id="content">

  <section id="userDetails">

    <div class="section-heading d-flex justify-content-between align-items-center rounded-top bg-light p-3 border">
      <h4 class="mb-0">{{ __('User details') }}</h4>
      @can('update', $user)
        <a href="{{ route('user.edit', ['slug' => $user->slug]) }}" class="btn btn-sm atom-btn-white">
          <i class="fas fa-pencil-alt me-1"></i>{{ __('Edit') }}
        </a>
      @endcan
    </div>

    <div class="section-body border border-top-0 p-3 mb-3">
      <div class="mb-2">
        <strong>{{ __('User name') }}</strong>:
        {{ $user->username }}
        @if(auth()->check() && auth()->user()->id === $user->id)
          ({{ __('you') }})
        @endif
      </div>

      @if(!empty($userGroups) && count($userGroups) > 0)
        <div class="mb-2">
          <strong>{{ __('User groups') }}</strong>:
          {{ implode(', ', $userGroups->pluck('name')->toArray()) }}
        </div>
      @endif

      @if(config('app.multi_repository', false) && !empty($repositories) && count($repositories) > 0)
        <div class="mb-2">
          <strong>{{ __('Repository affiliation') }}</strong>:
          {{ implode(', ', $repositories->pluck('name')->toArray()) }}
        </div>
      @endif

      @if($restEnabled ?? false)
        <div class="mb-2">
          <strong>{{ __('REST API key') }}</strong>:
          @if(isset($restApiKey))
            <code>{{ $restApiKey }}</code>
          @else
            {{ __('Not generated yet.') }}
          @endif
        </div>
      @endif

      @if($oaiEnabled ?? false)
        <div class="mb-2">
          <strong>{{ __('OAI-PMH API key') }}</strong>:
          @if(isset($oaiApiKey))
            <code>{{ $oaiApiKey }}</code>
          @else
            {{ __('Not generated yet.') }}
          @endif
        </div>
      @endif
    </div>

    @if(config('app.audit_log_enabled', false))
      <div id="editing-history-wrapper">
        <div class="accordion accordion-flush border-top" id="editingHistory">
          <div class="accordion-item rounded-bottom">
            <h2 class="accordion-header" id="history-heading">
              <button class="accordion-button collapsed text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#history-collapse" aria-expanded="false" aria-controls="history-collapse">
                {{ __('Editing history') }}
                <span id="editingHistoryActivityIndicator">
                  <i class="fas fa-spinner fa-spin ms-2" aria-hidden="true"></i>
                  <span class="visually-hidden">{{ __('Loading ...') }}</span>
                </span>
              </button>
            </h2>
            <div id="history-collapse" class="accordion-collapse collapse" aria-labelledby="history-heading">
              <div class="accordion-body">
                <div class="table-responsive mb-3">
                  <table class="table table-bordered mb-0">
                    <thead>
                      <tr>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Type') }}</th>
                      </tr>
                    </thead>
                    <tbody id="editingHistoryRows">
                    </tbody>
                  </table>
                </div>

                <div class="text-end">
                  <input class="btn atom-btn-white" type="button" id="previousButton" value="{{ __('Previous') }}">
                  <input class="btn atom-btn-white ms-2" type="button" id="nextButton" value="{{ __('Next') }}">
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    @endif

  </section>
</section>

@include('ahg-user-manage::_show-actions-mod-cas', ['user' => $user])
