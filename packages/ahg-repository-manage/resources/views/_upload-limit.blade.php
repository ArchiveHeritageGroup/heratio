<section class="card mb-3" id="upload-limit-card">

  <div class="card-body alert-success d-none" role="alert" aria-hidden="true">
    {{ __('Update successful') }}
  </div>

  <div class="card-body alert-danger d-none" role="alert" aria-hidden="true">
    {{ __('Update failed') }}
  </div>

  <h5 class="p-3 mb-1 border-bottom">
    {{ __('Upload limit') }}
    @if('sfIsdiahPlugin' != $sf_context->getModuleName())
      <span class="d-block text-muted small mt-1">
        {{ __('for %repo%', ['%repo%' => link_to($resource->getAuthorizedFormOfName(['cultureFallback' => true]), ['module' => 'repository', 'slug' => $resource->slug])]) }}
      </span>
    @endforeach
  </h5>

  <div class="card-body py-2">
    <style @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
	#upload-limit-progress-div { height : 25px }
	#upload-limit-progress-bar-div {width: @php echo $sf_data->getRaw('diskUsageFloat'); @endphp%}
    </style>
    @if('limited' == $quotaType)
      <div class="progress mb-1" id="upload-limit-progress-div">
        <div
          class="progress-bar"
          id="upload-limit-progress-bar-div"
          role="progressbar"
          aria-valuenow="@php echo $sf_data->getRaw('diskUsageFloat'); @endphp"
          aria-valuemin="0"
          aria-valuemax="100">
        </div>
      </div>
      <p class="card-text">{{ __('%du% of %limit% <abbr title="1 GB = 1 000 000 000 bytes">GB</abbr> (%percent%%)', ['%du%' => $sf_data->getRaw('diskUsage'), '%limit%' => $sf_data->getRaw('uploadLimit'), '%percent%' => $sf_data->getRaw('diskUsagePercent')]) }}</p>
    @php } elseif ('disabled' == $quotaType) { @endphp
      <p class="card-text">{{ __('Upload is disabled') }}</p>
    @php } elseif ('unlimited' == $quotaType) { @endphp
      <p class="card-text">{{ __('%du% <abbr title="1 GB = 1 000 000 000 bytes">GB</abbr> of <em>Unlimited</em>&nbsp;', ['%du%' => $sf_data->getRaw('diskUsage')]) }}</p>
    @endforeach
  </div>

  <div class="card-body">
    @if($sf_user->isAdministrator())
      <a href="#" class="btn atom-btn-white" data-bs-toggle="modal" data-bs-target="#upload-limit-modal">{{ __('Edit') }}</a>
    @endforeach
  </div>

</section>

@if($sf_user->isAdministrator() && !$noedit)
  <div
  class="modal fade"
  id="upload-limit-modal"
  data-bs-backdrop="static"
  tabindex="-1"
  aria-labelledby="upload-limit-modal-heading"
  aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="upload-limit-modal-heading">
            {{ __('Edit upload limit') }}
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal">
            <span class="visually-hidden">{{ __('Close') }}</span>
          </button>
        </div>
        <div class="modal-body">

          <form id="upload-limit-form" method="POST" action="@php echo url_for([$resource, 'module' => 'repository', 'action' => 'editUploadLimit']); @endphp">
            @php echo $form->renderHiddenFields(); @endphp
	    <div>
	      <style @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
                #uploadLimit_value { width: 6em }
              </style>	
              <label for="uploadLimit_type">{{ __('Set the upload limit for this %1%', ['%1%' => strtolower(sfConfig::get('app_ui_label_repository'))]) }} <span class="badge bg-secondary ms-1">Required</span></label>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="uploadLimit[type]" id="uploadLimit_type_disabled" value="disabled"@php echo ('disabled' == $quotaType) ? ' checked' : ''; @endphp>
                <label class="form-check-label" for="uploadLimit_type_disabled">
                  {{ __('Disable uploads') }} <span class="badge bg-secondary ms-1">Required</span>
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="uploadLimit[type]" id="uploadLimit_type_limited" value="limited"@php echo ('limited' == $quotaType) ? ' checked' : ''; @endphp>
                <label class="form-check-label" for="uploadLimit_type_limited">
                  {{ __('Limit uploads to %1% GB', ['%1%' => '<input class="form-control form-control-sm d-inline" id="uploadLimit_value" type="number" step="any" name="uploadLimit[value]" value="'.(($resource->uploadLimit > 0) ? $resource->uploadLimit : '').'" />']) }} <span class="badge bg-secondary ms-1">Required</span>
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="uploadLimit[type]" id="uploadLimit_type_unlimited" value="unlimited"@php echo ('unlimited' == $quotaType) ? ' checked' : ''; @endphp>
                <label class="form-check-label" for="uploadLimit_type_unlimited">
                  {{ __('Allow unlimited uploads', ['%1%' => sfConfig::get('app_ui_label_repository')]) }} <span class="badge bg-secondary ms-1">Required</span>
                </label>
              </div>
            </div>
          </form>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">
            {{ __('Cancel') }}
          </button>
          <button type="button" class="btn atom-btn-white">
            {{ __('Save') }}
          </button>
        </div>
      </div>
    </div>
  </div>
@endforeach
