<x-filament-panels::page>
    <div wire:ignore class="space-y-6" x-data="pdfLayoutEditor()" x-init="init()">
        <x-filament::section>
            <x-slot name="heading">
                PDF Layout Editor
            </x-slot>
            
            <div>
                {{ $this->form }}
            </div>
        </x-filament::section>

        <div class="space-y-4">
            <!-- Sheet Tabs -->
            <div class="flex gap-2 border-b">
                <template x-for="(sheet, index) in sheets" :key="index">
                    <button type="button"
                            @click="switchSheet(index)"
                            :class="currentSheet === index ? 'border-b-2 border-primary-600 text-primary-600' : ''"
                            class="px-4 py-2 font-medium"
                            :style="currentSheet !== index ? 'color:#6b7280;' : ''"
                            x-text="sheet.name">
                    </button>
                </template>
            </div>

            <!-- Fullscreen Toggle -->
            <div class="flex gap-2 p-4 bg-white rounded-lg border">
                <button type="button"
                        @click="toggleFullscreen()"
                        class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700">
                    Open Split View Editor
                </button>
            </div>

            <!-- Split View Fullscreen Editor -->
            <div x-show="isFullscreen" 
                 class="fixed inset-0 z-50 bg-white overflow-auto"
                 style="overscroll-behavior: contain; -webkit-overflow-scrolling: touch;">
                
                <!-- Header -->
                <div class="flex items-center justify-between p-4 border-b bg-gray">
                    <div class="flex items-center gap-4">
                        <h2 class="text-lg font-semibold">Split View Editor</h2>
                        
                        <!-- Orientation Toggle -->
                        <select x-model="localOrientation"
                                @change="updateOrientation()"
                                class="rounded border text-sm">
                            <option value="portrait">Portrait (794×1123)</option>
                            <option value="landscape">Landscape (1123×794)</option>
                        </select>
                        
                        <!-- Action Buttons -->
                        <button type="button"
                                @click="extractStyles()"
                                class="inline-flex items-center px-3 py-1.5 bg-primary-600 text-white text-sm rounded hover:bg-primary-500">
                            Extract CSS
                        </button>
                        
                        <button type="button"
                                @click="beautifyHtml()"
                                class="inline-flex items-center px-3 py-1.5 text-white text-sm rounded hover:opacity-80"
                                style="background-color:#4B5563;">
                            Format HTML
                        </button>
                        
                        <button type="button"
                                @click="saveChanges()"
                                class="inline-flex items-center px-3 py-1.5 text-white text-sm rounded"
                                style="background-color:#059669;">
                            Save
                        </button>
                        
                        <a href="/template-documents/{{ $this->record->id }}/preview-pdf"
                           target="_blank"
                           class="inline-flex items-center px-3 py-1.5 bg-primary-600 text-white text-sm rounded hover:bg-primary-500">
                            Preview PDF
                        </a>
                    </div>
                    
                    <button type="button"
                            @click="toggleFullscreen()"
                            class="px-4 py-2 bg-gray rounded hover:bg-gray">
                        Exit Fullscreen
                    </button>
                </div>

                <!-- Split View Container -->
                <div class="flex h-[calc(100vh-64px)] overflow-hidden">
                    <!-- Left Panel - Code + CSS -->
                    <div class="w-1/2 border-r flex flex-col min-h-0">
                        <!-- Generated CSS - EDITABLE -->
                        <div class="p-4 border-b overflow-y-auto" style="max-height: 300px; background-color:#f9fafb;">
                            <h3 class="font-semibold mb-3">Generated CSS</h3>
                            
                            <div x-show="!generatedCss" class="text-sm" style="color:#6b7280;">
                                Click "Extract CSS" to scan HTML and generate styles
                            </div>
                            
                            <textarea x-show="generatedCss" 
                                      x-model="generatedCss"
                                      @input="updatePreview()"
                                      class="w-full text-xs font-mono p-3 border rounded bg-white"
                                      style="min-height: 200px; font-family: monospace; white-space: pre;"
                                      spellcheck="false"></textarea>
                        </div>

                        <!-- Code Editor - WITH SCROLL -->
                        <div class="flex-1 overflow-auto min-h-0">
                            <div x-ref="editorContainer" class="h-full w-full"></div>
                        </div>
                    </div>

                    <!-- Right Panel - Preview -->
                    <div class="w-1/2 flex flex-col min-h-0">
                        <!-- Zoom Controls -->
                        <div class="p-4 border-b flex items-center gap-4" style="background-color:#f9fafb;">
                            <span class="text-sm font-medium">Zoom:</span>
                            <button type="button"
                                    @click="zoom = Math.max(0.25, zoom - 0.25)"
                                    class="px-3 py-1 rounded"
                                    style="background-color:#e5e7eb;">-</button>
                            <span class="text-sm w-16 text-center" x-text="Math.round(zoom * 100) + '%'"></span>
                            <button type="button"
                                    @click="zoom = Math.min(2, zoom + 0.25)"
                                    class="px-3 py-1 rounded"
                                    style="background-color:#e5e7eb;">+</button>
                            <button type="button"
                                    @click="zoom = 1"
                                    class="px-3 py-1 rounded text-sm"
                                    style="background-color:#e5e7eb;">100%</button>
                        </div>
                        
                        <!-- Preview Area WITH SCROLL -->
                        <div class="flex-1 overflow-auto p-8" style="background-color:#f3f4f6;">
                            <div :style="`transform: scale(${zoom}); transform-origin: top left; transition: transform 0.2s;`">
                                <div x-ref="previewRoot"
                                     :style="`width: ${paperSize.width}px; height: ${paperSize.height}px;`"
                                     class="bg-white shadow-lg relative">
                                    
                                    <!-- Grid Lines -->
                                    <div class="absolute inset-0 pointer-events-none" 
                                         style="background-image: 
                                            linear-gradient(rgba(0,0,0,0.05) 1px, transparent 1px),
                                            linear-gradient(90deg, rgba(0,0,0,0.05) 1px, transparent 1px);
                                            background-size: 50px 50px;">
                                    </div>

                                    <!-- Preview Content -->
                                    <div x-ref="previewContent"
                                         style="padding: 20px; position: absolute; top: 0; left: 0; right: 0; bottom: 0; overflow: visible;">
                                        <div x-html="previewHtml"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Overflow Warning -->
                            <div x-show="isOverflowing" 
                                 class="mt-4 bg-red-500 text-white text-center py-2 px-4 rounded font-bold">
                                ⚠️ Content exceeds page boundary
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/monokai.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/js-beautify/1.14.11/beautify-html.min.js"></script>

    <style>
        .CodeMirror {
            height: 600px;
            font-size: 13px;
        }
    </style>

    <script>
        function pdfLayoutEditor() {
            return {
                isFullscreen: false,
                currentSheet: 0,
                sheets: [],
                currentHtml: '',
                previewHtml: '',
                editor: null,
                isOverflowing: false,
                zoom: 1,
                localOrientation: 'portrait',
                paperSize: { width: 794, height: 1123 },
                cssClasses: [],
                generatedCss: '',

                init() {
                    const contentData = @json($this->record->content);
                    console.log('Raw contentData:', contentData);
                    
                    const parsedContent = typeof contentData === 'string' ? JSON.parse(contentData) : contentData;
                    console.log('Parsed content:', parsedContent);
                    
                    this.sheets = parsedContent.sheets || [];
                    this.generatedCss = parsedContent.generatedCss || '';
                    this.cssClasses = parsedContent.cssClasses || [];
                    
                    console.log('Total sheets loaded:', this.sheets.length);
                    console.log('Sheets:', this.sheets);
                    
                    this.currentHtml = this.sheets[0]?.html || '';
                    this.previewHtml = (this.generatedCss || '') + this.currentHtml;

                    this.localOrientation = this.$wire?.data?.pdf_orientation || 'portrait';
                    this.updatePaperSize();

                    this.$watch('$wire.data.pdf_orientation', (value) => {
                        this.localOrientation = value;
                        this.updatePaperSize();
                    });
                },

                updatePaperSize() {
                    // ใช้ mm เหมือน CSS @page
                    // A4: 210mm × 297mm
                    // หัก margin 5mm×2 = 200mm × 287mm
                    // Convert mm to px: 1mm ≈ 3.78px (96 DPI)
                    if (this.localOrientation === 'landscape') {
                        // 287mm × 200mm
                        this.paperSize = { width: 1085, height: 756 }; // 287×3.78, 200×3.78
                    } else {
                        // 200mm × 287mm
                        this.paperSize = { width: 756, height: 1085 };
                    }
                    this.checkOverflow();
                },

                updateOrientation() {
                    // ไม่ใช้ $wire.set() เพราะจะทำให้ Livewire rerender และทำลาย Alpine
                    // เก็บค่าไว้ใน localOrientation และจะ save ตอนกด save() เท่านั้น
                    this.updatePaperSize();
                },
                
                beautifyHtml() {
                    if (this.editor) {
                        try {
                            const cursor = this.editor.getCursor();
                            const currentValue = this.editor.getValue();
                            
                            console.log('=== BEFORE BEAUTIFY ===');
                            console.log('Length:', currentValue.length);
                            console.log('First 500 chars:', currentValue.substring(0, 500));
                            console.log('Has <table:', currentValue.includes('<table'));
                            console.log('Has <tr:', currentValue.includes('<tr'));
                            console.log('Has <td:', currentValue.includes('<td'));
                            
                            // นับจำนวน tags
                            const tableCount = (currentValue.match(/<table/g) || []).length;
                            const trCount = (currentValue.match(/<tr/g) || []).length;
                            const tdCount = (currentValue.match(/<td/g) || []).length;
                            console.log('Tag counts - table:', tableCount, 'tr:', trCount, 'td:', tdCount);
                            
                            // นับบรรทัดว่างด้านบน
                            const leadingNewlines = currentValue.match(/^\n*/)[0];
                            
                            const beautified = html_beautify(currentValue, {
                                indent_size: 2,
                                wrap_line_length: 0,
                                preserve_newlines: true,
                                max_preserve_newlines: 2,
                                unformatted: []
                            });
                            
                            console.log('=== AFTER BEAUTIFY ===');
                            console.log('Length:', beautified.length);
                            console.log('First 500 chars:', beautified.substring(0, 500));
                            
                            const tableCountAfter = (beautified.match(/<table/g) || []).length;
                            const trCountAfter = (beautified.match(/<tr/g) || []).length;
                            const tdCountAfter = (beautified.match(/<td/g) || []).length;
                            console.log('Tag counts - table:', tableCountAfter, 'tr:', trCountAfter, 'td:', tdCountAfter);
                            
                            // เช็คว่า tags หายหรือเปล่า
                            if (tableCountAfter < tableCount || trCountAfter < trCount || tdCountAfter < tdCount) {
                                alert(`Format HTML cancelled: Tags lost!\nBefore: table=${tableCount}, tr=${trCount}, td=${tdCount}\nAfter: table=${tableCountAfter}, tr=${trCountAfter}, td=${tdCountAfter}`);
                                return;
                            }
                            
                            if (beautified && beautified.length > 0) {
                                // เอาบรรทัดว่างด้านบนกลับมา
                                const result = leadingNewlines + beautified;
                                
                                console.log('=== SETTING TO EDITOR ===');
                                console.log('Result length:', result.length);
                                
                                // ไม่ clear ก่อน - set ตรงๆ
                                this.editor.setValue(result);
                                
                                // เช็คว่า set สำเร็จไหม
                                const afterSet = this.editor.getValue();
                                console.log('After setValue length:', afterSet.length);
                                console.log('Data loss in setValue:', ((result.length - afterSet.length) / result.length * 100).toFixed(2) + '%');
                                
                                const afterTableCount = (afterSet.match(/<table/g) || []).length;
                                const afterTrCount = (afterSet.match(/<tr/g) || []).length;
                                const afterTdCount = (afterSet.match(/<td/g) || []).length;
                                console.log('After setValue tags - table:', afterTableCount, 'tr:', afterTrCount, 'td:', afterTdCount);
                                
                                if (afterSet.length < result.length * 0.95) {
                                    alert('CodeMirror data loss detected! Reverting...');
                                    this.editor.setValue(currentValue);
                                    return;
                                }
                                
                                // Force refresh viewport
                                this.editor.refresh();
                                
                                // Scroll กลับไปตำแหน่งเดิม
                                this.editor.setCursor(cursor);
                                this.editor.scrollIntoView(cursor, 100);
                            } else {
                                console.error('Beautify returned empty result');
                            }
                        } catch (e) {
                            console.error('Beautify error:', e);
                            alert('Error formatting HTML: ' + e.message);
                        }
                    }
                },

                toggleFullscreen() {
                    this.isFullscreen = !this.isFullscreen;
                    
                    if (this.isFullscreen) {
                        this.$nextTick(() => {
                            // Clear existing editor
                            if (this.editor) {
                                this.editor = null;
                            }
                            this.$refs.editorContainer.innerHTML = '';
                            
                            const beautified = html_beautify(this.currentHtml, {
                                indent_size: 2,
                                wrap_line_length: 0,
                                preserve_newlines: true,
                                max_preserve_newlines: 2
                            });
                            
                            this.editor = CodeMirror(this.$refs.editorContainer, {
                                mode: 'htmlmixed',
                                theme: 'monokai',
                                lineNumbers: true,
                                lineWrapping: true,
                                value: beautified
                            });

                            this.editor.on('change', () => {
                                this.currentHtml = this.editor.getValue();
                                this.sheets[this.currentSheet].html = this.currentHtml;
                                this.updatePreview();
                                this.saveToHidden();
                            });
                            
                            this.updatePreview();
                        });
                    } else {
                        if (this.editor) {
                            this.currentHtml = this.editor.getValue();
                            this.editor = null;
                        }
                    }
                },

                extractStyles() {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(this.currentHtml, 'text/html');
                    const allElements = doc.querySelectorAll('[style]');
                    
                    console.log('Total elements with style:', allElements.length);
                    
                    if (allElements.length === 0) {
                        alert('No inline styles found. Styles may have already been extracted.');
                        return;
                    }
                    
                    // Collect unique font-size and font-family for CSS Variables
                    const fontSizeMap = new Map(); // value -> varName
                    const fontFamilyMap = new Map();
                    let fontSizeIndex = 1;
                    let fontFamilyIndex = 1;
                    
                    // Collect identical style combinations for CSS Classes
                    const styleMap = new Map();
                    let classIndex = 1;
                    
                    // First pass: collect all fonts
                    allElements.forEach(el => {
                        const styleAttr = el.getAttribute('style');
                        if (!styleAttr) return;
                        
                        const styleAst = this.parseInlineStyle(styleAttr);
                        
                        // Collect fonts for variables
                        if (styleAst['font-size'] && !fontSizeMap.has(styleAst['font-size'])) {
                            fontSizeMap.set(styleAst['font-size'], `--font-size-${fontSizeIndex++}`);
                        }
                        if (styleAst['font-family'] && !fontFamilyMap.has(styleAst['font-family'])) {
                            fontFamilyMap.set(styleAst['font-family'], `--font-family-${fontFamilyIndex++}`);
                        }
                    });
                    
                    // Second pass: replace fonts with var() and collect for classes
                    allElements.forEach(el => {
                        const styleAttr = el.getAttribute('style');
                        if (!styleAttr) return;
                        
                        const styleAst = this.parseInlineStyle(styleAttr);
                        let modified = false;
                        
                        // Replace font-size with var()
                        if (styleAst['font-size'] && fontSizeMap.has(styleAst['font-size'])) {
                            const varName = fontSizeMap.get(styleAst['font-size']);
                            styleAst['font-size'] = `var(${varName})`;
                            modified = true;
                        }
                        
                        // Replace font-family with var()
                        if (styleAst['font-family'] && fontFamilyMap.has(styleAst['font-family'])) {
                            const varName = fontFamilyMap.get(styleAst['font-family']);
                            styleAst['font-family'] = `var(${varName})`;
                            modified = true;
                        }
                        
                        // Update element style if modified
                        if (modified) {
                            el.setAttribute('style', this.rebuildStyleString(styleAst));
                        }
                        
                        // Collect for classes (after font replacement)
                        const key = JSON.stringify(styleAst);
                        
                        if (!styleMap.has(key)) {
                            styleMap.set(key, {
                                styles: styleAst,
                                count: 0,
                                elements: [],
                                className: `style-${classIndex++}`
                            });
                        }
                        
                        const entry = styleMap.get(key);
                        entry.count++;
                        entry.elements.push(el);
                    });
                    
                    console.log('Unique font-sizes:', fontSizeMap.size);
                    console.log('Unique font-families:', fontFamilyMap.size);
                    console.log('Total unique styles:', styleMap.size);
                    
                    // Create CSS classes
                    this.cssClasses = [];
                    
                    // Add CSS Variables for fonts
                    fontSizeMap.forEach((varName, value) => {
                        this.cssClasses.push({
                            className: varName,
                            fontSize: value,
                            fontFamily: '',
                            count: 0,
                            isVariable: true
                        });
                    });
                    
                    fontFamilyMap.forEach((varName, value) => {
                        this.cssClasses.push({
                            className: varName,
                            fontSize: '',
                            fontFamily: value,
                            count: 0,
                            isVariable: true
                        });
                    });
                    
                    // Add CSS Classes for repeated styles (now with var() in them)
                    styleMap.forEach((entry, key) => {
                        console.log(`Style ${entry.className}: used ${entry.count} times`, entry.styles);
                        if (entry.count >= 2) {
                            this.cssClasses.push({
                                className: entry.className,
                                fontSize: entry.styles['font-size'] || '',
                                fontFamily: entry.styles['font-family'] || '',
                                color: entry.styles['color'] || '',
                                backgroundColor: entry.styles['background-color'] || '',
                                count: entry.count,
                                allStyles: entry.styles,
                                isVariable: false
                            });
                            
                            // Replace inline styles with class
                            entry.elements.forEach(el => {
                                el.removeAttribute('style');
                                el.classList.add(entry.className);
                            });
                        }
                    });
                    
                    console.log('Total CSS classes + variables created:', this.cssClasses.length);
                    
                    // Generate CSS
                    this.applyCssVariables();
                    
                    console.log('Generated CSS:', this.generatedCss);
                    
                    // ไม่ใช้ innerHTML - แก้ style attribute ตรงๆ ใน string
                    let html = this.currentHtml;
                    
                    // สร้าง Map เก็บ style attribute เก่า -> ใหม่
                    const replacements = [];
                    
                    allElements.forEach(el => {
                        const oldStyle = el.getAttribute('style');
                        const newStyle = el.getAttribute('style');
                        
                        if (oldStyle !== newStyle) {
                            replacements.push({
                                old: `style="${oldStyle}"`,
                                new: `style="${newStyle}"`
                            });
                        }
                    });
                    
                    // Replace style attributes ใน string HTML
                    replacements.forEach(r => {
                        html = html.replace(r.old, r.new);
                    });
                    
                    this.currentHtml = html;
                    this.sheets[this.currentSheet].html = this.currentHtml;
                    
                    if (this.editor) {
                        const cursor = this.editor.getCursor();
                        const originalLength = this.currentHtml.length;
                        
                        console.log('=== EXTRACT CSS - BEFORE BEAUTIFY ===');
                        console.log('Length:', originalLength);
                        const tableCount = (this.currentHtml.match(/<table/g) || []).length;
                        const trCount = (this.currentHtml.match(/<tr/g) || []).length;
                        const tdCount = (this.currentHtml.match(/<td/g) || []).length;
                        console.log('Tags - table:', tableCount, 'tr:', trCount, 'td:', tdCount);
                        
                        const beautified = html_beautify(this.currentHtml, {
                            indent_size: 2,
                            wrap_line_length: 0,
                            preserve_newlines: true,
                            max_preserve_newlines: 2
                        });
                        
                        console.log('=== EXTRACT CSS - AFTER BEAUTIFY ===');
                        console.log('Length:', beautified.length);
                        const tableCountAfter = (beautified.match(/<table/g) || []).length;
                        const trCountAfter = (beautified.match(/<tr/g) || []).length;
                        const tdCountAfter = (beautified.match(/<td/g) || []).length;
                        console.log('Tags - table:', tableCountAfter, 'tr:', trCountAfter, 'td:', tdCountAfter);
                        
                        // เช็คว่า tags หาย
                        if (tableCountAfter < tableCount || trCountAfter < trCount || tdCountAfter < tdCount) {
                            alert(`Extract CSS cancelled: Tags lost!\nBefore: table=${tableCount}, tr=${trCount}, td=${tdCount}\nAfter: table=${tableCountAfter}, tr=${trCountAfter}, td=${tdCountAfter}`);
                            return;
                        }
                        
                        console.log('=== EXTRACT CSS - SETTING TO EDITOR ===');
                        
                        // ไม่ clear - set ตรงๆ
                        this.editor.setValue(beautified);
                        
                        // เช็คหลัง set
                        const afterSet = this.editor.getValue();
                        console.log('After setValue length:', afterSet.length);
                        console.log('Data loss:', ((beautified.length - afterSet.length) / beautified.length * 100).toFixed(2) + '%');
                        
                        if (afterSet.length < beautified.length * 0.95) {
                            alert('CodeMirror data loss detected! Reverting...');
                            this.currentHtml = this.sheets[this.currentSheet].html; // revert
                            this.editor.setValue(this.currentHtml);
                            return;
                        }
                        
                        // Force refresh
                        this.editor.refresh();
                        this.editor.setCursor(cursor);
                        this.editor.scrollIntoView(cursor, 100);
                    }
                    
                    // Update preview with CSS + HTML
                    this.updatePreview();
                },
                
                rebuildStyleString(styleAst) {
                    const parts = [];
                    for (const [prop, value] of Object.entries(styleAst)) {
                        if (value) {
                            parts.push(`${prop}: ${value}`);
                        }
                    }
                    return parts.join('; ');
                },

                parseInlineStyle(styleString) {
                    const styles = {};
                    styleString.split(';').forEach(rule => {
                        const colonIndex = rule.indexOf(':');
                        if (colonIndex === -1) return;
                        
                        const prop = rule.substring(0, colonIndex).trim();
                        const value = rule.substring(colonIndex + 1).trim();
                        
                        if (prop && value) {
                            styles[prop] = value;
                        }
                    });
                    return styles;
                },

                applyCssVariables() {
                    // Generate CSS
                    let cssText = '<style>\n';
                    
                    // CSS Variables in :root
                    const variables = this.cssClasses.filter(c => c.isVariable);
                    if (variables.length > 0) {
                        cssText += ':root {\n';
                        variables.forEach(cssClass => {
                            if (cssClass.fontSize) {
                                let fontSize = cssClass.fontSize.trim();
                                // เช็คว่าเป็นตัวเลขล้วนไหม (ไม่มี px, pt, em)
                                if (!isNaN(fontSize) && !fontSize.includes('px') && !fontSize.includes('pt') && !fontSize.includes('em')) {
                                    fontSize += 'px';
                                }
                                cssText += `  ${cssClass.className}: ${fontSize};\n`;
                            }
                            if (cssClass.fontFamily) {
                                cssText += `  ${cssClass.className}: ${cssClass.fontFamily};\n`;
                            }
                        });
                        cssText += '}\n\n';
                    }
                    
                    // CSS Classes (already using var())
                    const classes = this.cssClasses.filter(c => !c.isVariable);
                    classes.forEach(cssClass => {
                        cssText += `.${cssClass.className} {\n`;
                        
                        // Font properties already have var() from extractStyles
                        if (cssClass.fontSize) cssText += `  font-size: ${cssClass.fontSize};\n`;
                        if (cssClass.fontFamily) cssText += `  font-family: ${cssClass.fontFamily};\n`;
                        if (cssClass.color) cssText += `  color: ${cssClass.color};\n`;
                        if (cssClass.backgroundColor) cssText += `  background-color: ${cssClass.backgroundColor};\n`;
                        
                        // Add other styles
                        if (cssClass.allStyles) {
                            for (const [prop, value] of Object.entries(cssClass.allStyles)) {
                                if (!['font-size', 'font-family', 'color', 'background-color'].includes(prop)) {
                                    cssText += `  ${prop}: ${value};\n`;
                                }
                            }
                        }
                        cssText += '}\n';
                    });
                    
                    cssText += '</style>';
                    this.generatedCss = cssText;
                },
                
                updateCssClass(index) {
                    // Regenerate all CSS
                    this.applyCssVariables();
                    this.updatePreview();
                    this.checkOverflow();
                },

                updatePreview() {
                    this.previewHtml = (this.generatedCss || '') + this.currentHtml;
                    this.$nextTick(() => this.checkOverflow());
                },

                switchSheet(index) {
                    this.currentSheet = index;
                    this.currentHtml = this.sheets[index].html || '';
                    this.previewHtml = this.currentHtml;
                    
                    if (this.editor) {
                        const beautified = html_beautify(this.currentHtml, {
                            indent_size: 2,
                            wrap_line_length: 120,
                            preserve_newlines: true,
                            max_preserve_newlines: 2
                        });
                        this.editor.setValue(beautified);
                    }
                    
                    this.cssClasses = [];
                    this.$nextTick(() => {
                        this.applyCssVariables();
                        this.checkOverflow();
                    });
                },

                checkOverflow() {
                    this.$nextTick(() => {
                        const previewContent = this.$refs.previewContent;
                        if (previewContent) {
                            const maxHeight = this.paperSize.height - 40;
                            this.isOverflowing = previewContent.scrollHeight > maxHeight;
                        }
                    });
                },

                saveToHidden() {
                    const updatedContent = {
                        sheets: this.sheets,
                        orientation: this.localOrientation,
                        cssClasses: this.cssClasses,
                        generatedCss: this.generatedCss
                    };
                    
                    // ใช้ $wire.set แทน dispatchEvent เพราะมี wire:ignore
                    this.$wire.set('data.pdf_layout_html', JSON.stringify(updatedContent));
                },
                
                saveChanges() {
                    // อัพเดท currentHtml จาก editor ก่อน
                    if (this.editor) {
                        this.currentHtml = this.editor.getValue();
                        this.sheets[this.currentSheet].html = this.currentHtml;
                    }
                    
                    // รวม generatedCss เข้าไปใน sheets ทุก sheet ก่อน save
                    const sheetsWithCss = this.sheets.map(sheet => ({
                        ...sheet,
                        html: (this.generatedCss || '') + sheet.html
                    }));
                    
                    const updatedContent = {
                        sheets: sheetsWithCss,
                        orientation: this.localOrientation,
                        cssClasses: this.cssClasses,
                        generatedCss: this.generatedCss
                    };
                    
                    // Save orientation
                    this.$wire.set('data.pdf_orientation', this.localOrientation);
                    
                    // Save content
                    this.$wire.set('data.pdf_layout_html', JSON.stringify(updatedContent));
                    
                    // รอให้ Livewire sync ข้อมูล
                    setTimeout(() => {
                        // เรียก save() method ของ page
                        this.$wire.save();
                    }, 100);
                },
            }
        }
    </script>
</x-filament-panels::page>