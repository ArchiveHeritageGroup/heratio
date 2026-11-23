@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if (session('error'))
  <div class="alert alert-danger">
    <div class="fw-semibold">{{ session('error') }}</div>
    @if (session('release_failures'))<ul class="mb-0 mt-2">@foreach (session('release_failures') as $f)<li>{{ $f }}</li>@endforeach</ul>@endif
  </div>
@endif
@if ($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
