{{--
  Exhibition object-list CSV export.
  Vars: $objects (iterable of arrays/objects with keys: object_number,
        object_title, section_name, display_location, insurance_value,
        condition_status, display_notes).

  Usage: render this partial inside a controller method that has already
  set Content-Type: text/csv and a Content-Disposition attachment header.

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
--}}
@php
    $cell = fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"';
@endphp
Object Number,Title,Section,Display Location,Insurance Value,Condition,Notes
@foreach ($objects ?? [] as $obj)
@php $o = is_array($obj) ? (object) $obj : $obj; @endphp
{{ $cell($o->object_number ?? '') }},{{ $cell($o->object_title ?? '') }},{{ $cell($o->section_name ?? '') }},{{ $cell($o->display_location ?? '') }},{{ $o->insurance_value ?? '' }},{{ $cell($o->condition_status ?? '') }},{{ $cell($o->display_notes ?? '') }}
@endforeach
