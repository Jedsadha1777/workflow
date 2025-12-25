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
        
        if (!empty($formData) && isset($formData[$sheetName])) {
            foreach ($formData[$sheetName] as $cell => $value) {
                

                if (is_array($value) && isset($value['type']) && $value['type'] === 'signature') {
                    $approver = \App\Models\User::find($value['approver_id']);
                    
                    if ($approver && $approver->signature_image) {
                        $signatureUrl = asset('storage/' . $approver->signature_image);
                        $signatureHtml = '<div style="text-align:center;padding:8px;">' .
                            '<img src="' . $signatureUrl . '" style="max-width:150px;max-height:60px;display:block;margin:0 auto;" alt="Signature">' .
                            '</div>';
                    } else {
                        $signatureHtml = '<div style="border:2px solid #10b981;padding:10px;text-align:center;background:#f0fdf4;border-radius:6px;">' .
                            '<div style="font-weight:bold;color:#065f46;">✓ ' . htmlspecialchars($approver ? $approver->name : 'Unknown') . '</div>' .
                            '</div>';
                    }
                    
                    $sheetHtml = preg_replace(
                        '/<td([^>]*data-cell="' . preg_quote($sheetName . ':' . $cell, '/') . '"[^>]*)>.*?<\/td>/s',
                        '<td$1>' . $signatureHtml . '</td>',
                        $sheetHtml
                    );
                } elseif (is_array($value) && isset($value['type']) && $value['type'] === 'date') {
                    $dateHtml = '<div style="padding:4px;">' . (new DateTime($value['date']))->format('d/m/Y') . '</div>';
        
                    
                    $sheetHtml = preg_replace(
                        '/<td([^>]*data-cell="' . preg_quote($sheetName . ':' . $cell, '/') . '"[^>]*)>.*?<\/td>/s',
                        '<td$1>' . $dateHtml . '</td>',
                        $sheetHtml
                    );
                } else {
                    $escapedValue = is_bool($value) ? ($value ? 'TRUE' : 'FALSE') : htmlspecialchars($value);
                    $sheetHtml = preg_replace_callback(
                        '/<td([^>]*data-cell="' . preg_quote($sheetName . ':' . $cell, '/') . '"[^>]*)>.*?<\/td>/s',
                        function($matches) use ($escapedValue) {
                             return '<td' . $matches[1] . '><div style="padding:4px;">' . $escapedValue . '</div></td>';
                        },
                        $sheetHtml
                    );
                }
            }
        }
    @endphp
    
    <div class="bg-white mb-4" wire:ignore>
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
document.addEventListener('DOMContentLoaded', function() {
    const zoomStates = new Map();
    
    document.querySelectorAll('.zoom-controls').forEach(controls => {
        const sheetId = controls.dataset.sheetId;
        const wrapper = document.getElementById(sheetId);
        const levelDisplay = document.getElementById('zoom-level-' + sheetId);
        
        if (!wrapper || !levelDisplay) return;
        
        zoomStates.set(sheetId, 100);
        
        controls.querySelectorAll('.zoom-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const action = this.dataset.zoomAction;
                let currentZoom = zoomStates.get(sheetId);
                
                if (action === 'in') {
                    currentZoom = Math.min(currentZoom + 10, 200);
                } else if (action === 'out') {
                    currentZoom = Math.max(currentZoom - 10, 50);
                } else if (action === 'reset') {
                    currentZoom = 100;
                }
                
                zoomStates.set(sheetId, currentZoom);
                wrapper.style.transform = `scale(${currentZoom / 100})`;
                levelDisplay.textContent = currentZoom + '%';
            });
        });
    });
});
</script>