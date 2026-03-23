<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>3D Model Viewer</title>
  <script type="module" src="https://unpkg.com/@google/model-viewer/dist/model-viewer.min.js"></script>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { width: 100%; height: 100%; overflow: hidden; }
    model-viewer { width: 100%; height: 100%; --poster-color: transparent; }
    .hotspot {
      display: block; width: 20px; height: 20px; border-radius: 50%;
      border: 2px solid white; background-color: var(--hotspot-color, #1a73e8);
      box-shadow: 0 2px 4px rgba(0,0,0,0.3); cursor: pointer; transition: transform 0.2s;
    }
    .hotspot:hover { transform: scale(1.2); }
    .hotspot-annotation {
      display: none; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%);
      background: white; padding: 8px 12px; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);
      min-width: 120px; max-width: 200px; font-size: 13px; z-index: 100; margin-bottom: 8px;
    }
    .hotspot:hover .hotspot-annotation, .hotspot:focus .hotspot-annotation { display: block; }
    .hotspot-annotation strong { display: block; margin-bottom: 3px; color: #333; }
    .hotspot-annotation p { margin: 0; color: #666; font-size: 12px; }
    .ar-button {
      position: absolute; bottom: 16px; left: 16px; padding: 8px 16px; border: none; border-radius: 8px;
      background: #1a73e8; color: white; font-weight: 500; font-size: 14px; cursor: pointer;
    }
    .progress-bar {
      display: block; position: absolute; bottom: 0; left: 0; right: 0; height: 3px;
      background: rgba(255,255,255,0.2);
    }
    .progress-bar .update-bar { height: 100%; background: #1a73e8; }
    .error-message {
      display: flex; align-items: center; justify-content: center; height: 100%;
      color: #721c24; background: #f8d7da; padding: 20px; text-align: center;
    }
  </style>
</head>
<body>
@if(!$model)
  <div class="error-message"><p>Model not found</p></div>
@else
  <model-viewer
    id="embed-viewer"
    src="/uploads/{{ $model->file_path }}"
    @if(!empty($model->poster_image)) poster="/uploads/{{ $model->poster_image }}" @endif
    alt="{{ e($model->alt_text ?: ($model->model_title ?: '3D Model')) }}"
    camera-controls
    touch-action="pan-y"
    @if(!empty($model->ar_enabled)) ar ar-modes="webxr scene-viewer quick-look" @endif
    @if(!empty($model->auto_rotate)) auto-rotate @endif
    rotation-per-second="{{ $model->rotation_speed ?? 30 }}deg"
    camera-orbit="{{ $model->camera_orbit ?? '0deg 75deg 105%' }}"
    field-of-view="{{ $model->field_of_view ?? '30deg' }}"
    exposure="{{ $model->exposure ?? 1 }}"
    shadow-intensity="{{ $model->shadow_intensity ?? 1 }}"
    @if(!empty($model->environment_image)) environment-image="/uploads/{{ $model->environment_image }}" @endif
    style="background-color: {{ $model->background_color ?? '#f5f5f5' }};"
  >
    @foreach($hotspots ?? [] as $hotspot)
      <button class="hotspot"
              slot="hotspot-{{ $hotspot->id }}"
              data-position="{{ $hotspot->position_x }}m {{ $hotspot->position_y }}m {{ $hotspot->position_z }}m"
              data-normal="{{ $hotspot->normal_x }}m {{ $hotspot->normal_y }}m {{ $hotspot->normal_z }}m"
              style="--hotspot-color: {{ $hotspot->color }};"
              @if(!empty($hotspot->link_url)) data-link="{{ $hotspot->link_url }}" data-target="{{ $hotspot->link_target ?? '_blank' }}" @endif>
        <div class="hotspot-annotation">
          @if(!empty($hotspot->hotspot_title))
            <strong>{{ e($hotspot->hotspot_title) }}</strong>
          @endif
          @if(!empty($hotspot->hotspot_description))
            <p>{{ e($hotspot->hotspot_description) }}</p>
          @endif
        </div>
      </button>
    @endforeach

    @if(!empty($model->ar_enabled))
      <button slot="ar-button" class="ar-button">View in AR</button>
    @endif

    <div class="progress-bar" slot="progress-bar">
      <div class="update-bar"></div>
    </div>
  </model-viewer>

  <script>
    document.querySelectorAll('.hotspot[data-link]').forEach(function(hotspot) {
      hotspot.addEventListener('click', function(e) {
        e.preventDefault();
        var url = this.dataset.link;
        var target = this.dataset.target || '_blank';
        if (url) {
          window.parent.postMessage({type: 'hotspot-link', url: url, target: target}, '*');
        }
      });
    });
  </script>
@endif
</body>
</html>
