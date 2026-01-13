window.LuckysheetEditors = window.LuckysheetEditors || {};
window.LuckysheetAreas = window.LuckysheetAreas || {};

function initLuckysheetEditor(wrapperId, config) {
    if (window.LuckysheetEditors[wrapperId]) {
        return;
    }
    window.LuckysheetEditors[wrapperId] = true;

    const formFieldRendererScript = document.createElement('script');
    formFieldRendererScript.src = '/js/form-field-renderer.js';
    if (!document.querySelector('script[src="/js/form-field-renderer.js"]')) {
        document.head.appendChild(formFieldRendererScript);
    }

    const containerId = config.containerId;
    const statusId = config.statusId;
    const fullscreenBtnId = config.fullscreenBtnId;
    const previewBtnId = config.previewBtnId;
    const reloadBtnId = config.reloadBtnId;
    const previewId = config.previewId;
    const previewSheetsId = config.previewSheetsId;
    const fileUrl = config.fileUrl;

    const originalScrollTo = window.scrollTo;
    let scrollBlocked = false;
    window.scrollTo = function (x, y) { if (!scrollBlocked) originalScrollTo.call(window, x, y); };


    function saveContentToField(contentData, statusId) {
        console.log('=== SAVE CONTENT DEBUG ===');
        console.log('Data to save:', contentData);
        
        let contentInput = document.querySelector('[data-content-field="true"]');
        console.log('Method 1 [data-content-field]:', contentInput);
        
        if (!contentInput) {
            contentInput = document.querySelector('textarea[name="content"]');
            console.log('Method 2 [name=content]:', contentInput);
        }
        
        if (!contentInput) {
            contentInput = document.querySelector('textarea[wire\\:model="data.content"]');
            console.log('Method 3 [wire:model]:', contentInput);
        }
        
        if (!contentInput) {
            const allTextareas = document.querySelectorAll('textarea');
            console.log('Total textareas found:', allTextareas.length);
            allTextareas.forEach((ta, i) => {
                console.log(`Textarea ${i}:`, {
                    name: ta.name,
                    id: ta.id,
                    'wire:model': ta.getAttribute('wire:model'),
                    'data-attr': ta.getAttribute('data-content-field')
                });
            });
            
            for (let ta of allTextareas) {
                const name = ta.name || '';
                const wireModel = ta.getAttribute('wire:model') || '';
                if (name.includes('content') || wireModel.includes('content')) {
                    contentInput = ta;
                    console.log('Found by partial match!');
                    break;
                }
            }
        }
        
        if (contentInput) {
            const jsonString = JSON.stringify(contentData);            contentInput.value = jsonString;
            
            contentInput.dispatchEvent(new Event('input', { bubbles: true }));
            contentInput.dispatchEvent(new Event('change', { bubbles: true }));
            
            console.log('✓ SAVED! Length:', jsonString.length);
            document.getElementById(statusId).textContent = "Content saved (" + contentData.sheets.length + " sheets, " + jsonString.length + " chars)";
            return true;
        } else {
            console.error('✗ TEXTAREA NOT FOUND!');
            alert('Error: Cannot find content field. Check console (F12)');
            document.getElementById(statusId).textContent = "Error: Content field not found";
            return false;
        }
    }

    function loadResources(callback) {
        if (window.luckysheet && window.LuckyExcel && window.luckysheetToHtml) {
            callback();
            return;
        }

        const styles = [
            'https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/css/luckysheet.css',
            'https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/plugins/css/pluginsCss.css',
            'https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/plugins/plugins.css',
            'https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/assets/iconfont/iconfont.css'
        ];

        styles.forEach(href => {
            if (!document.querySelector(`link[href="${href}"]`)) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = href;
                document.head.appendChild(link);
            }
        });

        const scripts = [
            'https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/plugins/js/plugin.js',
            'https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/luckysheet.umd.js',
            'https://cdn.jsdelivr.net/npm/luckyexcel/dist/luckyexcel.umd.js',
            '/js/luckysheet-to-html.js'
        ];

        let loaded = 0;
        scripts.forEach((src, index) => {
            if (document.querySelector(`script[src="${src}"]`)) {
                loaded++;
                if (loaded === scripts.length) callback();
                return;
            }
            const script = document.createElement('script');
            script.src = src;
            script.onload = () => {
                loaded++;
                if (loaded === scripts.length) {
                    setTimeout(callback, 100);
                }
            };
            document.head.appendChild(script);
        });
    }

    loadResources(function () {
        document.getElementById(statusId).textContent = "Loading...";
        fetch(fileUrl)
            .then(r => r.ok ? r.arrayBuffer() : Promise.reject("File not found"))
            .then(ab => {
                document.getElementById(statusId).textContent = "Converting...";
                LuckyExcel.transformExcelToLucky(ab, function (exportJson) {
                    if (exportJson.sheets && exportJson.sheets.length > 0) {
                        luckysheet.create({
                            container: containerId,
                            data: exportJson.sheets,
                            showinfobar: false,
                            showsheetbar: true,
                            enableAddRow: true,
                            enableAddCol: true,
                            showToolbar: true,
                            showFormulaBar: true,
                            enableAddBackTop: false,
                            userInfo: false,
                            myFolderUrl: false,
                            devicePixelRatio: 1,
                        });

                        scrollBlocked = true;
                        const container = document.getElementById(containerId);
                        container.addEventListener("mousedown", function (e) { scrollBlocked = true; }, true);
                        document.addEventListener("mouseup", function () { setTimeout(function () { scrollBlocked = false; }, 200); });

                        document.getElementById(statusId).textContent = "Ready";
                    }
                });
            })
            .catch(err => document.getElementById(statusId).textContent = "Error: " + err);

        

        function columnToLetter(col) {
            let letter = '';
            let temp = col;
            while (temp >= 0) {
                letter = String.fromCharCode(65 + (temp % 26)) + letter;
                temp = Math.floor(temp / 26) - 1;
            }
            return letter;
        }

        function getCellReference(row, col) {
            return columnToLetter(col) + (row + 1);
        }

        function cropTableByArea(htmlString, area) {
            if (!area) return htmlString;

            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = htmlString;
            const table = tempDiv.querySelector('table');

            if (!table) return htmlString;

            const colgroup = table.querySelector('colgroup');
            const rows = Array.from(table.querySelectorAll('tr'));
            const startRow = area.row[0];
            const endRow = area.row[1];
            const startCol = area.column[0];
            const endCol = area.column[1];

            if (colgroup) {
                const cols = Array.from(colgroup.querySelectorAll('col'));
                cols.forEach((col, index) => {
                    if (index < startCol || index > endCol) {
                        col.remove();
                    }
                });
            }

            rows.forEach((row, rowIndex) => {
                if (rowIndex < startRow || rowIndex > endRow) {
                    row.remove();
                } else {
                    const cells = Array.from(row.querySelectorAll('td, th'));
                    let cellIndex = 0;
                    cells.forEach((cell) => {
                        const colspan = parseInt(cell.getAttribute('colspan') || '1');

                        if (cellIndex < startCol || cellIndex > endCol) {
                            cell.remove();
                        }

                        cellIndex += colspan;
                    });
                }
            });

            return tempDiv.innerHTML;
        }

        function generatePreview() {
            if (typeof luckysheetToHtml !== "function") {
                document.getElementById(statusId).textContent = "Error: Converter not loaded";
                return;
            }
            document.getElementById(statusId).textContent = "Generating HTML...";
            try {
                const sheets = luckysheetToHtml();
                const container = document.getElementById(previewSheetsId);
                container.innerHTML = "";


                

                let allHtml = "";

                const style = document.createElement('style');
                style.textContent = `
                    .sheet-wrapper { margin-bottom: 30px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; background: white; position: relative; }
                    .sheet-wrapper:last-child { margin-bottom: 0; }
                    .area-badge { position: absolute; top: 10px; right: 10px; background: #10b981; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; }
                    .preview-tabs { display: flex; border-bottom: 2px solid #e5e7eb; margin-bottom: 20px; align-items: center; flex-wrap: wrap; gap: 8px; }
                    .preview-tab-btn { padding: 12px 24px; border: none; background: none; cursor: pointer; font-weight: 500; color: #6b7280; border-bottom: 3px solid transparent; transition: all 0.2s; }
                    .preview-tab-btn.active { color: #3b82f6; border-bottom-color: #3b82f6; }
                    .preview-tab-btn:hover:not(.active) { color: #374151; background: #f9fafb; }
                    .zoom-controls { margin-left: auto; display: flex; gap: 8px; align-items: center; }
                    .zoom-btn { width: 32px; height: 32px; border: 1px solid #d1d5db; background: white; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; color: #374151; transition: all 0.2s; display: flex; align-items: center; justify-content: center; }
                    .zoom-btn:hover { background: #f3f4f6; border-color: #9ca3af; }
                    .zoom-level { font-size: 14px; color: #6b7280; min-width: 50px; text-align: center; }
                    .tab-content { display: none; }
                    .tab-content.active { display: block; }
                    .preview-content {  max-height: 600px; overflow: auto;  border: 1px solid #e5e7eb;  border-radius: 6px;  padding: 16px;   background: #fafafa;  }
                    .preview-content table td { line-height: 1.3; }
                    .preview-zoom-wrapper {  transform-origin: top left;  transition: transform 0.2s;}
                `;
                container.appendChild(style);

                const allSheetsData = [];

                sheets.forEach(function (sheet, index) {
                    const sheetArea = window.LuckysheetAreas[sheet.name];
                    let processedHtml = sheet.html;

                    if (sheetArea) {
                        processedHtml = cropTableByArea(sheet.html, sheetArea);
                    }

                    allSheetsData.push({
                        name: sheet.name,
                        html: processedHtml,
                        
                        area: sheetArea || null
                    });

                    allHtml += processedHtml;

                    const sheetWrapper = document.createElement('div');
                    sheetWrapper.className = 'sheet-wrapper';

                    if (sheetArea) {
                        const badge = document.createElement('div');
                        badge.className = 'area-badge';
                        badge.textContent = 'Area Set';
                        badge.title = 'Row ' + (sheetArea.row[0] + 1) + '-' + (sheetArea.row[1] + 1) + ', Col ' + (sheetArea.column[0] + 1) + '-' + (sheetArea.column[1] + 1);
                        sheetWrapper.appendChild(badge);
                    }

                    const title = document.createElement('h3');
                    title.className = 'font-semibold text-lg mb-4 text-gray-800';
                    title.textContent = 'Page: ' + sheet.name;
                    sheetWrapper.appendChild(title);

                    const tabsDiv = document.createElement('div');
                    tabsDiv.className = 'preview-tabs';

                    const previewBtn = document.createElement('button');
                    previewBtn.type = 'button';
                    previewBtn.className = 'preview-tab-btn active';
                    previewBtn.textContent = 'Preview';

                    const sourceBtn = document.createElement('button');
                    sourceBtn.type = 'button';
                    sourceBtn.className = 'preview-tab-btn';
                    sourceBtn.textContent = 'Source Code';

                    const zoomControls = document.createElement('div');
                    zoomControls.className = 'zoom-controls';

                    const zoomOutBtn = document.createElement('button');
                    zoomOutBtn.type = 'button';
                    zoomOutBtn.className = 'zoom-btn';
                    zoomOutBtn.innerHTML = '−';
                    zoomOutBtn.title = 'Zoom Out';

                    const zoomLevel = document.createElement('span');
                    zoomLevel.className = 'zoom-level';
                    zoomLevel.textContent = '100%';

                    const zoomInBtn = document.createElement('button');
                    zoomInBtn.type = 'button';
                    zoomInBtn.className = 'zoom-btn';
                    zoomInBtn.innerHTML = '+';
                    zoomInBtn.title = 'Zoom In';

                    const zoomResetBtn = document.createElement('button');
                    zoomResetBtn.type = 'button';
                    zoomResetBtn.className = 'zoom-btn';
                    zoomResetBtn.innerHTML = '⟲';
                    zoomResetBtn.title = 'Reset Zoom';
                    zoomResetBtn.style.fontSize = '18px';

                    zoomControls.appendChild(zoomOutBtn);
                    zoomControls.appendChild(zoomLevel);
                    zoomControls.appendChild(zoomInBtn);
                    zoomControls.appendChild(zoomResetBtn);

                    const previewTabContent = document.createElement('div');
                    previewTabContent.className = 'tab-content active preview-content';

                    const previewZoomWrapper = document.createElement('div');
                    previewZoomWrapper.className = 'preview-zoom-wrapper';
                    previewZoomWrapper.innerHTML = renderFormFields(processedHtml);
                    previewTabContent.appendChild(previewZoomWrapper);

                    const sourceTabContent = document.createElement('div');
                    sourceTabContent.className = 'tab-content';

                    const textarea = document.createElement('textarea');
                    textarea.className = 'w-full p-4 border rounded-lg font-mono text-sm bg-gray-50';
                    textarea.style.minHeight = '400px';
                    textarea.style.fontFamily = 'monospace';
                    textarea.spellcheck = false;
                    textarea.value = processedHtml;

                    let currentZoom = 1.0;

                    // แก้ไข: สร้างฟังก์ชัน updateZoom ให้รับพารามิเตอร์ wrapper และ table โดยตรง
                    function updateZoom(newZoom, targetWrapper) {
                        currentZoom = Math.max(0.25, Math.min(2.0, newZoom));
                        const table = targetWrapper.querySelector('table');
                        
                        if (table) {
                            // 1. Reset เพื่อวัดขนาดจริงของ Sheet นั้นๆ
                            targetWrapper.style.transform = 'scale(1)';
                            targetWrapper.style.width = 'auto';
                            targetWrapper.style.height = 'auto';
                            
                            // Force reflow เฉพาะตัวที่กำลังจัดการ
                            void targetWrapper.offsetWidth;

                            // 2. คำนวณขนาดจาก colgroup ของตารางนี้เท่านั้น
                            const colgroup = table.querySelector('colgroup');
                            let actualWidth = 0;
                            if (colgroup) {
                                const cols = colgroup.querySelectorAll('col');
                                cols.forEach(function (col) {
                                    const colWidth = col.style.width;
                                    actualWidth += parseFloat(colWidth) || 0;
                                });
                            }

                            if (actualWidth === 0) {
                                actualWidth = table.offsetWidth;
                            }
                            const actualHeight = table.offsetHeight;

                            table.style.width = actualWidth + 'px';
                            table.style.minWidth = actualWidth + 'px';
                            table.style.margin = '0';

                            // 3. ปรับขนาดกรอบตามเงื่อนไขที่ต้องการ
                            targetWrapper.style.transformOrigin = 'top left';
                            targetWrapper.style.transform = 'scale(' + currentZoom + ')';

                            if (currentZoom < 1) {
                                // ซูมออก: หดกรอบตามสัดส่วน
                                targetWrapper.style.width = (actualWidth * currentZoom) + 'px';
                                targetWrapper.style.height = (actualHeight * currentZoom) + 'px';
                            } else {
                                // ซูมเข้า: ล็อคกรอบไว้ที่ 100% ไม่ให้ล้น layout
                                targetWrapper.style.width = actualWidth + 'px';
                                targetWrapper.style.height = actualHeight + 'px';
                            }

                            // ล็อคความกว้างตารางข้างในไม่ให้เพี้ยน
                            table.style.width = actualWidth + 'px';
                            table.style.minWidth = actualWidth + 'px';
                            table.style.margin = '0';
                        }
                        
                        // อัปเดตตัวเลข % ในชุดควบคุมของ sheet นั้นๆ
                        const currentZoomLevelLabel = targetWrapper.closest('.sheet-wrapper').querySelector('.zoom-level');
                        if (currentZoomLevelLabel) {
                            currentZoomLevelLabel.textContent = Math.round(currentZoom * 100) + '%';
                        }
                    }

                    // แก้ไขส่วนการเรียกใช้ Event Listeners ของปุ่ม
                    zoomInBtn.onclick = function () { 
                        updateZoom(currentZoom + 0.1, previewZoomWrapper); 
                    };
                    zoomOutBtn.onclick = function () { 
                        updateZoom(currentZoom - 0.1, previewZoomWrapper); 
                    };
                    zoomResetBtn.onclick = function () { 
                        currentZoom = 1.0;
                        updateZoom(1.0, previewZoomWrapper); 
                    };

                    previewBtn.onclick = function () {
                        previewBtn.classList.add('active');
                        sourceBtn.classList.remove('active');
                        previewTabContent.classList.add('active');
                        sourceTabContent.classList.remove('active');
                        zoomControls.style.display = 'flex';
                    };

                    sourceBtn.onclick = function () {
                        previewBtn.classList.remove('active');
                        sourceBtn.classList.add('active');
                        previewTabContent.classList.remove('active');
                        sourceTabContent.classList.add('active');
                        zoomControls.style.display = 'none';
                    };

                    textarea.addEventListener('input', function () {
                        const newContent = renderFormFields(this.value);

                        previewZoomWrapper.innerHTML = newContent;
                        setTimeout(function () { updateZoom(currentZoom); }, 10);

                        sheets[index].html = this.value;

                        const updatedSheetsData = sheets.map(function (s) {
                            const area = window.LuckysheetAreas[s.name];
                            const processedHtml = area ? cropTableByArea(s.html, area) : s.html;
                            return {
                                name: s.name,
                                html: processedHtml,
                                
                                area: area || null
                            };
                        });

                        const contentData = {
                            sheets: updatedSheetsData,
                            generated_at: new Date().toISOString()
                        };

                        saveContentToField(contentData, statusId);
                    });

                    sourceTabContent.appendChild(textarea);

                    tabsDiv.appendChild(previewBtn);
                    tabsDiv.appendChild(sourceBtn);
                    tabsDiv.appendChild(zoomControls);
                    sheetWrapper.appendChild(tabsDiv);
                    sheetWrapper.appendChild(previewTabContent);
                    sheetWrapper.appendChild(sourceTabContent);

                    container.appendChild(sheetWrapper);

                    setTimeout(function () { updateZoom(1.0); }, 50);
                });

                const contentData = {
                    sheets: allSheetsData,
                    generated_at: new Date().toISOString()
                };

                saveContentToField(contentData, statusId);

                document.getElementById(previewId).style.display = "block";
                document.getElementById(previewBtnId).style.display = "none";
                document.getElementById(reloadBtnId).style.display = "inline-flex";
                document.getElementById(statusId).textContent = "Preview ready";

            } catch (e) {
                document.getElementById(statusId).textContent = "Error: " + e.message;
                console.error(e);
            }
        }

        document.getElementById(previewBtnId).addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            generatePreview();
        });

        document.getElementById(reloadBtnId).addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            generatePreview();
        });

        let isFullscreen = false;
        let originalStyle = "";
        let originalParent = null;
        let originalNextSibling = null;
        const fullscreenBtn = document.getElementById(fullscreenBtnId);
        let sidebarMenu = null;

        function createFormFieldButton(label, fieldType) {
            const btn = document.createElement("button");
            btn.className = "form-field-btn";
            btn.textContent = label;
            btn.onclick = function () {
                insertField(fieldType);
            };
            return btn;
        }

        function createAreaButton() {
            const btn = document.createElement("button");
            btn.className = "form-field-btn";
            btn.style.background = "#10b981";
            btn.textContent = "📐 Set Area";
            btn.onclick = function () {
                setArea();
            };
            return btn;
        }

        function updateSidebarAreaInfo() {
            if (!sidebarMenu) return;

            const existingInfo = sidebarMenu.querySelector('.area-info-section');
            if (existingInfo) {
                existingInfo.remove();
            }

            const allSheets = luckysheet.getAllSheets();

            const areaInfoSection = document.createElement("div");
            areaInfoSection.className = "menu-section area-info-section";
            areaInfoSection.style.maxHeight = "300px";
            areaInfoSection.style.overflowY = "auto";

            const areaInfoTitle = document.createElement("div");
            areaInfoTitle.className = "menu-title";
            areaInfoTitle.textContent = "Area Status";
            areaInfoSection.appendChild(areaInfoTitle);

            allSheets.forEach(function (sheet) {
                const sheetArea = window.LuckysheetAreas[sheet.name];

                const sheetInfo = document.createElement("div");
                sheetInfo.style.cssText = "padding:8px 12px;margin-bottom:8px;background:#374151;border-radius:6px;font-size:12px;line-height:1.4;";

                const sheetNameDiv = document.createElement("div");
                sheetNameDiv.style.cssText = "color:#f3f4f6;font-weight:600;margin-bottom:4px;";
                sheetNameDiv.textContent = sheet.name;
                sheetInfo.appendChild(sheetNameDiv);

                const areaStatusDiv = document.createElement("div");
                if (sheetArea) {
                    areaStatusDiv.style.color = "#10b981";
                    areaStatusDiv.innerHTML = "✓ R" + (sheetArea.row[0] + 1) + "-" + (sheetArea.row[1] + 1) +
                        " C" + (sheetArea.column[0] + 1) + "-" + (sheetArea.column[1] + 1);
                } else {
                    areaStatusDiv.style.color = "#9ca3af";
                    areaStatusDiv.textContent = "○ Full sheet";
                }
                sheetInfo.appendChild(areaStatusDiv);

                areaInfoSection.appendChild(sheetInfo);
            });

            sidebarMenu.appendChild(areaInfoSection);
        }

        function setArea() {
            const range = luckysheet.getRange();
            if (!range || range.length === 0) {
                alert('Please select a cell range first');
                return;
            }

            const selection = range[0];
            const currentSheet = luckysheet.getSheet();
            const sheetName = currentSheet.name;

            const area = {
                row: [selection.row[0], selection.row[1]],
                column: [selection.column[0], selection.column[1]]
            };

            window.LuckysheetAreas[sheetName] = area;

            const rowRange = 'Row ' + (area.row[0] + 1) + '-' + (area.row[1] + 1);
            const colRange = 'Col ' + (area.column[0] + 1) + '-' + (area.column[1] + 1);

            updateSidebarAreaInfo();

            alert('Area set for "' + sheetName + '":\n' + rowRange + '\n' + colRange + '\n\nClick "Reload HTML" to apply changes.');
        }

        function insertField(fieldType) {
            const range = luckysheet.getRange();
            if (!range || range.length === 0) {
                alert('Please select a cell first');
                return;
            }

            const selection = range[0];
            const row = selection.row[0];
            const col = selection.column[0];
            const rowEnd = selection.row[1];
            const colEnd = selection.column[1];

            showFieldDialog(fieldType, row, col, rowEnd, colEnd);
        }

        function showFieldDialog(fieldType, row, col, rowEnd, colEnd) {
            const fieldName = fieldType.match(/\[(.*?)\s/)[1];
            const cellRef = getCellReference(row, col);
            const defaultName = fieldName + '-field-' + cellRef;
            const isSelect = fieldName === 'select';

            const dialog = document.createElement('div');
            dialog.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:white;padding:20px;border-radius:8px;box-shadow:0 4px 6px rgba(0,0,0,0.3);z-index:999;min-width:300px;max-width:500px;max-height:90vh;overflow-y:auto;';

            let html = '<h3 style="margin:0 0 15px 0;font-size:16px;font-weight:bold;">Configure Field</h3>';
            html += '<div style="margin-bottom:15px;"><label style="display:block;margin-bottom:5px;font-size:14px;">Field Name:</label><input type="text" id="field_name_input" value="' + defaultName + '" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;"></div>';

            if (isSelect) {
                html += '<div style="margin-bottom:15px;"><label style="display:block;margin-bottom:5px;font-size:14px;">Options (one per line):</label><textarea id="field_options_input" rows="6" placeholder="Option 1\nOption 2\nOption 3" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;font-family:monospace;font-size:13px;">Option 1\nOption 2\nOption 3</textarea></div>';
                html += '<div style="margin-bottom:15px;"><label style="display:flex;align-items:center;font-size:14px;cursor:pointer;"><input type="checkbox" id="field_first_as_label_input" style="margin-right:8px;"> First option as label (not a value)</label></div>';
            }

            html += '<div style="margin-bottom:15px;"><label style="display:flex;align-items:center;font-size:14px;cursor:pointer;"><input type="checkbox" id="field_required_input" style="margin-right:8px;"> Required field</label></div>';
            html += '<div style="display:flex;gap:10px;justify-content:flex-end;"><button id="cancel_btn" style="padding:8px 16px;background:#6B7280;color:white;border:none;border-radius:4px;cursor:pointer;">Cancel</button><button id="ok_btn" style="padding:8px 16px;background:#2563EB;color:white;border:none;border-radius:4px;cursor:pointer;">OK</button></div>';

            dialog.innerHTML = html;
            document.body.appendChild(dialog);

            const nameInput = dialog.querySelector('#field_name_input');
            const requiredInput = dialog.querySelector('#field_required_input');
            const optionsInput = isSelect ? dialog.querySelector('#field_options_input') : null;
            const firstAsLabelInput = isSelect ? dialog.querySelector('#field_first_as_label_input') : null;
            const okBtn = dialog.querySelector('#ok_btn');
            const cancelBtn = dialog.querySelector('#cancel_btn');

            nameInput.focus();
            nameInput.select();

            function closeDialog() {
                document.body.removeChild(dialog);
            }

            cancelBtn.onclick = closeDialog;

            okBtn.onclick = function () {
                const name = nameInput.value.trim() || defaultName;
                const required = requiredInput.checked;
                const options = isSelect ? optionsInput.value.split('\n').map(opt => opt.trim()).filter(opt => opt.length > 0) : null;
                const firstAsLabel = isSelect ? firstAsLabelInput.checked : false;

                const shortcode = buildShortcode(fieldType, name, required, options, firstAsLabel);

                luckysheet.setCellValue(row, col, shortcode);

                const range = [row, col, rowEnd, colEnd];
                setTimeout(function () {
                    luckysheet.setRangeFormat("ht", 0, range);
                    luckysheet.setRangeFormat("vt", 0, range);
                    luckysheet.setRangeFormat("tb", 2, range);
                    luckysheet.setRangeFormat("bl", 1, range);
                    luckysheet.setRangeFormat("fs", 12, range);
                    luckysheet.setRangeFormat("bg", "#F3F4F6", range);
                }, 50);

                closeDialog();
            };

            nameInput.addEventListener('keypress', function (e) {
                if (e.key === 'Enter' && !isSelect) {
                    okBtn.click();
                }
            });
        }

        function buildShortcode(fieldType, name, required, options, firstAsLabel) {
            const field = fieldType.match(/\[(.*?)\s/)[1];
            const requiredMark = required ? '*' : '';

            // Get current cell reference
            const range = luckysheet.getRange();
            let cellRef = '';
            if (range && range.length > 0) {
                const selection = range[0];
                const row = selection.row[0];
                const col = selection.column[0];
                cellRef = getCellReference(row, col);
            }

            const cellAttr = cellRef ? ' cell="' + cellRef + '"' : '';

            if (field === 'select' && options && options.length > 0) {
                const optionsStr = options.map(opt => '"' + opt + '"').join(' ');
                const firstAsLabelStr = firstAsLabel ? 'first_as_label ' : '';
                return '[' + field + requiredMark + ' ' + name + cellAttr + ' ' + firstAsLabelStr + optionsStr + ']';
            }

            return '[' + field + requiredMark + ' ' + name + cellAttr + ']';
        }

        function toggleFullscreen() {
            const container = document.getElementById(containerId);
            if (!isFullscreen) {
                originalParent = container.parentNode;
                originalNextSibling = container.nextSibling;
                originalStyle = container.getAttribute("style");
                sidebarMenu = document.createElement("div");
                sidebarMenu.style.cssText = 'position:fixed;top:0;left:0;width:220px;height:100vh;background:#1f2937;z-index:998;padding:20px;box-sizing:border-box;overflow-y:auto;';

                const returnBtn = document.createElement("button");
                returnBtn.textContent = "← Return";
                returnBtn.className = "inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-500 w-full justify-center";
                returnBtn.style.marginBottom = "20px";
                returnBtn.onclick = toggleFullscreen;
                sidebarMenu.appendChild(returnBtn);

                const formFieldsSection = document.createElement("div");
                formFieldsSection.className = "menu-section";
                const formFieldsTitle = document.createElement("div");
                formFieldsTitle.className = "menu-title";
                formFieldsTitle.textContent = "Form Fields";
                formFieldsSection.appendChild(formFieldsTitle);

                formFieldsSection.appendChild(createFormFieldButton("📝 Text", "[text your-name]"));
                formFieldsSection.appendChild(createFormFieldButton("📧 Email", "[email your-email]"));
                formFieldsSection.appendChild(createFormFieldButton("📱 Phone", "[tel your-phone]"));
                formFieldsSection.appendChild(createFormFieldButton("🔢 Number", "[number your-number]"));
                formFieldsSection.appendChild(createFormFieldButton("☑️ Checkbox", "[checkbox your-checkbox]"));
                formFieldsSection.appendChild(createFormFieldButton("📋 Dropdown", "[select your-dropdown]"));
                formFieldsSection.appendChild(createFormFieldButton("📄 Textarea", "[textarea your-message]"));
                formFieldsSection.appendChild(createFormFieldButton("📅 Date", "[date your-date]"));
                formFieldsSection.appendChild(createFormFieldButton("✍️ Signature", "[signature your-signature]"));

                sidebarMenu.appendChild(formFieldsSection);

                const areaSection = document.createElement("div");
                areaSection.className = "menu-section";
                const areaTitle = document.createElement("div");
                areaTitle.className = "menu-title";
                areaTitle.textContent = "Preview Area";
                areaSection.appendChild(areaTitle);
                areaSection.appendChild(createAreaButton());
                sidebarMenu.appendChild(areaSection);

                document.body.appendChild(sidebarMenu);
                document.body.appendChild(container);

                container.style.cssText = 'position:fixed;top:0;left:220px;width:calc(100vw - 220px);height:100vh;margin:0;border:none;background:#fff;z-index:2;';

                updateSidebarAreaInfo();
                fullscreenBtn.textContent = "Exit Fullscreen";
                fullscreenBtn.style.backgroundColor = "#DC2626";
                fullscreenBtn.style.color = "#FFF";
                isFullscreen = true;
                setTimeout(function () {
                    luckysheet.resize();
                }, 100);
            } else {
                if (sidebarMenu) {
                    document.body.removeChild(sidebarMenu);
                    sidebarMenu = null;
                }

                document.body.removeChild(container);

                if (originalNextSibling) {
                    originalParent.insertBefore(container, originalNextSibling);
                } else {
                    originalParent.appendChild(container);
                }

                container.setAttribute("style", originalStyle);
                fullscreenBtn.textContent = "Fullscreen";
                fullscreenBtn.style.backgroundColor = "#4B5563";
                fullscreenBtn.style.color = "#FFF";
                isFullscreen = false;
                setTimeout(function () {
                    luckysheet.resize();
                }, 100);
            }
        }

        fullscreenBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleFullscreen();
        });
    });
}