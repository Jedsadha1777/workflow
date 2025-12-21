window.templateFormHandler = function(formId, existingData) {
    return {
        formId: formId,
        existingData: existingData,
        initialized: false,
        formData: {},
        
        init() {
            if (this.initialized) {
                console.log('Already initialized, skipping...');
                return;
            }
            
            console.log('Alpine init for:', this.formId);
            console.log('Existing data:', this.existingData);
            
            this.$nextTick(() => {
                this.renderFields();
                this.setupEventListeners();
            });
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
                });
            };
            
            tryRender();
        },
        
        loadDataToFields() {
            const container = this.$el;
            let loaded = 0;
            
            console.log('=== LOADING DATA ===');
            Object.keys(this.formData).forEach(sheet => {
                Object.keys(this.formData[sheet]).forEach(cell => {
                    const value = this.formData[sheet][cell];
                    const cellRef = sheet + ':' + cell;
                    
                    // หา <td> ที่มี data-cell
                    const td = container.querySelector('td[data-cell="' + cellRef + '"]');
                    
                    if (td) {
                        // หา field ใน td นั้น
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
                
                e.stopPropagation();
                this.collectFormData();
            }, true);
            
            container.addEventListener('input', (e) => {
                if (!e.target.matches('input, textarea')) return;
                e.stopPropagation();
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
            this.$wire.set('data.form_data', JSON.stringify(data), false);
        }
    };
};