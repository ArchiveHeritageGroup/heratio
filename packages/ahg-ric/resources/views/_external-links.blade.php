{{-- External RIC Tools Links --}}
<div class="card mt-3">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <h5 class="mb-0"><i class="fas fa-external-link-alt"></i> RIC Tools</h5>
  </div>
  <div class="card-body">
    <div class="d-grid gap-2">
      @if(Route::has('settings.ahg'))
        <a href="{{ route('settings.ahg', 'fuseki') }}" class="btn btn-outline-primary">
          <i class="fas fa-cog"></i> RIC/Fuseki Settings
        </a>
      @endif
      <a href="https://www.ica.org/standards/RiC/ontology" target="_blank" class="btn btn-outline-info">
        <i class="fas fa-book"></i> RiC-O Ontology Reference
      </a>
      @php
        $fusekiConfig = \Illuminate\Support\Facades\DB::table('ahg_settings')
          ->where('setting_group', 'fuseki')
          ->pluck('setting_value', 'setting_key')
          ->toArray();
        $fusekiEndpoint = $fusekiConfig['fuseki_endpoint'] ?? 'http://localhost:3030/ric';
        $fusekiAdmin = preg_replace('#/[^/]+$#', '/', $fusekiEndpoint);
      @endphp
      <a href="{{ $fusekiAdmin }}" target="_blank" class="btn btn-outline-dark">
        <i class="fas fa-database"></i> Fuseki Admin
      </a>
      <a href="https://ric.theahg.co.za/explorer" target="_blank" class="btn btn-outline-success">
        <i class="fas fa-project-diagram"></i> RIC Explorer
      </a>
      <a href="{{ route('ric.semantic-search') }}" class="btn btn-outline-secondary">
        <i class="fas fa-search"></i> Semantic Search
      </a>
    </div>
  </div>
</div>
