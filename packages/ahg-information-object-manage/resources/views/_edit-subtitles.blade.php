<div class="accordion-item">
  <h2 class="accordion-header" id="heading-@php echo $usageId; @endphp">
    <button
      class="accordion-button collapsed"
      type="button"
      data-bs-toggle="collapse"
      data-bs-target="#collapse-@php echo $usageId; @endphp"
      aria-expanded="false"
      aria-controls="collapse-@php echo $usageId; @endphp">
      {{ __('%1%', ['%1%' => QubitTerm::getById($usageId)]) }}
    </button>
  </h2>
  <div
    id="collapse-@php echo $usageId; @endphp"
    class="accordion-collapse collapse"
    aria-labelledby="heading-@php echo $usageId; @endphp">
    <div class="accordion-body">
      <div class="table-responsive mb-3">
        <table class="table table-bordered mb-0">
          <thead class="table-light">
	    <tr>
              <th class="w-30">
                {{ __('Language') }}
              </th>
              <th class="w-50">
                {{ __('Filename') }}
              </th>
              <th class="w-20">
                {{ __('Filesize') }}
              </th>
              <th>
                <span class="visually-hidden">{{ __('Actions') }}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            @foreach($subtitles as $subtitle)
              <tr>
                <td>
                  @php echo format_language($subtitle->language); @endphp
                </td>
                <td>
                  @php echo render_value_inline($subtitle->name); @endphp
                </td>
                <td>
                  @php echo hr_filesize($subtitle->byteSize); @endphp
                </td>
                <td class="text-nowrap">
                  <a
                    href="@php echo $subtitle->getFullPath(); @endphp"
                    class="btn atom-btn-white me-1">
                    <i class="fas fa-fw fa-eye" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('View file') }}</span>
                  </a>
                  <a
                    href="@php echo url_for([
                        $subtitle,
                        'module' => 'digitalobject',
                        'action' => 'delete',
                    ]); @endphp"
                    class="btn atom-btn-white">
                    <i class="fas fa-fw fa-times" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('Delete') }}</span>
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="row">
        <div class="col-md-6">
          @php echo render_field($form["trackFile_{$usageId}"]->label(__(
              'Select a file to upload (.vtt|.srt)'
          ))); @endphp
        </div>
        <div class="col-md-6">    
          @php echo render_field($form["lang_{$usageId}"]->label(__('Language'))); @endphp
        </div>
      </div>
    </div>
  </div>
</div>
