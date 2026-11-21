{{--
  heratio#1582: the shared tree-move client (drag-and-drop, the keyboard
  "Move..." picker, confirmation before leaving a fonds, undo). Include only
  for staff who may update; the routes re-check every record.
--}}
@once
<script src="{{ asset('vendor/ahg-theme-b5/js/ahg-tree-move.js') }}?v={{ @filemtime(public_path('vendor/ahg-theme-b5/js/ahg-tree-move.js')) }}"
        nonce="{{ csp_nonce() }}"
        data-move-url="{{ route('informationobject.tree-move') }}"
        data-targets-url="{{ route('informationobject.tree-move.targets') }}"
        data-children-url="{{ str_replace('/0', '/:id', route('informationobject.tree-move.children', ['id' => 0])) }}"
        data-strings="{{ json_encode([
            'title' => __('Move'),
            'move' => __('Move'),
            'cancel' => __('Cancel'),
            'close' => __('Close'),
            'searchLabel' => __('Find the new parent by title or identifier'),
            'searchHint' => __('Type at least two letters, then choose a parent from the list.'),
            'parentLegend' => __('New parent'),
            'positionLabel' => __('Position'),
            'atEnd' => __('At the end'),
            'atStart' => __('At the start'),
            'after' => __('After'),
            'inFonds' => __('In'),
            'noMatches' => __('No matches'),
            'loading' => __('Loading...'),
            'moved' => __('Moved.'),
            'undo' => __('Undo'),
            'undone' => __('Move undone.'),
            'cancelled' => __('Move cancelled.'),
            'failed' => __('Could not move this description.'),
        ]) }}"></script>
@endonce
