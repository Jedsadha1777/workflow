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
        
        @if($orientation === 'fit')
        html, body {
            width: fit-content !important;
            height: fit-content !important;
            overflow: visible !important;
        }
        
        /* ซ่อน CSS text ที่หลุดมา */
        body > :not(table):not(div) {
            display: none !important;
        }
        @endif
        
        @media print {
            html, body {
                @if($orientation === 'fit')
                width: auto;
                height: auto;
                overflow: visible !important;
                @elseif($orientation === 'landscape')
                width: 297mm;
                height: 210mm;
                @else
                width: 210mm;
                height: 297mm;
                @endif
            }
            
            @if($orientation === 'fit')
            /* ซ่อน CSS text ตอน print */
            body > :not(table):not(div) {
                display: none !important;
            }
            @endif
        }
        
        body {
            font-family: Arial, sans-serif;
            @if($orientation === 'fit')
            padding: 0;
            margin: 0;
            @else
            padding: 20px;
            @endif
        }
        
        body > table {
            margin: 0 auto;
        }
        
        table {
            @if($orientation !== 'fit')
            table-layout: fixed;
            @endif
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