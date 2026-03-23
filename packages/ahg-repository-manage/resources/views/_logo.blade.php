@php
  $resource = $resource ?? $repository ?? null;
  $repoName = $resource->authorized_form_of_name ?? $resource->name ?? '[Untitled]';
  $repoUrl = route('repository.show', ['slug' => $resource->slug]);
  $logoPath = $resource->logo_path ?? null;
  $hasLogo = !empty($logoPath);
@endphp

@if($hasLogo)
  <div class="repository-logo mb-3 mx-auto">
    <a class="text-decoration-none" href="{{ $repoUrl }}">
      <img
        src="{{ $logoPath }}"
        alt="{{ __('Go to :name', ['name' => \Illuminate\Support\Str::limit($repoName, 100)]) }}"
        class="img-fluid img-thumbnail border-4 shadow-sm bg-white">
    </a>
  </div>
@else
  <div class="repository-logo-text mb-3">
    <a class="text-decoration-none" href="{{ $repoUrl }}">
      <h2 class="h4 p-2 text-muted text-start border border-4 shadow-sm bg-white mx-auto">
        {{ $repoName }}
      </h2>
    </a>
  </div>
@endif
