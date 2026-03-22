@php /**
 * Information Object Action Icons Partial - Laravel Version
 *
 * @package    ahgThemeB5Plugin
 * @subpackage templates
 */

use Illuminate\Database\Capsule\Manager as DB;

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

if (!function_exists('ahg_url_for_dc_export')) {
    function ahg_url_for_dc_export($resource): string
    {
        $slug = $resource->slug ?? null;
        if ($slug) {
            return url_for(['module' => 'sfDcPlugin', 'action' => 'index', 'slug' => $slug, 'sf_format' => 'xml']);
        }
        return url_for(['module' => 'sfDcPlugin', 'action' => 'index', 'id' => $resource->id, 'sf_format' => 'xml']);
    }
}

if (!function_exists('ahg_url_for_ead_export')) {
    function ahg_url_for_ead_export($resource): string
    {
        $slug = $resource->slug ?? null;
        if ($slug) {
            return url_for(['module' => 'sfEadPlugin', 'action' => 'index', 'slug' => $slug, 'sf_format' => 'xml']);
        }
        return url_for(['module' => 'sfEadPlugin', 'action' => 'index', 'id' => $resource->id, 'sf_format' => 'xml']);
    }
}

if (!function_exists('ahg_resource_url')) {
    function ahg_resource_url($resource, string $module, string $action): string
    {
        $slug = is_object($resource) ? ($resource->slug ?? null) : null;
        if ($slug) {
            return url_for(['module' => $module, 'action' => $action, 'slug' => $slug]);
        }
        $id = is_object($resource) ? ($resource->id ?? null) : $resource;
        return url_for(['module' => $module, 'action' => $action, 'id' => $id]);
    }
}

// Get resource properties
$slug = $resource->slug ?? null;
$resourceId = $resource->id ?? null;
$collectionRootId = ahg_get_collection_root_id($resource);
$hasDigitalObject = ahg_has_digital_object($resourceId);
$showInventory = ahg_show_inventory($resource); @endphp

<section id="action-icons">

  <h4 class="h5 mb-2">{{ __('Clipboard') }}</h4>
  @php echo get_component('clipboard', 'button', ['slug' => $slug, 'wide' => true, 'type' => 'informationObject']); @endphp

  <h4 class="h5 mb-2 mt-3">{{ __('Explore') }}</h4>
  <ul class="ps-3">

    <li>
      <a class="atom-icon-link" href="@php echo ahg_resource_url($resource, 'informationobject', 'reports'); @endphp">
        <i class="fas fa-fw fa-print me-1" aria-hidden="true">
        </i>{{ __('Reports') }}
      </a>
    </li>

    @if($showInventory)
      <li>
        <a class="atom-icon-link" href="@php echo ahg_resource_url($resource, 'informationobject', 'inventory'); @endphp">
          <i class="fas fa-fw fa-list-alt me-1" aria-hidden="true">
          </i>{{ __('Inventory') }}
        </a>
      </li>
    @endforeach

    <li>
      @if(isset($resource) && sfConfig::get('app_enable_institutional_scoping') && $sf_user->hasAttribute('search-realm'))
        <a class="atom-icon-link" href="@php echo url_for([
            'module' => 'informationobject',
            'action' => 'browse',
            'collection' => $collectionRootId,
            'repos' => $sf_user->getAttribute('search-realm'),
            'topLod' => false, ]); @endphp">
      @php } else { @endphp
        <a class="atom-icon-link" href="@php echo url_for([
            'module' => 'informationobject',
            'action' => 'browse',
            'collection' => $collectionRootId,
            'topLod' => false, ]); @endphp">
      @endforeach
        <i class="fas fa-fw fa-list me-1" aria-hidden="true">
        </i>{{ __('Browse as list') }}
      </a>
    </li>

    @if($hasDigitalObject)
      <li>
        <a class="atom-icon-link" href="@php echo url_for([
            'module' => 'informationobject',
            'action' => 'browse',
            'collection' => $collectionRootId,
            'topLod' => false,
            'view' => 'card',
            'onlyMedia' => true, ]); @endphp">
          <i class="fas fa-fw fa-image me-1" aria-hidden="true">
          </i>{{ __('Browse digital objects') }}
        </a>
      </li>
    @endforeach
  </ul>

  @if($sf_user->isAdministrator())
    <h4 class="h5 mb-2">{{ __('Import') }}</h4>
    <ul class="ps-3">
      <li>
        <a class="atom-icon-link" href="@php echo ahg_resource_url($resource, 'object', 'importSelect') . '?type=xml'; @endphp">
          <i class="fas fa-fw fa-download me-1" aria-hidden="true">
          </i>{{ __('XML') }}
        </a>
      </li>

      <li>
        <a class="atom-icon-link" href="@php echo ahg_resource_url($resource, 'object', 'importSelect') . '?type=csv'; @endphp">
          <i class="fas fa-fw fa-download me-1" aria-hidden="true">
          </i>{{ __('CSV') }}
        </a>
      </li>
    </ul>
  @endforeach

  <h4 class="h5 mb-2">{{ __('Export') }}</h4>
  <ul class="ps-3">
    @if($sf_context->getConfiguration()->isPluginEnabled('sfDcPlugin'))
      <li>
        <a class="atom-icon-link" href="@php echo ahg_url_for_dc_export($resource); @endphp">
          <i class="fas fa-fw fa-upload me-1" aria-hidden="true">
          </i>{{ __('Dublin Core 1.1 XML') }}
        </a>
      </li>
    @endforeach

    @if($sf_context->getConfiguration()->isPluginEnabled('sfEadPlugin'))
      <li>
        <a class="atom-icon-link" href="@php echo ahg_url_for_ead_export($resource); @endphp">
          <i class="fas fa-fw fa-upload me-1" aria-hidden="true">
          </i>{{ __('EAD 2002 XML') }}
        </a>
      </li>
    @endforeach

    @if('sfModsPlugin' == $sf_context->getModuleName() && $sf_context->getConfiguration()->isPluginEnabled('sfModsPlugin'))
      <li>
        <a class="atom-icon-link" href="@php echo url_for(['module' => 'sfModsPlugin', 'action' => 'index', 'slug' => $slug, 'sf_format' => 'xml']); @endphp">
          <i class="fas fa-fw fa-upload me-1" aria-hidden="true">
          </i>{{ __('MODS 3.5 XML') }}
        </a>
      </li>
    @endforeach

    @if($sf_context->getConfiguration()->isPluginEnabled('ahgPortableExportPlugin'))
      <li>
        <a class="atom-icon-link portable-export-link" href="#"
           data-slug="{{ $slug }}"
           title="{{ __('Generate a standalone portable catalogue viewer for offline access') }}">
          <i class="fas fa-fw fa-compact-disc me-1" aria-hidden="true">
          </i>{{ __('Portable Viewer') }}
        </a>
      </li>
    @endforeach
  </ul>

  @php echo get_component('informationobject', 'findingAid', ['resource' => $resource, 'contextMenu' => true]); @endphp

  @php echo get_component('informationobject', 'calculateDatesLink', ['resource' => $resource, 'contextMenu' => true]); @endphp

</section>

@if($sf_context->getConfiguration()->isPluginEnabled('ahgPortableExportPlugin'))
<script @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
(function() {
  var link = document.querySelector('.portable-export-link');
  if (!link) return;
  link.addEventListener('click', function(e) {
    e.preventDefault();
    var slug = this.getAttribute('data-slug');
    if (!slug) return;
    var origHtml = this.innerHTML;
    this.innerHTML = '<i class="fas fa-fw fa-spinner fa-spin me-1"></i>Starting...';
    fetch('/portable-export/api/quick-start', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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