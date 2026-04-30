@php
  $resource = $resource ?? $repository;
  $noedit = $noedit ?? false;
  $repoLabel = \AhgCore\Services\SettingHelper::get('ui_label_repository', 'Repository');

  // Determine quota type based on upload_limit value
  $uploadLimit = $resource->upload_limit ?? -1;
  if ($uploadLimit == 0) {
      $quotaType = 'disabled';
  } elseif ($uploadLimit == -1) {
      $quotaType = 'unlimited';
  } else {
      $quotaType = 'limited';
  }

  // Calculate disk usage (in GB)
  $diskUsageBytes = $resource->disk_usage ?? 0;
  $diskUsage = round($diskUsageBytes / 1000000000, 2);
  $diskUsageFloat = ($quotaType === 'limited' && $uploadLimit > 0)
      ? min(100, round(($diskUsage / $uploadLimit) * 100, 1))
      : 0;
  $diskUsagePercent = round($diskUsageFloat);
  $showRepoLink = true;
@endphp

<section class="card mb-3" id="upload-limit-card">

  <div class="card-body alert-success d-none" role="alert" aria-hidden="true">
    {{ __('Update successful') }}
  </div>

  <div class="card-body alert-danger d-none" role="alert" aria-hidden="true">
    {{ __('Update failed') }}
  </div>

  <h5 class="p-3 mb-1 border-bottom">
    {{ __('Upload limit') }}
    @if($showRepoLink)
      <span class="d-block text-muted small mt-1">
        {!! __('for :repo', ['repo' => '<a href="' . route('repository.show', ['slug' => $resource->slug]) . '">' . e($resource->authorized_form_of_name ?? '') . '</a>']) !!}
      </span>
    @endif
  </h5>

  <div class="card-body py-2">
    <style>
      #upload-limit-progress-div { height: 25px }
      #upload-limit-progress-bar-div { width: {{ $diskUsageFloat }}% }
    </style>
    @if('limited' === $quotaType)
      <div class="progress mb-1" id="upload-limit-progress-div">
        <div
          class="progress-bar"
          id="upload-limit-progress-bar-div"
          role="progressbar"
          aria-valuenow="{{ $diskUsageFloat }}"
          aria-valuemin="0"
          aria-valuemax="100">
        </div>
      </div>
      <p class="card-text">{!! __(':du of :limit <abbr title="1 GB = 1 000 000 000 bytes">GB</abbr> (:percent%)', ['du' => $diskUsage, 'limit' => $uploadLimit, 'percent' => $diskUsagePercent]) !!}</p>
    @elseif('disabled' === $quotaType)
      <p class="card-text">{{ __('Upload is disabled') }}</p>
    @elseif('unlimited' === $quotaType)
      <p class="card-text">{!! __(':du <abbr title="1 GB = 1 000 000 000 bytes">GB</abbr> of <em>Unlimited</em>', ['du' => $diskUsage]) !!}</p>
    @endif
  </div>

  <div class="card-body">
    @auth
      @if(auth()->user()->is_admin ?? false)
        <a href="#" class="btn atom-btn-white" data-bs-toggle="modal" data-bs-target="#upload-limit-modal">{{ __('Edit') }}</a>
      @endif
    @endauth
  </div>

</section>

@auth
  @if((auth()->user()->is_admin ?? false) && !$noedit)
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

            <form id="upload-limit-form" method="POST" action="{{ route('repository.editUploadLimit', ['slug' => $resource->slug]) }}">
              @csrf
              <div>
                <style>
                  #uploadLimit_value { width: 6em }
                </style>
                <label for="uploadLimit_type">{{ __('Set the upload limit for this :type', ['type' => strtolower($repoLabel)]) }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="uploadLimit[type]" id="uploadLimit_type_disabled" value="disabled"{{ ('disabled' === $quotaType) ? ' checked' : '' }}>
                  <label class="form-check-label" for="uploadLimit_type_disabled">
                    {{ __('Disable uploads') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span>
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="uploadLimit[type]" id="uploadLimit_type_limited" value="limited"{{ ('limited' === $quotaType) ? ' checked' : '' }}>
                  <label class="form-check-label" for="uploadLimit_type_limited">
                    {!! __('Limit uploads to :input GB', ['input' => '<input class="form-control form-control-sm d-inline" id="uploadLimit_value" type="number" step="any" name="uploadLimit[value]" value="' . (($uploadLimit > 0) ? $uploadLimit : '') . '" />']) !!} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span>
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="uploadLimit[type]" id="uploadLimit_type_unlimited" value="unlimited"{{ ('unlimited' === $quotaType) ? ' checked' : '' }}>
                  <label class="form-check-label" for="uploadLimit_type_unlimited">
                    {{ __('Allow unlimited uploads') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span>
                  </label>
                </div>
              </div>
            </form>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn atom-btn-outline-light" data-bs-dismiss="modal">
              {{ __('Cancel') }}
            </button>
            <button type="button" class="btn atom-btn-outline-success" onclick="document.getElementById('upload-limit-form').submit();">
              {{ __('Save') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  @endif
@endauth
