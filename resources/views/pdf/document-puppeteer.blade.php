<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: A4 {{ $orientation ?? 'portrait' }};
            margin: 5mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
        }
        .page {
            page-break-before: always;
        }
        .page:first-child {
            page-break-before: avoid;
        }
        .sheet-wrapper {
            margin-left: {{ $offsetX ?? 0 }}px;
        }
    </style>
</head>
<body>
    @foreach($sheets as $sheet)
        <div class="page">
            <div class="sheet-wrapper">
                {!! $sheet['html'] !!}
            </div>
        </div>
    @endforeach
</body>
</html>