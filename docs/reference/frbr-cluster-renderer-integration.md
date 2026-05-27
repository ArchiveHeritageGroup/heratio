# FRBR Cluster Renderer - Integration Snippet for ahg-display

**Issue:** heratio#763 (final acceptance criterion)
**Status:** Ready-to-paste snippet. Requires `./bin/unlock packages/ahg-display/` before applying.

## Background

The ES `heratio_qubitinformationobject` index now carries a `workKey` keyword field on every `library_item` doc (shipped v1.113+). The remaining FRBR clustering acceptance criterion is to collapse multiple ES hits sharing the same `workKey` into one result row in the GLAM browse hit-list, with a "View all editions" expander.

The hit-list renderer lives in the locked `packages/ahg-display/` tree, so this doc captures the exact code change ready for paste-on-unlock.

## Step 1: ES query - add a terms aggregation on workKey

In the search query builder (`packages/ahg-display/src/Services/GlamSearchService.php` or equivalent), augment the query body to include an aggregation that groups by `workKey`:

```php
$body['aggs'] = array_merge($body['aggs'] ?? [], [
    'work_clusters' => [
        'terms' => [
            'field' => 'workKey',
            'size'  => 200,           // up to 200 work-clusters per page
            'order' => ['_count' => 'desc'],
        ],
        'aggs' => [
            'representative' => [
                'top_hits' => [
                    'size'    => 1,
                    'sort'    => [['_score' => 'desc']],
                    '_source' => true,
                ],
            ],
            'edition_count' => ['value_count' => ['field' => '_id']],
        ],
    ],
]);
```

This costs one extra aggregation pass per query; on the 132k-row target catalogue it should add <50ms at 99th percentile.

## Step 2: Renderer - pick one representative per workKey

In the result iterator (`packages/ahg-display/resources/views/browse/_card.blade.php` or `_list.blade.php`), suppress non-representative rows in favour of the representative + an expander:

```php
@php
    // Build a set of representative IO IDs from the work_clusters aggregation.
    $clusterMap = [];
    foreach (($search->aggregations['work_clusters']['buckets'] ?? []) as $b) {
        $key = $b['key'];
        $repHit = $b['representative']['hits']['hits'][0]['_id'] ?? null;
        if ($repHit !== null) {
            $clusterMap[$key] = [
                'representative_id' => (int) $repHit,
                'count'             => (int) ($b['edition_count']['value'] ?? 1),
            ];
        }
    }
@endphp

@foreach ($hits as $hit)
    @php
        $workKey = $hit['_source']['workKey'] ?? null;
        $cluster = $workKey ? ($clusterMap[$workKey] ?? null) : null;
        $isRepresentative = !$cluster || $cluster['representative_id'] === (int) $hit['_id'];
    @endphp

    @if ($isRepresentative)
        {{-- ...existing card / list markup here... --}}

        @if ($cluster && $cluster['count'] > 1)
            <div class="mt-2">
                <a href="{{ route('library.work-cluster.show', $workKey) }}"
                   class="small text-muted">
                    <i class="bi bi-collection"></i>
                    {{ __('View all :count editions', ['count' => $cluster['count']]) }}
                </a>
            </div>
        @endif
    @endif
@endforeach
```

## Step 3: Expander route + view (unlocked, can ship now)

The expander page lives in the FRBR package (already unlocked). Add this to `packages/ahg-biblio-frbr/routes/web.php`:

```php
Route::get('/library/work-cluster/{workKey}', [\AhgBiblioFrbr\Controllers\WorkClusterController::class, 'show'])
    ->name('library.work-cluster.show')
    ->where('workKey', '[a-f0-9]{16}');
```

And the controller method (returns all manifestations sharing a workKey):

```php
public function show(string $workKey)
{
    $items = DB::table('library_item')
        ->where('work_key', $workKey)
        ->join('information_object', 'information_object.id', '=', 'library_item.information_object_id')
        ->leftJoin('information_object_i18n', function ($j) {
            $j->on('information_object_i18n.id', '=', 'information_object.id')
              ->where('information_object_i18n.culture', '=', app()->getLocale());
        })
        ->select(
            'library_item.id', 'library_item.isbn', 'library_item.publication_date',
            'library_item.publisher', 'library_item.edition', 'library_item.language',
            'information_object_i18n.title', 'information_object.slug'
        )
        ->orderBy('library_item.publication_date')
        ->get();

    if ($items->isEmpty()) abort(404);
    return view('ahg-biblio-frbr::work-cluster', compact('workKey', 'items'));
}
```

## Step 4: Benchmark

After unlock + paste:

```bash
# Reindex (already done as part of v1.113+ ship):
sudo -u www-data php artisan ahg:es-reindex --index=informationobject

# Backfill any newly imported items:
sudo -u www-data php artisan ahg:frbr-backfill-work-keys

# Benchmark: capture the GLAM browse query timing on a populated catalogue
ab -n 100 -c 5 'https://heratio.theahg.co.za/glam/browse'
```

Target is 132k IOs clustering in <500ms per query. The terms aggregation + top_hits hot path should hit that on Elasticsearch 8 with reasonable hardware.

## Why this doc exists separately

The work-key engine, override admin, backfill command, and ES indexer changes all ship in unlocked packages. Only the final search-results renderer hookup is in locked code. This doc keeps the snippet ready so a 5-minute unlock sweep finishes the loop without re-discovery.
