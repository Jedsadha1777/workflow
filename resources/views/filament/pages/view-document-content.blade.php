@php
    $record = $getRecord();
    $content = $record->content;
    if (is_string($content)) {
        $content = json_decode($content, true);
    }
    
    $sheets = $content['sheets'] ?? [];
    $formData = $record->form_data ?? [];
    $formId = 'doc_view_' . $record->id . '_' . uniqid();
@endphp

<style>
.zoom-controls {
    margin-left: auto;
    display: flex;
    gap: 8px;
    align-items: center;
}
.zoom-btn {
    width: 32px;
    height: 32px;
    border: 1px solid #d1d5db;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
    color: #374151;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.zoom-btn:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
}
.zoom-level {
    font-size: 14px;
    color: #6b7280;
    min-width: 50px;
    text-align: center;
}
.preview-zoom-wrapper {
    transform-origin: top left;
    transition: transform 0.2s;
}
</style>

@foreach($sheets as $index => $sheet)
    @php
        $sheetId = $formId . '_sheet_' . $index;
        $sheetHtml = $sheet['html'];
        $sheetName = $sheet['name'];
        
        // แทนค่า form data
        if (!empty($formData) && isset($formData[$sheetName])) {
            foreach ($formData[$sheetName] as $cell => $value) {
                if (is_array($value) && isset($value['type']) && $value['type'] === 'signature') {
                    $approver = \App\Models\User::find($value['approver_id']);
                    $signatureHtml = $approver ? 
                        '<div style="border:2px solid #10b981;padding:10px;text-align:center;background:#f0fdf4;border-radius:6px;">' .
                        '<div style="font-weight:bold;color:#065f46;">✓ ' . htmlspecialchars($approver->name) . '</div>' .
                        '<div style="font-size:11px;color:#6b7280;margin-top:4px;">Signed: ' . date('Y-m-d H:i', strtotime($value['signed_at'])) . '</div>' .
                        '</div>' : '';
                    
                    $sheetHtml = preg_replace(
                        '/<td([^>]*data-cell="' . preg_quote($sheetName . ':' . $cell, '/') . '"[^>]*)>.*?<\/td>/s',
                        '<td$1>' . $signatureHtml . '</td>',
                        $sheetHtml
                    );
                } elseif (is_array($value) && isset($value['type']) && $value['type'] === 'date') {
                    $dateHtml = '<div style="padding:4px;"><strong>' . htmlspecialchars($value['date']) . '</strong></div>';
                    
                    $sheetHtml = preg_replace(
                        '/<td([^>]*data-cell="' . preg_quote($sheetName . ':' . $cell, '/') . '"[^>]*)>.*?<\/td>/s',
                        '<td$1>' . $dateHtml . '</td>',
                        $sheetHtml
                    );
                } else {
                    $escapedValue = htmlspecialchars($value);
                    $sheetHtml = preg_replace_callback(
                        '/<td([^>]*data-cell="' . preg_quote($sheetName . ':' . $cell, '/') . '"[^>]*)>.*?<\/td>/s',
                        function($matches) use ($escapedValue) {
                            return '<td' . $matches[1] . '><div style="padding:4px;"><strong>' . $escapedValue . '</strong></div></td>';
                        },
                        $sheetHtml
                    );
                }
            }
        }
    @endphp
    
    <div class="  bg-white mb-4" wire:ignore>
        <div style="display: flex; align-items: center; margin-bottom: 12px;">
            <h4 class="font-semibold" style="margin: 0; flex: 1;">{{ $sheet['name'] }}</h4>
            
            <div class="zoom-controls" data-sheet-id="{{ $sheetId }}">
                <button type="button" class="zoom-btn" data-zoom-action="out">−</button>
                <span class="zoom-level" id="zoom-level-{{ $sheetId }}">100%</span>
                <button type="button" class="zoom-btn" data-zoom-action="in">+</button>
                <button type="button" class="zoom-btn" data-zoom-action="reset" style="font-size: 18px;">⟲</button>
            </div>
        </div>
        
        <div style="overflow: auto; border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px; background: #fff;">
            <div id="{{ $sheetId }}" class="preview-zoom-wrapper">
                {!! $sheetHtml !!}
            </div>
        </div>
    </div>
@endforeach


<script>
window.sheetZoomLevels ||= {};

document.addEventListener('DOMContentLoaded', () =>
    setTimeout(() =>
        document.querySelectorAll('.zoom-controls[data-sheet-id]').forEach(c => {
            const id = c.dataset.sheetId,
                  w  = document.getElementById(id),
                  t  = w?.querySelector('table');
            if (!w) return;

            window.sheetZoomLevels[id] = 1;
            w.style.transformOrigin = 'top left';
            w.style.transform = 'scale(1)';
            if (!t) return;

            w.style.width = w.style.height = 'auto';
            w.offsetWidth;

            const width =
                [...t.querySelectorAll('colgroup col')]
                    .reduce((s, c) => s + (parseFloat(c.style.width || c.getAttribute('width')) || 0), 0)
                || t.offsetWidth;

            w.style.width = width + 'px';
            w.style.height = t.offsetHeight + 'px';
            t.style.width = t.style.minWidth = width + 'px';

            const z = document.getElementById(`zoom-level-${id}`);
            if (z) z.textContent = '100%';
        })
    , 100)
);
</script>