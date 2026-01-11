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
                // ข้ามถ้าค่ายังเป็น shortcode component
                if (is_string($value)) {
                    $trimmedValue = trim($value);
                    if (preg_match('/^\[(?:signature|input|textarea|date|select|checkbox|radio|number|text)/', $trimmedValue)) {
                        continue;
                    }
                    if (empty($trimmedValue)) {
                        continue;
                    }
                }

                if (is_array($value) && isset($value['type']) && $value['type'] === 'signature') {
                    // ถ้ายังไม่มี approver_id หรือยังไม่ได้ sign → แสดง placeholder
                    if (empty($value['approver_id']) || empty($value['signed_at'])) {
                        $signatureHtml = '<div class="border-2 border-dashed border-gray-300 rounded p-4 text-center text-gray-500">Signature will appear here after approval</div>';
                    } else {
                        $approver = \App\Models\User::find($value['approver_id']);
                        
                        if ($approver && $approver->signature_image) {
                            $signatureUrl = asset('storage/' . $approver->signature_image);
                            $signatureHtml = '<div style="text-align:center;">' .
                                '<img src="' . $signatureUrl . '" style="max-width:150px;max-height:60px;display:block;margin:0 auto;" alt="Signature">' .
                                '</div>';
                        } else {
                            $signatureHtml = '<div style="border:2px solid #10b981;padding:10px;text-align:center;background:#f0fdf4;border-radius:6px;">' .
                                '<div style="font-weight:bold;color:#065f46;">✓ ' . htmlspecialchars($approver ? $approver->name : 'Unknown') . '</div>' .
                                '</div>';
                        }
                    }
                    
                    $sheetHtml = preg_replace(
                        '/<td([^>]*data-cell="' . preg_quote($sheetName . ':' . $cell, '/') . '"[^>]*)>.*?<\/td>/s',
                        '<td$1>' . $signatureHtml . '</td>',
                        $sheetHtml
                    );
                } elseif (is_array($value) && isset($value['type']) && $value['type'] === 'date') {
                    // ข้ามถ้ายังไม่ได้เลือกวันที่
                    if (empty($value['date'])) {
                        continue;
                    }
                    
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
        
        // แทนที่ #VALUE!, #DIV/0!, #REF!, #N/A, #NAME? ด้วยค่าว่าง
        $sheetHtml = preg_replace('/#VALUE!|#DIV\/0!|#REF!|#N\/A|#NAME\?/i', '', $sheetHtml);
        
        // แทนที่ shortcode components ด้วย div เปล่าเพื่อรักษา structure
        $sheetHtml = preg_replace('/\[(?:signature|input|textarea|date|select|checkbox|radio|number|text)(?:\s+[^\]]+)?\]/i', '<div style="padding:4px;">&nbsp;</div>', $sheetHtml);
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
        
        <div style="overflow: auto; border: 1px solid #e5e7eb; border-radius: 6px; background: #fff;">
            <div style="padding: 16px;">
                <div id="{{ $sheetId }}" class="preview-zoom-wrapper">
                    {!! $sheetHtml !!}
                </div>
            </div>
        </div>
    </div>
@endforeach

<script>
document.addEventListener('DOMContentLoaded', function() {
    const zoomStates = new Map();
    
    function updateZoom(wrapper, scale, levelDisplay) {
        const table = wrapper.querySelector('table');
        
        if (table) {
            // Reset to measure natural size
            wrapper.style.transform = 'scale(1)';
            wrapper.style.width = 'auto';
            wrapper.style.height = 'auto';
            
            // Force reflow
            void wrapper.offsetWidth;
            
            const naturalWidth = table.offsetWidth;
            const naturalHeight = table.offsetHeight;
            
            // Apply scale to wrapper
            wrapper.style.transformOrigin = 'top left';
            wrapper.style.transform = 'scale(' + scale + ')';
            wrapper.style.width = naturalWidth + 'px';
            wrapper.style.height = naturalHeight + 'px';
            
            // Update parent (padding container) size
            const paddingParent = wrapper.parentElement;
            if (paddingParent) {
                const padding = 16; // padding: 16px
                const scaledWidth = naturalWidth * scale + padding * 2;
                const scaledHeight = naturalHeight * scale + padding * 2;
                
                // เช็คว่ากล่องข้างในใหญ่กว่า parent หรือไม่
                const overflowParent = paddingParent.parentElement;
                if (overflowParent) {
                    const parentWidth = overflowParent.clientWidth;
                    const parentHeight = overflowParent.clientHeight;
                    
                    // เช็คว่าเนื้อหาใหญ่กว่ากรอบหรือไม่
                    if (scaledWidth > parentWidth || scaledHeight > parentHeight) {
                        // ใหญ่กว่า → เปิด overflow auto
                        overflowParent.style.overflow = 'auto';
                        paddingParent.style.width = '';
                        paddingParent.style.height = '';
                    } else {
                        // เล็กกว่า → ปิด overflow, ตั้งขนาดพอดี
                        overflowParent.style.overflow = 'visible';
                        paddingParent.style.width = scaledWidth + 'px';
                        paddingParent.style.height = scaledHeight + 'px';
                    }
                }
            }
            
            table.style.width = '';
            table.style.minWidth = '';
        }
        
        if (levelDisplay) {
            levelDisplay.textContent = Math.round(scale * 100) + '%';
        }
    }
    
    document.querySelectorAll('.zoom-controls').forEach(controls => {
        const sheetId = controls.dataset.sheetId;
        const wrapper = document.getElementById(sheetId);
        const levelDisplay = document.getElementById('zoom-level-' + sheetId);
        
        if (!wrapper || !levelDisplay) return;
        
        zoomStates.set(sheetId, 1.0);
        
        controls.querySelectorAll('.zoom-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const action = this.dataset.zoomAction;
                let currentZoom = zoomStates.get(sheetId);
                
                if (action === 'in') {
                    currentZoom = Math.min(currentZoom + 0.1, 2.0);
                } else if (action === 'out') {
                    currentZoom = Math.max(currentZoom - 0.1, 0.25);
                } else if (action === 'reset') {
                    currentZoom = 1.0;
                }
                
                zoomStates.set(sheetId, currentZoom);
                updateZoom(wrapper, currentZoom, levelDisplay);
            });
        });
        
        updateZoom(wrapper, 1.0, levelDisplay);
    });
});
</script>