@extends('theme::layouts.1col')

@section('title', 'Link articles — ' . $article['title'])
@section('body-class', 'help manage-links')

@section('content')
<div class="row justify-content-center">
  <div class="col-lg-8">

    <a href="{{ route('help.article', $article['slug']) }}" class="d-inline-block mb-3 small">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to article') }}
    </a>

    <h1 class="h4 mb-1">{{ __('Link articles') }}</h1>
    <p class="text-muted">{{ $article['title'] }}</p>

    @if(session('success'))
      <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="alert alert-warning py-2">{{ session('error') }}</div>
    @endif

    {{-- Add a link --}}
    <div class="card mb-4">
      <div class="card-body">
        <h2 class="h6 mb-3"><i class="fas fa-plus me-1"></i>{{ __('Add a linked article') }}</h2>
        <form action="{{ route('help.article.links.add', $article['slug']) }}" method="post">
          @csrf
          <div class="input-group">
            <input type="text" name="target" class="form-control" list="article-options"
                   placeholder="{{ __('Search a title… or paste a /help/article/… URL') }}"
                   autocomplete="off" required>
            <button class="btn atom-btn-white" type="submit">{{ __('Add & save') }}</button>
          </div>
          <datalist id="article-options">
            @foreach($allArticles as $a)
              <option value="{{ $a['title'] }}">{{ $a['category'] }}</option>
            @endforeach
          </datalist>
          <div class="form-text">{{ __('Start typing to search, pick from the list, then Add. Links are bidirectional — they appear on both articles. Repeat to add more.') }}</div>
        </form>
      </div>
    </div>

    {{-- Current links --}}
    <h2 class="h6 mb-2"><i class="fas fa-link me-1"></i>{{ __('Linked articles') }} <span class="badge bg-secondary">{{ count($related) }}</span></h2>
    @if(empty($related))
      <p class="text-muted">{{ __('No links yet. Add one above.') }}</p>
    @else
      <ul class="list-group">
        @foreach($related as $rel)
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <span>
              <a href="{{ route('help.article', $rel['slug']) }}">{{ $rel['title'] }}</a>
              <span class="text-muted small ms-2">{{ $rel['category'] }}</span>
            </span>
            <form action="{{ route('help.article.links.remove', [$article['slug'], $rel['id']]) }}" method="post" class="m-0">
              @csrf
              @method('DELETE')
              <button class="btn btn-sm btn-outline-danger" type="submit" title="{{ __('Remove') }}">
                <i class="fas fa-times"></i>
              </button>
            </form>
          </li>
        @endforeach
      </ul>
    @endif

  </div>
</div>
@endsection
