// Form Field Renderer for Filament + Livewire v3
(function() {
    'use strict';
    
    // Render CF7-style shortcodes to HTML form fields
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
    
    // Process template content containers
    function processTemplateContents() {
        document.querySelectorAll('.template-content[data-processed="false"]').forEach(content => {
            const originalHtml = content.innerHTML;
            content.innerHTML = renderFormFields(originalHtml);
            content.setAttribute('data-processed', 'true');
        });
    }
    
    // Setup form data change listener
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
            
            const textarea = document.querySelector('textarea[data-form-data="true"]');
            if (textarea) {
                textarea.value = JSON.stringify(data);
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }, true);
        
        formElement.dataset.listenerAttached = 'true';
    }
    
    // Initialize all template forms
    function initTemplateForms() {
        processTemplateContents();
        
        document.querySelectorAll('[id^="doc_form_"]').forEach(form => {
            setupFormListener(form);
        });
    }
    
    // Watch for DOM changes (Livewire morphs)
    const observer = new MutationObserver(function(mutations) {
        let shouldInit = false;
        
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === 1) {
                    if (node.classList?.contains('template-content') || 
                        node.querySelector?.('.template-content')) {
                        shouldInit = true;
                    }
                }
            });
        });
        
        if (shouldInit) {
            setTimeout(initTemplateForms, 10);
        }
    });
    
    // Start observing
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Initialize on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTemplateForms);
    } else {
        initTemplateForms();
    }
    
    // Livewire v3 hooks
    document.addEventListener('livewire:navigated', initTemplateForms);
    document.addEventListener('livewire:load', initTemplateForms);
    
    // Export for manual usage if needed
    window.renderFormFields = renderFormFields;
    window.initTemplateForms = initTemplateForms;
})();