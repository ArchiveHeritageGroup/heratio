@extends('ahg-theme-b5::layout')

@section('title', 'Inventory list - ' . ($io->title ?? 'Untitled'))

@section('content')
<div class="container-fluid py-3">

  <div class="d-flex flex-wrap gap-3 mb-3">
    <div class="multiline-header d-inline-flex align-items-center me-2">
      <i class="fas fa-3x fa-list-alt me-3" aria-hidden="true"></i>
      <div class="d-flex flex-column">
        <h1 class="mb-0">{{ $io->title ?? 'Untitled' }}</h1>
        <span class="small text-muted">Inventory list</span>
      </div>
    </div>
    <div class="ms-auto">
      <a href="{{ route('informationobject.show', $io->slug) }}" class="btn btn-sm atom-btn-white text-wrap">Return to archival description</a>
    </div>
  </div>

  {{-- Breadcrumbs --}}
  @if(!empty($breadcrumbs))
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb">
        @foreach($breadcrumbs as $crumb)
          <li class="breadcrumb-item">
            <a href="{{ route('informationobject.show', $crumb->slug) }}">{{ $crumb->title ?? 'Untitled' }}</a>
          </li>
        @endforeach
        <li class="breadcrumb-item active" aria-current="page">{{ $io->title ?? 'Untitled' }}</li>
      </ol>
    </nav>
  @endif

  @if($items->isNotEmpty())
    <div class="table-responsive mb-3">
      <table class="table table-bordered mb-0">
        <thead>
          <tr class="text-nowrap">
            <th width="14%">
              <a href="{{ route('informationobject.inventory', ['slug' => $io->slug, 'sort' => 'identifier']) }}">Identifier</a>
            </th>
            <th width="40%">
              <a href="{{ route('informationobject.inventory', ['slug' => $io->slug, 'sort' => 'title']) }}">Title</a>
            </th>
            <th width="14%">
              <a href="{{ route('informationobject.inventory', ['slug' => $io->slug, 'sort' => 'level']) }}">Level of description</a>
            </th>
            <th width="24%">Date</th>
            <th width="8%">Digital object</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($items as $item)
            <tr>
              <td>{{ $item->identifier }}</td>
              <td>
                <a href="{{ route('informationobject.show', $item->slug) }}">{{ $item->title ?? 'Untitled' }}</a>
              </td>
              <td>{{ $levelNames[$item->level_of_description_id] ?? '' }}</td>
              <td>{{ $itemDates[$item->id] ?? '' }}</td>
              <td>
                @if(isset($hasDigitalObject[$item->id]))
                  <a href="{{ route('informationobject.show', $item->slug) }}" class="btn btn-sm atom-btn-white">View</a>
                @endif
              </td>
              <td>
                @include('ahg-core::clipboard._button', ['slug' => $item->slug, 'type' => 'informationObject', 'wide' => true])
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- Pagination --}}
    @if($total > $perPage)
      <nav aria-label="Inventory pagination">
        <ul class="pagination justify-content-center">
          @php
            $totalPages = ceil($total / $perPage);
          @endphp
          @if($page > 1)
            <li class="page-item">
              <a class="page-link" href="{{ route('informationobject.inventory', ['slug' => $io->slug, 'page' => $page - 1, 'sort' => request('sort')]) }}">Previous</a>
            </li>
          @endif
          @for($p = 1; $p <= $totalPages; $p++)
            <li class="page-item {{ $p === $page ? 'active' : '' }}">
              <a class="page-link" href="{{ route('informationobject.inventory', ['slug' => $io->slug, 'page' => $p, 'sort' => request('sort')]) }}">{{ $p }}</a>
            </li>
          @endfor
          @if($page < $totalPages)
            <li class="page-item">
              <a class="page-link" href="{{ route('informationobject.inventory', ['slug' => $io->slug, 'page' => $page + 1, 'sort' => request('sort')]) }}">Next</a>
            </li>
          @endif
        </ul>
      </nav>
    @endif

  @else
    <div class="p-3">
      We couldn't find any results matching your search.
    </div>
  @endif

</div>
@endsection
