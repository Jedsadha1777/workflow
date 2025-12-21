window.templateFormHandler = function(formId, existingData) {
    return {
        formId: formId,
        existingData: existingData,
        initialized: false,
        formData: {},
        
        init() {
            console.log('Alpine init for:', this.formId);
            
            // ดึง data จาก Livewire state ก่อน (กรณี re-mount)
            this.restoreFromLivewire();
            
            this.$nextTick(() => {
                this.renderFields();
                this.setupEventListeners();
            });
        },
        
        restoreFromLivewire() {
            // ดึง form_data จาก Livewire component
            const wireId = this.$el.closest('[wire\\:id]')?.getAttribute('wire:id');
            if (wireId && window.Livewire) {
                const component = window.Livewire.find(wireId);
                if (component && component.get('data.form_data')) {
                    try {
                        const savedData = JSON.parse(component.get('data.form_data'));
                        if (savedData && Object.keys(savedData).length > 0) {
                            this.formData = savedData;
                            console.log('Restored from Livewire:', this.formData);
                            return;
                        }
                    } catch (e) {
                        console.warn('Failed to restore from Livewire:', e);
                    }
                }
            }
            
            // Fallback ไปใช้ existingData
            if (this.existingData && Object.keys(this.existingData).length > 0) {
                this.formData = JSON.parse(JSON.stringify(this.existingData));
                console.log('Using existingData:', this.formData);
            }
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
                    } catch (e) {
                        console.error('Render error:', e);
                    }
                });
                
                // Load data to fields
                if (Object.keys(this.formData).length > 0) {
                    this.loadDataToFields();
                }
                
                this.initialized = true;
                console.log('Template initialized with data:', this.formData);
            };
            
            tryRender();
        },
        
        loadDataToFields() {
            const container = this.$el;
            
            Object.keys(this.formData).forEach(sheet => {
                Object.keys(this.formData[sheet]).forEach(cell => {
                    const value = this.formData[sheet][cell];
                    const cellRef = sheet + ':' + cell;
                    const field = container.querySelector('[data-cell="' + cellRef + '"]');
                    
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
            console.log('Collected form data:', data);
            
            // Sync to Livewire WITHOUT triggering refresh
            const wireId = this.$el.closest('[wire\\:id]')?.getAttribute('wire:id');
            if (wireId && window.Livewire) {
                const component = window.Livewire.find(wireId);
                if (component) {
                    component.set('data.form_data', JSON.stringify(data), false);
                }
            }
        }
    };
};