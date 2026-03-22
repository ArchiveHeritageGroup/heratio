<h1>{{ __('User %1%', ['%1%' => render_title($resource)]) }}</h1>

@if(!$resource->active)
  <div class="alert alert-danger" role="alert">
    {{ __('This user is inactive') }}
  </div>
@endforeach

@php echo get_component('user', 'aclMenu'); @endphp

<section id="content">

  <section id="userDetails">

    @php echo render_b5_section_heading(
        __('User details'),
        \AtomExtensions\Services\AclService::check($resource, 'update'),
        [$resource, 'module' => 'user', 'action' => 'edit'],
        ['class' => 'rounded-top']
    ); @endphp

    @php echo render_show(__('User name'), render_value_inline($resource->username.($sf_user->user === $resource ? ' ('.__('you').')' : ''))); @endphp

    @if(0 < count($groups = $resource->getAclGroups()))
      @php echo render_show(__('User groups'), $groups); @endphp
    @endforeach

    @if(
        sfConfig::get('app_multi_repository')
        && 0 < count($repositories = $resource->getRepositories())
    )
      @php $repos = [];
          foreach ($repositories as $item) {
              $repos[] = render_title($item);
          }
          echo render_show(__('Repository affiliation'), $repos); @endphp
    @endforeach

    @if($sf_context->getConfiguration()->isPluginEnabled('arRestApiPlugin'))
      @php echo render_show(
          __('REST API key'),
          isset($restApiKey) ? '<code>'.$restApiKey.'</code>' : __('Not generated yet.')
      ); @endphp
    @endforeach

    @if($sf_context->getConfiguration()->isPluginEnabled('arOaiPlugin'))
      @php echo render_show(
          __('OAI-PMH API key'),
          isset($oaiApiKey) ? '<code>'.$oaiApiKey.'</code>' : __('Not generated yet.')
      ); @endphp
    @endforeach

    @if(sfConfig::get('app_audit_log_enabled', false))
      <div id="editing-history-wrapper">
        <div class="accordion accordion-flush border-top hidden" id="editingHistory">
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
                        <th>
                          {{ __('Title') }}
                        </th>
                        <th>
                          {{ __('Date') }}
                        </th>
                        <th>
                          {{ __('Type') }}
                        </th>
                      </tr>
                    </thead>
                    <tbody id="editingHistoryRows">
                    </tbody>
                  </table>
                </div>

                <div class="text-end">
                  <input class="btn atom-btn-white" type="button" id='previousButton' value='{{ __('Previous') }}'>
                  <input class="btn atom-btn-white ms-2" type="button" id='nextButton' value='{{ __('Next') }}'>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    @endforeach

  </section>
</section>

@php echo get_partial('showActions', ['resource' => $resource]); @endphp
