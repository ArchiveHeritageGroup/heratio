@php
  // Build static pages sidebar from DB
  $culture = app()->getLocale();
  $staticPages = \Illuminate\Support\Facades\DB::table('static_page')
      ->join('static_page_i18n', 'static_page.id', '=', 'static_page_i18n.id')
      ->join('slug', 'static_page.id', '=', 'slug.object_id')
      ->where('static_page_i18n.culture', $culture)
      ->whereNotNull('static_page_i18n.title')
      ->select('static_page.id', 'static_page_i18n.title', 'slug.slug')
      ->orderBy('static_page_i18n.title')
      ->get();
@endphp

@if($staticPages->count())
  <section class="card mb-3">
    <h2 class="h5 p-3 mb-0">
      {{ __('Static pages') }}
    </h2>
    <div class="list-group list-group-flush">
      @foreach($staticPages as $sp)
        @php
          $href = in_array($sp->slug, ['about', 'contact', 'privacy', 'terms'])
              ? url('/' . $sp->slug)
              : url('/pages/' . $sp->slug);
        @endphp
        <a
          class="list-group-item list-group-item-action{{ request()->is($sp->slug) || request()->is('pages/' . $sp->slug) ? ' active' : '' }}"
          href="{{ $href }}">
          {{ $sp->title }}
        </a>
      @endforeach
    </div>
  </section>
@endif
