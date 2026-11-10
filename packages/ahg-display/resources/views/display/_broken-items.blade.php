@if(!empty($brokenItems))
  <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
    <h6 class="alert-heading mb-2"><i class="fas fa-exclamation-triangle me-1"></i> {{ trans_choice('{1} Data integrity issues detected (:count record)|[2,*] Data integrity issues detected (:count records)', count($brokenItems), ['count' => count($brokenItems)]) }} <small class="text-muted fw-normal">{{ __('(Administrator view only)') }}</small></h6>
    <p class="small mb-2">{!! __('The following records have no slug and cannot be linked. They are excluded from browse results. Run :command to fix, or delete if they are test data.', ['command' => '<code>php artisan ahg:generate-slugs</code>']) !!}</p>
    <table class="table table-sm table-bordered mb-0 small bg-white">
      <thead><tr><th>{{ __('ID') }}</th><th>{{ __('Title') }}</th><th>{{ __('Identifier') }}</th><th>{{ __('Level') }}</th><th>{{ __('Type') }}</th><th>{{ __('Issue') }}</th><th>{{ __('Action') }}</th></tr></thead>
      <tbody>
        @foreach($brokenItems as $broken)
        <tr>
          <td><code>{{ $broken->id }}</code></td>
          <td>{{ $broken->title ?? '[Untitled]' }}</td>
          <td>{{ $broken->identifier ?? '-' }}</td>
          <td>{{ $broken->level_name ?? '-' }}</td>
          <td>{{ ucfirst($broken->object_type ?? '?') }}</td>
          <td><span class="text-danger">{{ __('Missing slug') }}</span></td>
          <td>
            <form method="POST" action="{{ route('admin.fix-missing-slug') }}" class="d-inline">
              @csrf
              <input type="hidden" name="object_id" value="{{ $broken->id }}">
              <button type="submit" class="btn btn-sm atom-btn-outline-success py-0 px-1"><i class="fas fa-link me-1"></i>{{ __('Fix') }}</button>
            </form>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
  </div>
@endif
