<!DOCTYPE html>
<html lang="{{ $themeData['culture'] ?? 'en' }}" dir="ltr">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $themeData['siteTitle'] ?? 'Heratio') - Print</title>
    <style>
      /* Base typography */
      body {
        font-family: "Times New Roman", Times, serif;
        font-size: 12pt;
        line-height: 1.5;
        color: #000;
        background: #fff;
        margin: 0;
        padding: 20px 40px;
      }

      /* Print header */
      .print-header {
        border-bottom: 2px solid #333;
        padding-bottom: 10px;
        margin-bottom: 20px;
      }
      .print-header h1.site-title {
        font-size: 10pt;
        color: #666;
        margin: 0 0 4px 0;
        font-weight: normal;
      }
      .print-header h2.record-title {
        font-size: 16pt;
        margin: 0;
        color: #000;
      }
      .print-header .record-type {
        font-size: 10pt;
        color: #666;
        margin-top: 2px;
      }

      /* Section headings */
      h2.section-heading {
        font-size: 13pt;
        font-weight: bold;
        border-bottom: 1px solid #999;
        padding-bottom: 4px;
        margin-top: 18px;
        margin-bottom: 8px;
        page-break-after: avoid;
      }

      /* Field rows */
      .field-row {
        display: table;
        width: 100%;
        border-bottom: 1px solid #eee;
        page-break-inside: avoid;
      }
      .field-label {
        display: table-cell;
        width: 30%;
        padding: 4px 8px 4px 0;
        font-weight: bold;
        font-size: 10pt;
        vertical-align: top;
        color: #333;
      }
      .field-value {
        display: table-cell;
        width: 70%;
        padding: 4px 0;
        vertical-align: top;
      }

      /* Tables */
      table {
        width: 100%;
        border-collapse: collapse;
        margin: 8px 0;
        font-size: 11pt;
      }
      table th, table td {
        border: 1px solid #ccc;
        padding: 4px 8px;
        text-align: left;
        vertical-align: top;
      }
      table th {
        background-color: #f0f0f0;
        font-weight: bold;
      }

      /* Lists */
      ul, ol {
        margin: 0 0 0 1.5em;
        padding: 0;
      }
      li { margin-bottom: 2px; }

      /* Contact cards */
      .contact-block {
        border: 1px solid #ccc;
        padding: 8px;
        margin-bottom: 8px;
        page-break-inside: avoid;
      }

      /* Links - show as plain text in print */
      a { color: #000; text-decoration: none; }

      /* Print footer */
      .print-footer {
        margin-top: 30px;
        padding-top: 10px;
        border-top: 1px solid #999;
        font-size: 9pt;
        color: #666;
      }

      /* Print toolbar - hidden in actual print */
      .print-toolbar {
        background: #f5f5f5;
        border-bottom: 1px solid #ccc;
        padding: 10px 20px;
        margin: -20px -40px 20px -40px;
      }
      .print-toolbar button {
        background: #333;
        color: #fff;
        border: none;
        padding: 8px 20px;
        cursor: pointer;
        margin-right: 8px;
        font-size: 11pt;
      }
      .print-toolbar button:hover {
        background: #555;
      }

      /* Page break helpers */
      .page-break-before { page-break-before: always; }
      .page-break-after { page-break-after: always; }
      .no-break { page-break-inside: avoid; }

      /* Badge for print */
      .print-badge {
        display: inline-block;
        padding: 1px 6px;
        border: 1px solid #999;
        font-size: 9pt;
        margin-left: 4px;
      }

      @media print {
        .print-toolbar { display: none !important; }
        body { padding: 0; margin: 0; }
        .print-header { margin-top: 0; }
        @page {
          margin: 2cm;
          size: A4;
        }
      }

      @media screen {
        body { max-width: 900px; margin: 0 auto; padding: 20px 40px; }
      }
    </style>
  </head>
  <body>

    <div class="print-toolbar">
      <button onclick="window.print()">{{ __('Print this page') }}</button>
      <button onclick="window.close()">{{ __('Close') }}</button>
    </div>

    <div class="print-header">
      <h1 class="site-title">{{ $themeData['siteTitle'] ?? 'Heratio' }}</h1>
      <h2 class="record-title">@yield('record-title')</h2>
      @hasSection('record-type')
        <div class="record-type">@yield('record-type')</div>
      @endif
    </div>

    @yield('content')

    <div class="print-footer">
      <div style="float: right;">Page generated: {{ now()->format('Y-m-d H:i:s') }}</div>
      <div>Printed from {{ $themeData['siteTitle'] ?? 'Heratio' }} &mdash; {{ url()->current() }}</div>
    </div>

  </body>
</html>
