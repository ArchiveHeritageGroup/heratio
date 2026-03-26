@php
use Illuminate\Support\Facades\DB;
@endphp

<section id="popular-this-week" class="card mb-3">
  <h2 class="h5 p-3 mb-0">
    {{ __('Popular this week') }}
  </h2>
  <div class="list-group list-group-flush">
    @foreach($popularThisWeek as $item)
      @php
        $objectId = $item[0] ?? null;
        $visits = $item[1] ?? 0;
        $object = null;
        $objectType = null;
        $objectSlug = null;
        $objectTitle = '';

        if ($objectId) {
            $row = DB::table('object')
                ->leftJoin('slug', 'object.id', '=', 'slug.object_id')
                ->where('object.id', $objectId)
                ->select('object.id', 'object.class_name', 'slug.slug')
                ->first();
            if ($row) {
                $objectSlug = $row->slug;
                $objectType = $row->class_name;
                // Get title based on type
                if ($objectType === 'QubitInformationObject') {
                    $i18n = DB::table('information_object_i18n')
                        ->where('id', $objectId)->where('culture', app()->getLocale())
                        ->first();
                    if (!$i18n) {
                        $i18n = DB::table('information_object_i18n')
                            ->where('id', $objectId)->first();
                    }
                    $objectTitle = $i18n->title ?? '';
                } elseif ($objectType === 'QubitRepository' || $objectType === 'QubitActor') {
                    $ai = DB::table('actor_i18n')
                        ->where('id', $objectId)->where('culture', app()->getLocale())
                        ->first();
                    if (!$ai) {
                        $ai = DB::table('actor_i18n')
                            ->where('id', $objectId)->first();
                    }
                    $objectTitle = $ai->authorized_form_of_name ?? '';
                }
            }
        }

        if (!$objectSlug) continue;

        if ($objectType === 'QubitInformationObject') {
            $href = route('informationobject.show', $objectSlug);
        } elseif ($objectType === 'QubitRepository') {
            $href = route('repository.show', $objectSlug);
        } elseif ($objectType === 'QubitActor') {
            $href = route('actor.show', $objectSlug);
        } else {
            $href = '/' . rawurlencode($objectSlug);
        }
      @endphp
      @if($objectSlug)
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break"
           href="{{ $href }}">
          {{ $objectTitle }}
          <span class="ms-3 text-nowrap">
            {{ __('%1% visits', ['%1%' => $visits]) }}
          </span>
        </a>
      @endif
    @endforeach
  </div>
</section>
