{{-- Print Call Slips - Migrated from AtoM --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Call Slips - Print</title>
    <style>
        @media print { body { margin: 0; padding: 0; } .no-print { display: none !important; } @page { margin: 10mm; } }
        body { font-family: Arial, sans-serif; font-size: 10pt; line-height: 1.4; }
        .print-controls { position: fixed; top: 10px; right: 10px; z-index: 1000; background: #fff; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .print-controls button { padding: 8px 16px; margin: 0 5px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="print-controls no-print">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </div>
    {!! $html ?? '<p>No call slips to print.</p>' !!}
</body>
</html>