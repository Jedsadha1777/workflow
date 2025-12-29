<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            @if($orientation === 'fit')
            size: auto;
            margin: 0;
            @else
            size: A4 {{ $orientation ?? 'portrait' }};
            margin: 0;
            @endif
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        @media print {
            html, body {
                @if($orientation === 'fit')
                width: auto;
                height: auto;
                @elseif($orientation === 'landscape')
                width: 297mm;
                height: 210mm;
                @else
                width: 210mm;
                height: 297mm;
                @endif
            }
            
            @if($orientation === 'fit')
            /* ปิด page break ทั้งหมดใน fit mode */
            * {
                page-break-before: avoid !important;
                page-break-after: avoid !important;
                page-break-inside: avoid !important;
            }
            @endif
        }
        
        body {
            font-family: Arial, sans-serif;
            @if($orientation === 'fit')
            padding: 0;
            position: relative;
            @else
            padding: 20px;
            @endif
        }
        
        @if($orientation === 'fit')
        body > * {
            margin: 0 auto;
        }
        @endif
        
        table {
            table-layout: fixed;
        }
    </style>
</head>
<body>
    @foreach($sheets as $sheet)
        @if($loop->index > 0 && $orientation !== 'fit')
        <div style="page-break-before: always;"></div>
        @endif
        {!! $sheet['html'] !!}
    @endforeach
</body>
</html>