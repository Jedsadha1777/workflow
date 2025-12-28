<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: A4 {{ $orientation ?? 'portrait' }};
            margin: 0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        @media print {
            html, body {
                width: {{ $orientation === 'landscape' ? '297mm' : '210mm' }};
                height: {{ $orientation === 'landscape' ? '210mm' : '297mm' }};
            }
        }
        
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        
        table {
            table-layout: fixed;
        }
    </style>
</head>
<body>
    @foreach($sheets as $sheet)
        @if($loop->index > 0)
        <div style="page-break-before: always;"></div>
        @endif
        {!! $sheet['html'] !!}
    @endforeach
</body>
</html>