@extends('theme::layouts.1col')

@section('title')
  <h1>Are you sure you want to delete {{ $accession->title ?: $accession->identifier ?: '[Untitled]' }}?</h1>
@endsection

@section('content')

  <form method="POST" action="{{ route('accession.destroy', $accession->slug) }}">
    @csrf
    @method('DELETE')

    @if((isset($deaccessions) && count($deaccessions) > 0) || (isset($accruals) && count($accruals) > 0))
      <div id="content" class="p-3">

        @if(isset($deaccessions) && count($deaccessions) > 0)
          {{ __('It has :count deaccessions that will also be deleted:', ['count' => count($deaccessions)]) }}
          <ul class="mb-0">
            @foreach($deaccessions as $item)
              <li><a href="{{ url('/deaccession/' . $item->slug) }}">{{ $item->identifier ?: $item->description ?: '[Untitled]' }}</a></li>
            @endforeach
          </ul>
        @endif

        @if(isset($accruals) && count($accruals) > 0)
          <div class="mt-3">
            {{ __('It has :count accruals. They will not be deleted.', ['count' => count($accruals)]) }}
            <ul class="mb-0">
              @foreach($accruals as $item)
                <li><a href="{{ route('accession.show', $item->slug) }}">{{ $item->title ?: $item->identifier ?: '[Untitled]' }}</a></li>
              @endforeach
            </ul>
          </div>
        @endif

      </div>
    @endif

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('accession.show', $accession->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
      <li><input class="btn atom-btn-outline-danger" type="submit" value="Delete"></li>
    </ul>
  </form>

@endsection
