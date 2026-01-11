window.templateFormHandler = function(formId, existingData) { 
    return {
        formId: formId,
        existingData: existingData,
        initialized: false,
        formData: {},
        
        init() {
            console.log('Alpine init for:', this.formId);
            console.log('Existing data:', this.existingData);
            
            this.$nextTick(() => {
                this.loadCalculationScript();
                this.renderFields();
                this.setupEventListeners();
                this.setupCalculationFunctions();
            });
        },
        
        loadCalculationScript() {
            const scriptId = 'calc-script-' + this.formId;
            const existingScript = document.getElementById(scriptId);
            
            if (existingScript && existingScript.textContent) {
                const scriptCode = existingScript.textContent.trim();
                
                if (scriptCode) {
                    console.log('Loading calculation script via dynamic element...');
                    
                    const newScript = document.createElement('script');
                    newScript.textContent = scriptCode;
                    newScript.id = scriptId + '-dynamic';
                    
                    document.head.appendChild(newScript);
                    
                    console.log('✓ Calculation script loaded');
                    
                    const funcName = 'runCalculations_' + this.formId;
                    if (typeof window[funcName] === 'function') {
                        console.log('✓ Function', funcName, 'is now available');
                    } else {
                        console.error('✗ Function', funcName, 'not found after loading script');
                    }
                } else {
                    console.warn('Script element found but empty');
                }
            } else {
                console.warn('No calculation script found for:', scriptId);
            }
        },
        
        setupCalculationFunctions() {
            const container = this.$el;
            const self = this;
            
            window.getValue = function(cellRef) {
                const [sheet, cell] = cellRef.split(':');
                if (!sheet || !cell) return '';
                
                const td = container.querySelector('td[data-cell="' + cellRef + '"]');
                if (!td) return '';
                
                const field = td.querySelector('input, select, textarea');
                if (!field) return '';
                
                if (field.type === 'checkbox') {
                    return field.checked;
                }
                return field.value || '';
            };
            
            window.setValue = function(cellRef, value) {
                const [sheet, cell] = cellRef.split(':');
                if (!sheet || !cell) return;
                
                const td = container.querySelector('td[data-cell="' + cellRef + '"]');
                if (!td) return;
                
                const field = td.querySelector('input, select, textarea');
                
                if (field) {
                    if (field.type === 'checkbox') {
                        field.checked = !!value;
                    } else {
                        field.value = value;
                    }
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    td.innerHTML = '<strong style="color: #059669;">' + value + '</strong>';
                }
            };
            
            console.log('✓ Calculation functions ready: getValue(), setValue()');
        },
        
        renderFields() {
            if (this.initialized) return;
            
            const container = this.$el;
            let retryCount = 0;
            const maxRetries = 50;
            
            const tryRender = () => {
                if (!window.renderFormFields) {
                    retryCount++;
                    if (retryCount < maxRetries) {
                        setTimeout(tryRender, 100);
                    } else {
                        console.error('renderFormFields not loaded after', maxRetries, 'retries');
                    }
                    return;
                }
                
                container.querySelectorAll('.template-content[data-processed="false"]').forEach(content => {
                    try {
                        content.innerHTML = window.renderFormFields(content.innerHTML);
                        content.setAttribute('data-processed', 'true');
                        console.log('✓ Rendered template content');
                        
                        content.querySelectorAll('td[data-cell]').forEach(td => {
                            const text = td.textContent.trim();
                            if (text === '#VALUE!' || text === '#DIV/0!' || text === '#REF!' || text === '#N/A' || text === '#NAME?') {
                                td.textContent = '';
                            }
                        });
                        console.log('✓ Cleared Excel errors');
                    } catch (e) {
                        console.error('Render error:', e);
                    }
                });
                
                requestAnimationFrame(() => {
                    if (this.existingData && Object.keys(this.existingData).length > 0) {
                        this.formData = JSON.parse(JSON.stringify(this.existingData));
                        console.log('Loading data to fields...');
                        this.loadDataToFields();
                    }
                    
                    this.initialized = true;
                    console.log('Template initialized');
                    
                    if (this.existingData && Object.keys(this.existingData).length > 0) {
                        this.runCalculations();
                    }
                });
            };
            
            tryRender();
        },
        
        runCalculations() {
            const funcName = 'runCalculations_' + this.formId;
            
            if (typeof window[funcName] === 'function') {
                try {
                    window[funcName](); 
                    console.log('✓ Calculations executed via', funcName);
                } catch (e) {
                    console.error('Calculation error:', e);
                }
            }
        },
        
        loadDataToFields() {
            const container = this.$el;
            let loaded = 0;
            
            console.log('=== LOADING DATA ===');
            Object.keys(this.formData).forEach(sheet => {
                Object.keys(this.formData[sheet]).forEach(cell => {
                    const value = this.formData[sheet][cell];
                    const cellRef = sheet + ':' + cell;
                    
                    const td = container.querySelector('td[data-cell="' + cellRef + '"]');
                    
                    if (td) {
                        const field = td.querySelector('input, select, textarea');
                        
                        if (field) {
                            if (field.type === 'checkbox') {
                                field.checked = value;
                            } else if (field.tagName === 'SELECT') {
                                field.value = value;
                            } else if (field.tagName === 'INPUT' || field.tagName === 'TEXTAREA') {
                                field.value = value;
                            }
                            loaded++;
                            console.log('✓ Loaded:', cellRef, '=', value);
                        } else {
                            console.warn('Field not found in td:', cellRef);
                        }
                    } else {
                        console.warn('TD not found:', cellRef);
                    }
                });
            });
            console.log('✓ Total loaded', loaded, 'fields');
        },
        
        setupEventListeners() {
            const container = this.$el;
            
            container.addEventListener('change', (e) => {
                if (!e.target.matches('input, select, textarea')) return;
                
                this.collectFormData();
                this.runCalculations();
            }, true);
            
            container.addEventListener('input', (e) => {
                if (!e.target.matches('input, textarea')) return;
            }, true);
        },
        
        collectFormData() {
            const container = this.$el;
            const data = {};
            
            container.querySelectorAll('input, select, textarea').forEach(field => {
                const cellRef = field.closest('td')?.getAttribute('data-cell') || field.getAttribute('data-cell');
                if (cellRef) {
                    const [sheet, cell] = cellRef.split(':');
                    if (sheet && cell) {
                        if (!data[sheet]) data[sheet] = {};
                        data[sheet][cell] = field.type === 'checkbox' ? field.checked : field.value;
                    }
                }
            });
            
            this.formData = data;
            
            if (this.$wire) {
                this.$wire.set('data.form_data', JSON.stringify(data), false);
            }
        }
    };
};