// Form Field Renderer for Filament + Livewire v3
(function() {
    'use strict';
    
    function renderFormFields(html) {
        return html.replace(/\[(text|email|tel|number|date|textarea|select|checkbox|signature)(\*?)\s+([^\s\]]+)(?:\s+cell="([^"]+)")?(?:\s+first_as_label)?(?:\s+(.*?))?\]/g, function(match, type, required, name, cell, options) {
            const cellAttr = cell ? ' data-cell="' + cell + '"' : '';
            const requiredAttr = required ? ' required' : '';
            const fieldId = 'field_' + name.replace(/[^a-zA-Z0-9]/g, '_');
            
            if (type === 'select') {
                const optArray = options ? options.match(/"([^"]+)"/g) : [];
                const opts = optArray ? optArray.map(o => o.replace(/"/g, '')) : ['Option 1', 'Option 2'];
                let selectHtml = '<select id="' + fieldId + '" name="' + name + '" class="w-full border rounded px-2 py-1"' + cellAttr + requiredAttr + '>';
                selectHtml += '<option value="">-- Select --</option>';
                opts.forEach(opt => {
                    selectHtml += '<option value="' + opt + '">' + opt + '</option>';
                });
                selectHtml += '</select>';
                return selectHtml;
            }
            
            if (type === 'textarea') {
                return '<textarea id="' + fieldId + '" name="' + name + '" class="w-full border rounded px-2 py-1" rows="3"' + cellAttr + requiredAttr + '></textarea>';
            }
            
            if (type === 'checkbox') {
                return '<input type="checkbox" id="' + fieldId + '" name="' + name + '" class="rounded"' + cellAttr + '>';
            }
            
            if (type === 'signature') {
                return '<div class="border-2 border-dashed border-gray-300 rounded p-4 text-center text-gray-500"' + cellAttr + '>Signature will appear here after approval</div>';
            }
            
            return '<input type="' + type + '" id="' + fieldId + '" name="' + name + '" class="w-full border rounded px-2 py-1"' + cellAttr + requiredAttr + '>';
        });
    }
    
    function processTemplateContents() {
        document.querySelectorAll('.template-content[data-processed="false"]').forEach(content => {
            const originalHtml = content.innerHTML;
            content.innerHTML = renderFormFields(originalHtml);
            content.setAttribute('data-processed', 'true');
        });
    }
    
    function setupFormListener(formElement) {
        if (!formElement || formElement.dataset.listenerAttached === 'true') return;
        
        formElement.addEventListener('change', function(e) {
            if (!e.target.matches('input, select, textarea')) return;
            
            const data = {};
            formElement.querySelectorAll('input, select, textarea').forEach(field => {
                const cellRef = field.closest('td')?.getAttribute('data-cell') || field.getAttribute('data-cell');
                if (cellRef && (field.value || field.type === 'checkbox')) {
                    const [sheet, cell] = cellRef.split(':');
                    if (sheet && cell) {
                        if (!data[sheet]) data[sheet] = {};
                        data[sheet][cell] = field.type === 'checkbox' ? field.checked : field.value;
                    }
                }
            });
            
            // Livewire v3 method
            if (window.Livewire) {
                const wireId = formElement.closest('[wire\\:id]')?.getAttribute('wire:id');
                if (wireId) {
                    const component = window.Livewire.find(wireId);
                    if (component) {
                        component.set('data.form_data', JSON.stringify(data));
                    }
                }
            }
        }, true);
        
        formElement.dataset.listenerAttached = 'true';
    }
    
    function loadExistingData(formElement) {
        const existingDataAttr = formElement.dataset.existingData;
        if (!existingDataAttr) return;
        
        try {
            const decoded = document.createElement('textarea');
            decoded.innerHTML = existingDataAttr;
            const existingData = JSON.parse(decoded.value);
            
            if (existingData && Object.keys(existingData).length > 0) {
                Object.keys(existingData).forEach(sheet => {
                    Object.keys(existingData[sheet]).forEach(cell => {
                        const value = existingData[sheet][cell];
                        const cellRef = sheet + ':' + cell;
                        const field = formElement.querySelector('[data-cell="' + cellRef + '"]');
                        
                        if (field) {
                            if (field.type === 'checkbox') {
                                field.checked = value;
                            } else if (field.tagName === 'SELECT') {
                                field.value = value;
                            } else if (field.tagName === 'INPUT' || field.tagName === 'TEXTAREA') {
                                field.value = value;
                            }
                        }
                    });
                });
            }
        } catch (e) {
            console.error('Failed to load existing data:', e);
        }
    }
    
    function initTemplateForms() {
        processTemplateContents();
        
        document.querySelectorAll('[id^="doc_form_"]').forEach(form => {
            setupFormListener(form);
            loadExistingData(form);
        });
    }
    
    const observer = new MutationObserver(function(mutations) {
        let shouldInit = false;
        
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === 1) {
                    if (node.classList?.contains('template-content') || 
                        node.querySelector?.('.template-content') ||
                        node.id?.startsWith('doc_form_')) {
                        shouldInit = true;
                    }
                }
            });
        });
        
        if (shouldInit) {
            setTimeout(initTemplateForms, 10);
        }
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTemplateForms);
    } else {
        initTemplateForms();
    }
    
    document.addEventListener('livewire:navigated', initTemplateForms);
    document.addEventListener('livewire:load', initTemplateForms);
    
    window.renderFormFields = renderFormFields;
    window.initTemplateForms = initTemplateForms;
})();