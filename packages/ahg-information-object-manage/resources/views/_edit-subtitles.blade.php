<div class="accordion-item">
  <h2 class="accordion-header" id="heading-{{ $usageId }}">
    <button
      class="accordion-button collapsed"
      type="button"
      data-bs-toggle="collapse"
      data-bs-target="#collapse-{{ $usageId }}"
      aria-expanded="false"
      aria-controls="collapse-{{ $usageId }}">
      {{ $usageLabel ?? '' }}
    </button>
  </h2>
  <div
    id="collapse-{{ $usageId }}"
    class="accordion-collapse collapse"
    aria-labelledby="heading-{{ $usageId }}">
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
                  {{ $subtitle->languageName ?? $subtitle->language ?? '' }}
                </td>
                <td>
                  {{ $subtitle->name ?? '' }}
                </td>
                <td>
                  @php
                    $bytes = $subtitle->byteSize ?? 0;
                    if ($bytes >= 1073741824) {
                        $size = number_format($bytes / 1073741824, 2) . ' GB';
                    } elseif ($bytes >= 1048576) {
                        $size = number_format($bytes / 1048576, 2) . ' MB';
                    } elseif ($bytes >= 1024) {
                        $size = number_format($bytes / 1024, 2) . ' KB';
                    } else {
                        $size = $bytes . ' B';
                    }
                  @endphp
                  {{ $size }}
                </td>
                <td class="text-nowrap">
                  <a
                    href="{{ $subtitle->getFullPath() }}"
                    class="btn atom-btn-white me-1">
                    <i class="fas fa-fw fa-eye" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('View file') }}</span>
                  </a>
                  <a
                    href="{{ route('io.digitalobject.delete', $subtitle->id) }}"
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
          <div class="mb-3">
            <label for="trackFile_{{ $usageId }}" class="form-label">{{ __('Select a file to upload (.vtt|.srt)') }}</label>
            <input type="file" class="form-control" id="trackFile_{{ $usageId }}" name="trackFile_{{ $usageId }}" accept=".vtt,.srt">
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="lang_{{ $usageId }}" class="form-label">{{ __('Language') }}</label>
            <select class="form-select" id="lang_{{ $usageId }}" name="lang_{{ $usageId }}">
              @foreach($languages ?? [] as $code => $name)
                <option value="{{ $code }}">{{ $name }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
