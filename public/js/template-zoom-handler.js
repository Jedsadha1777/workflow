(function() {
    'use strict';
    
    window.sheetZoomLevels = window.sheetZoomLevels || {};
    
    function updateZoom(sheetId, newZoom) {
        if (!window.sheetZoomLevels[sheetId]) {
            window.sheetZoomLevels[sheetId] = 1.0;
        }
        
        window.sheetZoomLevels[sheetId] = Math.max(0.25, Math.min(2.0, newZoom));
        
        const wrapper = document.getElementById(sheetId);
        const zoomLevel = document.getElementById('zoom-level-' + sheetId);
        
        if (wrapper) {
            const table = wrapper.querySelector('table');
            
            if (table) {
                const scale = window.sheetZoomLevels[sheetId];
                
                // ไม่บีบ content - ให้ auto เต็มที่
                wrapper.style.transformOrigin = 'top left';
                wrapper.style.transform = 'scale(' + scale + ')';
                wrapper.style.width = 'fit-content';
                wrapper.style.height = 'fit-content';
                
                // ให้ table ขนาดเต็มที่ตามที่ควรจะเป็น
                table.style.width = '';
                table.style.minWidth = '';
                
            } else {
                wrapper.style.transform = 'scale(' + window.sheetZoomLevels[sheetId] + ')';
                wrapper.style.transformOrigin = 'top left';
                wrapper.style.width = 'fit-content';
                wrapper.style.height = 'fit-content';
            }
            
            if (zoomLevel) {
                zoomLevel.textContent = Math.round(window.sheetZoomLevels[sheetId] * 100) + '%';
            }
        }
    }
    
    function initZoomControls() {
        document.querySelectorAll('.zoom-controls').forEach(controls => {
            if (controls.dataset.zoomInitialized === 'true') return;
            
            const sheetId = controls.dataset.sheetId;
            if (!sheetId) return;
            
            const zoomInBtn = controls.querySelector('[data-zoom-action="in"]');
            const zoomOutBtn = controls.querySelector('[data-zoom-action="out"]');
            const zoomResetBtn = controls.querySelector('[data-zoom-action="reset"]');
            
            if (zoomInBtn) {
                zoomInBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const currentZoom = window.sheetZoomLevels[sheetId] || 1.0;
                    updateZoom(sheetId, currentZoom + 0.1);
                });
            }
            
            if (zoomOutBtn) {
                zoomOutBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const currentZoom = window.sheetZoomLevels[sheetId] || 1.0;
                    updateZoom(sheetId, currentZoom - 0.1);
                });
            }
            
            if (zoomResetBtn) {
                zoomResetBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    updateZoom(sheetId, 1.0);
                });
            }
            
            controls.dataset.zoomInitialized = 'true';
            
            setTimeout(() => {
                updateZoom(sheetId, 1.0);
            }, 50);
        });
    }
    
    const observer = new MutationObserver(function(mutations) {
        let shouldInit = false;
        
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === 1) {
                    if (node.classList?.contains('zoom-controls') || 
                        node.querySelector?.('.zoom-controls')) {
                        shouldInit = true;
                    }
                }
            });
        });
        
        if (shouldInit) {
            setTimeout(initZoomControls, 10);
        }
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initZoomControls);
    } else {
        initZoomControls();
    }
    
    document.addEventListener('livewire:navigated', initZoomControls);
    document.addEventListener('livewire:load', initZoomControls);
    document.addEventListener('filament:navigated', initZoomControls);
})();