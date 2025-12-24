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
            width: auto;
            display: flex;
            justify-content: center;
        }
        .sheet-scale {
            display: inline-block;
        }
        table {
            width: 100%;
        }
    </style>
</head>
<body>
    @foreach($sheets as $sheet)
        <div class="page">
            <div class="sheet-wrapper">
                <div class="sheet-scale">
                    {!! $sheet['html'] !!}
                </div>
            </div>
        </div>
    @endforeach
</body>
</html>