{{-- Partial: Repository table view --}}
@props(['repositories' => collect()])
<div class="table-responsive"><table class="table table-bordered table-hover mb-0">
  <thead><tr style="background:var(--ahg-primary);color:#fff"><th>#</th><th>Name</th><th>Type</th><th>Location</th><th>Holdings</th><th>Actions</th></tr></thead>
  <tbody>@foreach($repositories as $i => $repo)<tr>
    <td>{{ $i + 1 }}</td><td><a href="{{ route('repository.show', $repo->slug ?? '') }}">{{ $repo->authorized_form_of_name ?? '' }}</a></td>
    <td>{{ $repo->type ?? '-' }}</td><td>{{ $repo->city ?? '-' }}</td><td>{{ $repo->holdings_count ?? 0 }}</td>
    <td><a href="{{ route('repository.show', $repo->slug ?? '') }}" class="btn btn-sm atom-btn-white"><i class="fas fa-eye"></i></a></td>
  </tr>@endforeach</tbody>
</table></div>
