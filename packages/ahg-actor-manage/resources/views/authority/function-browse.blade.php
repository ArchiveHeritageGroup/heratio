@extends('theme::layouts.1col')

@section('title', 'Browse by Function')
@section('body-class', 'authority function-browse')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item">
      <a href="{{ route('actor.dashboard') }}">Authority Dashboard</a>
    </li>
    <li class="breadcrumb-item active">Functions Browse</li>
  </ol>
</nav>

<h1 class="mb-4"><i class="fas fa-sitemap me-2"></i>Browse by Function</h1>

<div class="card">
  <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
    <i class="fas fa-sitemap me-1"></i>ISDF Functions
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Function</th>
          <th class="text-center">Linked Actors</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @if (empty($functions))
          <tr><td colspan="3" class="text-center text-muted py-3">No ISDF functions found.</td></tr>
        @else
          @foreach ($functions as $func)
            <tr>
              <td>
                @if ($func->slug)
                  <a href="{{ url('/' . $func->slug) }}">{{ e($func->title) }}</a>
                @else
                  {{ e($func->title) }}
                @endif
              </td>
              <td class="text-center">
                <span class="badge bg-secondary">{{ $func->actor_count }}</span>
              </td>
              <td>
                @if ($func->slug)
                  <a href="{{ url('/' . $func->slug) }}" class="btn btn-sm atom-btn-white">
                    <i class="fas fa-eye"></i>
                  </a>
                @endif
              </td>
            </tr>
          @endforeach
        @endif
      </tbody>
    </table>
  </div>
</div>

@endsection
