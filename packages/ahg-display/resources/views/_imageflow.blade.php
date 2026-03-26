{{-- Image flow / carousel component for digital objects --}}
@php
// Map file extension to FontAwesome icon class
$extIconMap = [
    // Audio
    'mp3' => 'fas fa-file-audio', 'wav' => 'fas fa-file-audio', 'ogg' => 'fas fa-file-audio',
    'flac' => 'fas fa-file-audio', 'aac' => 'fas fa-file-audio', 'wma' => 'fas fa-file-audio',
    // Video
    'mp4' => 'fas fa-file-video', 'avi' => 'fas fa-file-video', 'mkv' => 'fas fa-file-video',
    'mov' => 'fas fa-file-video', 'wmv' => 'fas fa-file-video', 'webm' => 'fas fa-file-video',
    // PDF
    'pdf' => 'fas fa-file-pdf',
    // Word
    'doc' => 'fas fa-file-word', 'docx' => 'fas fa-file-word', 'odt' => 'fas fa-file-word',
    // Excel
    'xls' => 'fas fa-file-excel', 'xlsx' => 'fas fa-file-excel', 'ods' => 'fas fa-file-excel',
    'csv' => 'fas fa-file-excel',
    // PowerPoint
    'ppt' => 'fas fa-file-powerpoint', 'pptx' => 'fas fa-file-powerpoint',
    // Archive
    'zip' => 'fas fa-file-archive', 'rar' => 'fas fa-file-archive', 'tar' => 'fas fa-file-archive',
    'gz' => 'fas fa-file-archive', '7z' => 'fas fa-file-archive',
    // 3D
    'glb' => 'fas fa-cube', 'gltf' => 'fas fa-cube', 'obj' => 'fas fa-cube',
    'stl' => 'fas fa-cube', 'fbx' => 'fas fa-cube',
    // Code/text
    'xml' => 'fas fa-file-code', 'json' => 'fas fa-file-code', 'html' => 'fas fa-file-code',
    'txt' => 'fas fa-file-alt',
];
$imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'svg'];

// Resolve URLs for carousel items
$thumbnailMeta = $thumbnailMeta ?? [];
if (empty($thumbnailMeta) && !empty($thumbnails)) {
    $doIds = [];
    foreach ($thumbnails as $item) {
        try {
            if (isset($item->id, $item->object_id) && $item->id && $item->object_id) {
                $doIds[] = (int) $item->id;
            } elseif (isset($item->parent) && $item->parent && isset($item->parent->id, $item->parent->object_id)) {
                $doIds[] = (int) $item->parent->id;
            }
        } catch (\Exception $e) {
            // skip
        }
    }

    $slugTitleMap = [];
    if (!empty($doIds)) {
        try {
            $rows = \Illuminate\Support\Facades\DB::table('digital_object as do2')
                ->join('slug as s', 's.object_id', '=', 'do2.object_id')
                ->leftJoin('information_object_i18n as ioi', function ($join) {
                    $join->on('ioi.id', '=', 'do2.object_id')
                         ->where('ioi.culture', '=', 'en');
                })
                ->whereIn('do2.id', $doIds)
                ->select('do2.id as do_id', 's.slug', 'ioi.title')
                ->get();
            foreach ($rows as $r) {
                $slugTitleMap[(int) $r->do_id] = ['slug' => $r->slug, 'title' => $r->title ?? ''];
            }
        } catch (\Exception $e) {
            // Fallback: leave empty
        }
    }

    foreach ($thumbnails as $item) {
        $doId = null;
        try {
            if (isset($item->id, $item->object_id) && $item->id && $item->object_id) {
                $doId = (int) $item->id;
            } elseif (isset($item->parent) && $item->parent && isset($item->parent->id, $item->parent->object_id)) {
                $doId = (int) $item->parent->id;
            }
        } catch (\Exception $e) {
            // skip
        }
        $meta = ($doId && isset($slugTitleMap[$doId])) ? $slugTitleMap[$doId] : ['slug' => null, 'title' => ''];
        $thumbnailMeta[] = $meta;
    }
}

/**
 * Get file extension icon class or null if it's a displayable image
 */
if (!function_exists('_getFileIconDisplay')) {
    function _getFileIconDisplay($path, $extIconMap, $imageExts) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, $imageExts)) {
            return null; // displayable image - use <img>
        }
        return $extIconMap[$ext] ?? 'fas fa-file';
    }
}
@endphp
<div
  class="accordion"
  id="atom-digital-object-carousel"
  data-carousel-instructions-text-text-link="{{ __('Clicking this description title link will open the description view page for this digital object. Advancing the carousel above will update this title text.') }}"
  data-carousel-instructions-text-image-link="{{ __('Changing the current slide of this carousel will change the description title displayed in the following carousel. Clicking any image in this carousel will open the related description view page.') }}"
  data-carousel-next-arrow-button-text="{{ __('Next') }}"
  data-carousel-prev-arrow-button-text="{{ __('Previous') }}"
  data-carousel-images-region-label="{{ __('Archival description images carousel') }}"
  data-carousel-title-region-label="{{ __('Archival description title link') }}">
  <div class="accordion-item border-0">
    <h2 class="accordion-header rounded-0 rounded-top border border-bottom-0" id="heading-carousel">
      <button class="accordion-button rounded-0 rounded-top text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-carousel" aria-expanded="true" aria-controls="collapse-carousel">
        <span>{{ __('Image carousel') }}</span>
      </button>
    </h2>
    <div id="collapse-carousel" class="accordion-collapse collapse show" aria-labelledby="heading-carousel">
      <div class="accordion-body bg-secondary px-5 pt-4 pb-3">
        <div id="atom-slider-images" class="mb-0">
          @foreach($thumbnails as $idx => $item)
            @php
              $meta = $thumbnailMeta[$idx] ?? null;
              $slug = $meta ? $meta['slug'] : null;
              $title = $meta ? $meta['title'] : '';
              $href = $slug ? route('informationobject.show', ['slug' => $slug]) : '#';
              $filePath = is_object($item) && method_exists($item, 'getFullPath') ? $item->getFullPath() : ($item->path ?? '');
              $iconClass = _getFileIconDisplay($filePath, $extIconMap, $imageExts);
            @endphp
            <a title="{{ e($title) }}" href="{{ $href }}">
              @if($iconClass)
              <span class="img-thumbnail mx-2 d-inline-flex align-items-center justify-content-center" style="width:120px;height:120px;background:#f8f9fa;">
                <i class="{{ $iconClass }} fa-3x text-secondary"></i>
              </span>
              @else
              <img src="{{ $filePath }}" class="img-thumbnail mx-2" alt="{{ strip_tags($title ?: 'Untitled') }}" style="max-height:120px;">
              @endif
            </a>
          @endforeach
        </div>

        <div id="atom-slider-title">
          @foreach($thumbnails as $idx => $item)
            @php
              $meta = $thumbnailMeta[$idx] ?? null;
              $slug = $meta ? $meta['slug'] : null;
              $title = $meta ? $meta['title'] : '';
              $href = $slug ? route('informationobject.show', ['slug' => $slug]) : '#';
            @endphp
            <a href="{{ $href }}" class="text-white text-center mt-2 mb-1">
              {{ strip_tags($title ?: __('Untitled')) }}
            </a>
          @endforeach
        </div>

        @if(isset($limit) && isset($total) && $limit < $total)
          <div class="text-white text-center mt-2 mb-1">
            {{ __('Results :from to :to of :total', ['from' => 1, 'to' => $limit, 'total' => $total]) }}
            <a class="btn atom-btn-outline-light btn-sm ms-2" href="{{ route('informationobject.browse', ['ancestor' => $resource->id ?? null, 'topLod' => false, 'view' => 'card', 'onlyMedia' => true]) }}">{{ __('Show all') }}</a>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
