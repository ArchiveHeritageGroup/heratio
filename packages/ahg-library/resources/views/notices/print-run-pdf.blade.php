<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 25mm 22mm; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 11pt; line-height: 1.45; color: #262626; }
    .notice { page-break-after: always; }
    .notice:last-child { page-break-after: auto; }
    .address { margin-bottom: 14mm; min-height: 25mm; }
    h1 { font-size: 13pt; margin: 0 0 6mm; }
</style>
</head>
<body>
@foreach($items as $item)
    <div class="notice">
        <div class="address">
            <strong>{{ $item->recipient_name }}</strong><br>
            @if($item->postal_address){!! nl2br(e($item->postal_address)) !!}@else{{ __('For collection at the counter') }}@endif
        </div>
        <h1>{{ $item->subject }}</h1>
        <div>{!! nl2br(e($item->body)) !!}</div>
        @if(!empty($item->created_at))
            <p style="font-size: 9pt; color: #555;">{{ __('Days overdue and fine as calculated on :date.', ['date' => substr((string) $item->created_at, 0, 10)]) }}</p>
        @endif
    </div>
@endforeach
</body>
</html>
