@php
  $currentRoute = \Illuminate\Support\Facades\Route::currentRouteName();
  $htrPages = [
      'admin.ai.htr.dashboard' => ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard'],
      'admin.ai.htr.extract' => ['icon' => 'fa-file-import', 'label' => 'Extract'],
      'admin.ai.htr.batch' => ['icon' => 'fa-layer-group', 'label' => 'Batch'],
      'admin.ai.htr.sources' => ['icon' => 'fa-database', 'label' => 'Sources'],
      'admin.ai.htr.annotate' => ['icon' => 'fa-pen-square', 'label' => 'Annotate'],
      'admin.ai.htr.bulkAnnotate' => ['icon' => 'fa-th', 'label' => 'Bulk Annotate'],
      'admin.ai.htr.fsOverlay' => ['icon' => 'fa-layer-group', 'label' => 'FS Overlay'],
      'admin.ai.htr.training' => ['icon' => 'fa-graduation-cap', 'label' => 'Training'],
  ];
@endphp
<nav class="mb-3">
  <div class="btn-group btn-group-sm flex-wrap">
    @foreach($htrPages as $route => $page)
      <a href="{{ route($route) }}"
         class="btn {{ $currentRoute === $route ? 'atom-btn-outline-success active' : 'atom-btn-white' }}">
        <i class="fas {{ $page['icon'] }} me-1"></i>{{ $page['label'] }}
      </a>
    @endforeach
  </div>
</nav>
