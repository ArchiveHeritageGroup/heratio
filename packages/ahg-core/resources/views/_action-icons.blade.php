@php /**
 * Information Object Action Icons Partial - Laravel Version
 *
 * @package    ahg-core
 * @subpackage templates
 */

use Illuminate\Support\Facades\DB;

if (!function_exists('ahg_get_collection_root_id')) {
    function ahg_get_collection_root_id($resource): ?int
    {
        if (!$resource || !isset($resource->lft) || !isset($resource->rgt)) {
            return $resource->id ?? null;
        }

        $root = DB::table('information_object')
            ->where('lft', '<=', $resource->lft)
            ->where('rgt', '>=', $resource->rgt)
            ->where('parent_id', 1)
            ->orderBy('lft')
            ->first();

        return $root ? $root->id : ($resource->id ?? null);
    }
}

if (!function_exists('ahg_has_digital_object')) {
    function ahg_has_digital_object($resourceId): bool
    {
        if (!$resourceId) {
            return false;
        }

        return DB::table('digital_object')
            ->where('object_id', $resourceId)
            ->exists();
    }
}

if (!function_exists('ahg_show_inventory')) {
    function ahg_show_inventory($resource): bool
    {
        if (!$resource || !isset($resource->id)) {
            return false;
        }

        return DB::table('information_object')
            ->where('parent_id', $resource->id)
            ->exists();
    }
}

if (!function_exists('ahg_is_plugin_enabled')) {
    function ahg_is_plugin_enabled(string $pluginName): bool
    {
        static $cache = [];
        if (isset($cache[$pluginName])) {
            return $cache[$pluginName];
        }
        try {
            $cache[$pluginName] = DB::table('atom_plugin')
                ->where('name', $pluginName)
                ->where('is_enabled', 1)
                ->exists();
        } catch (\Exception $e) {
            $cache[$pluginName] = false;
        }
        return $cache[$pluginName];
    }
}

// Get resource properties
$slug = $resource->slug ?? null;
$resourceId = $resource->id ?? null;
$collectionRootId = ahg_get_collection_root_id($resource);
$hasDigitalObject = ahg_has_digital_object($resourceId);
$showInventory = ahg_show_inventory($resource);
$isAdmin = auth()->check() && auth()->user()->is_admin;
$enableInstitutionalScoping = config('app.enable_institutional_scoping', false);
$searchRealm = session('search-realm');
@endphp

<section id="action-icons">

  <h4 class="h5 mb-2">{{ __('Clipboard') }}</h4>
  @include('ahg-core::clipboard.button', ['slug' => $slug, 'wide' => true, 'type' => 'informationObject'])

  <h4 class="h5 mb-2 mt-3">{{ __('Explore') }}</h4>
  <ul class="ps-3">

    <li>
      <a class="atom-icon-link" href="{{ route('informationobject.reports', $slug ?? '') }}">
        <i class="fas fa-fw fa-print me-1" aria-hidden="true">
        </i>{{ __('Reports') }}
      </a>
    </li>

    @if($showInventory)
      <li>
        <a class="atom-icon-link" href="{{ route('informationobject.inventory', $slug ?? '') }}">
          <i class="fas fa-fw fa-list-alt me-1" aria-hidden="true">
          </i>{{ __('Inventory') }}
        </a>
      </li>
    @endif

    <li>
      @if(isset($resource) && $enableInstitutionalScoping && $searchRealm)
        <a class="atom-icon-link" href="{{ route('informationobject.browse', ['collection' => $collectionRootId, 'repos' => $searchRealm, 'topLod' => 'false']) }}">
      @else
        <a class="atom-icon-link" href="{{ route('informationobject.browse', ['collection' => $collectionRootId, 'topLod' => 'false']) }}">
      @endif
        <i class="fas fa-fw fa-list me-1" aria-hidden="true">
        </i>{{ __('Browse as list') }}
      </a>
    </li>

    @if($hasDigitalObject)
      <li>
        <a class="atom-icon-link" href="{{ route('informationobject.browse', ['collection' => $collectionRootId, 'topLod' => 'false', 'view' => 'card', 'onlyMedia' => 'true']) }}">
          <i class="fas fa-fw fa-image me-1" aria-hidden="true">
          </i>{{ __('Browse digital objects') }}
        </a>
      </li>
    @endif
  </ul>

  @if($isAdmin)
    <h4 class="h5 mb-2">{{ __('Import') }}</h4>
    <ul class="ps-3">
      <li>
        <a class="atom-icon-link" href="{{ route('informationobject.import.xml', $slug ?? '') }}">
          <i class="fas fa-fw fa-download me-1" aria-hidden="true">
          </i>{{ __('XML') }}
        </a>
      </li>

      <li>
        <a class="atom-icon-link" href="{{ route('informationobject.import.csv', $slug ?? '') }}">
          <i class="fas fa-fw fa-download me-1" aria-hidden="true">
          </i>{{ __('CSV') }}
        </a>
      </li>
    </ul>
  @endif

  <h4 class="h5 mb-2">{{ __('Export') }}</h4>
  <ul class="ps-3">
    @if(ahg_is_plugin_enabled('sfDcPlugin'))
      <li>
        <a class="atom-icon-link" href="{{ route('informationobject.export.dc', $slug ?? '') }}">
          <i class="fas fa-fw fa-upload me-1" aria-hidden="true">
          </i>{{ __('Dublin Core 1.1 XML') }}
        </a>
      </li>
    @endif

    @if(ahg_is_plugin_enabled('sfEadPlugin'))
      <li>
        <a class="atom-icon-link" href="{{ route('informationobject.export.ead', $slug ?? '') }}">
          <i class="fas fa-fw fa-upload me-1" aria-hidden="true">
          </i>{{ __('EAD 2002 XML') }}
        </a>
      </li>
    @endif

    @if(($currentModule ?? '') == 'sfModsPlugin' && ahg_is_plugin_enabled('sfModsPlugin'))
      <li>
        <a class="atom-icon-link" href="{{ route('informationobject.export.mods', $slug ?? '') }}">
          <i class="fas fa-fw fa-upload me-1" aria-hidden="true">
          </i>{{ __('MODS 3.5 XML') }}
        </a>
      </li>
    @endif

    @if(ahg_is_plugin_enabled('ahgPortableExportPlugin'))
      <li>
        <a class="atom-icon-link portable-export-link" href="#"
           data-slug="{{ $slug }}"
           title="{{ __('Generate a standalone portable catalogue viewer for offline access') }}">
          <i class="fas fa-fw fa-compact-disc me-1" aria-hidden="true">
          </i>{{ __('Portable Viewer') }}
        </a>
      </li>
    @endif
  </ul>

  @include('ahg-information-object-manage::_finding-aid', ['resource' => $resource, 'contextMenu' => true])

  @include('ahg-information-object-manage::_calculate-dates-link', ['resource' => $resource, 'contextMenu' => true])

</section>

@if(ahg_is_plugin_enabled('ahgPortableExportPlugin'))
<script>
(function() {
  var link = document.querySelector('.portable-export-link');
  if (!link) return;
  link.addEventListener('click', function(e) {
    e.preventDefault();
    var slug = this.getAttribute('data-slug');
    if (!slug) return;
    var origHtml = this.innerHTML;
    this.innerHTML = '<i class="fas fa-fw fa-spinner fa-spin me-1"></i>Starting...';
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch('/portable-export/api/quick-start', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrfToken },
      body: 'slug=' + encodeURIComponent(slug)
    }).then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
          link.innerHTML = '<i class="fas fa-fw fa-check me-1 text-success"></i>Export started';
          link.href = '/portable-export';
          link.removeEventListener('click', arguments.callee);
          link.addEventListener('click', function() { window.location = '/portable-export'; });
        } else {
          alert(data.error || 'Failed to start export');
          link.innerHTML = origHtml;
        }
      })
      .catch(function(err) {
        alert('Error: ' + err.message);
        link.innerHTML = origHtml;
      });
  });
})();
</script>
@endif
