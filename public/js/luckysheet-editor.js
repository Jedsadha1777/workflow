window.LuckysheetEditors = window.LuckysheetEditors || {};

function initLuckysheetEditor(wrapperId, config) {
    if (window.LuckysheetEditors[wrapperId]) {
        return;
    }
    window.LuckysheetEditors[wrapperId] = true;

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
    window.scrollTo = function(x, y) { if (!scrollBlocked) originalScrollTo.call(window, x, y); };

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

    loadResources(function() {
        document.getElementById(statusId).textContent = "Loading...";
        fetch(fileUrl)
            .then(r => r.ok ? r.arrayBuffer() : Promise.reject("File not found"))
            .then(ab => {
                document.getElementById(statusId).textContent = "Converting...";
                LuckyExcel.transformExcelToLucky(ab, function(exportJson) {
                    if (exportJson.sheets && exportJson.sheets.length > 0) {
                        luckysheet.create({
                            container: containerId,
                            data: exportJson.sheets,
                            showinfobar: false,
                            showsheetbar: true,
                            enableAddRow: true,
                            enableAddCol: true,
                        });

                        scrollBlocked = true;
                        const container = document.getElementById(containerId);
                        container.addEventListener("mousedown", function(e) { scrollBlocked = true; }, true);
                        document.addEventListener("mouseup", function() { setTimeout(function() { scrollBlocked = false; }, 200); });

                        document.getElementById(statusId).textContent = "Ready";
                    }
                });
            })
            .catch(err => document.getElementById(statusId).textContent = "Error: " + err);

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
                sheets.forEach(function(sheet) {
                    const sheetDiv = document.createElement("div");
                    sheetDiv.className = "border rounded-lg p-4 bg-white mb-4";
                    sheetDiv.style.maxHeight = "600px";
                    sheetDiv.style.overflow = "auto";
                    const title = document.createElement("h4");
                    title.className = "font-semibold text-base mb-3 text-gray-700";
                    title.textContent = "Page: " + sheet.name;
                    const content = document.createElement("div");
                    content.innerHTML = sheet.html;
                    sheetDiv.appendChild(title);
                    sheetDiv.appendChild(content);
                    container.appendChild(sheetDiv);
                    allHtml += sheet.html;
                });
                document.getElementById(previewId).style.display = "block";
                document.getElementById(previewBtnId).style.display = "none";
                document.getElementById(reloadBtnId).style.display = "inline-flex";
                document.getElementById(statusId).textContent = "Preview ready";
                const contentInput = document.querySelector("[name=content]");
                if (contentInput) contentInput.value = allHtml;
            } catch (e) {
                document.getElementById(statusId).textContent = "Error: " + e.message;
                console.error(e);
            }
        }

        document.getElementById(previewBtnId).addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
            generatePreview();
        });

        document.getElementById(reloadBtnId).addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
            generatePreview();
        });

        let isFullscreen = false;
        let originalStyle = "";
        const fullscreenBtn = document.getElementById(fullscreenBtnId);
        let sidebarMenu = null;
        let draggedField = null;

        function createFormFieldButton(label, shortcode) {
            const btn = document.createElement("button");
            btn.className = "form-field-btn";
            btn.textContent = label;
            btn.draggable = true;
            btn.dataset.shortcode = shortcode;
            btn.addEventListener("dragstart", function(e) {
                draggedField = shortcode;
                e.dataTransfer.effectAllowed = "copy";
            });
            btn.addEventListener("dragend", function(e) {
                draggedField = null;
            });
            return btn;
        }

        function setupDropZone() {
            const container = document.getElementById(containerId);
            const lucksheetContainer = container.querySelector('#luckysheet-cell-main');
            if (!lucksheetContainer) return;

            let highlightOverlay = document.createElement('div');
            highlightOverlay.id = 'drop-highlight-' + containerId;
            highlightOverlay.style.cssText = 'position:absolute;border:2px solid #2563EB;background:rgba(37,99,235,0.1);pointer-events:none;display:none;z-index:1000;';
            lucksheetContainer.appendChild(highlightOverlay);

            let lastRow = -1;
            let lastCol = -1;

            function getCellFromPoint(x, y) {
                // ซ่อน highlight overlay ชั่วคราวเพื่อไม่ให้บัง
                highlightOverlay.style.display = 'none';
                
                // ใช้ DOM เพื่อหา cell ที่ตำแหน่ง x, y
                const scrollContainer = lucksheetContainer.querySelector('#luckysheet-cell-main');
                if (!scrollContainer) return null;

                // หา element ที่ตำแหน่งนี้
                const elements = document.elementsFromPoint(
                    lucksheetContainer.getBoundingClientRect().left + x - lucksheetContainer.scrollLeft,
                    lucksheetContainer.getBoundingClientRect().top + y - lucksheetContainer.scrollTop
                );

                let cellElement = null;
                for (let el of elements) {
                    // หา cell element (luckysheet-cell-element)
                    if (el.classList && el.classList.contains('luckysheet-cell-element')) {
                        cellElement = el;
                        break;
                    }
                }

                if (!cellElement) return null;

                // ดึง row, col จาก attribute
                const row = parseInt(cellElement.getAttribute('data-row') || cellElement.getAttribute('r'));
                const col = parseInt(cellElement.getAttribute('data-col') || cellElement.getAttribute('c'));

                if (isNaN(row) || isNaN(col)) return null;

                // ดึง position และ dimension จาก DOM
                const rect = cellElement.getBoundingClientRect();
                const containerRect = lucksheetContainer.getBoundingClientRect();

                return {
                    row: row,
                    col: col,
                    x: rect.left - containerRect.left + lucksheetContainer.scrollLeft,
                    y: rect.top - containerRect.top + lucksheetContainer.scrollTop,
                    width: rect.width,
                    height: rect.height
                };
            }

            function getCellPosition(x, y) {
                // วิธีที่ 1: ลองใช้ elementsFromPoint
                const cellData = getCellFromPoint(x, y);
                if (cellData) return cellData;

                // วิธีที่ 2: คำนวณจาก config แต่ใช้ค่าจริง
                const flowdata = luckysheet.getSheetData();
                if (!flowdata || flowdata.length === 0) return null;

                const config = luckysheet.getConfig();
                const merge = config.merge || {};
                const rowlen = config.rowlen || {};
                const columnlen = config.columnlen || {};

                // ใช้ค่า default จริงๆ ของ Luckysheet
                const defaultRowHeight = config.defaultRowHeight || 19;
                const defaultColWidth = config.defaultColWidth || 73;

                function buildMergeLookup(merge) {
                    const lookup = {};
                    Object.keys(merge).forEach(key => {
                        const m = merge[key];
                        const cellKey = m.r + '_' + m.c;
                        lookup[cellKey] = m;
                    });
                    return lookup;
                }

                const mergeLookup = buildMergeLookup(merge);
                const skipCells = {};

                Object.keys(mergeLookup).forEach(key => {
                    const m = mergeLookup[key];
                    for (let mr = m.r; mr < m.r + m.rs; mr++) {
                        for (let mc = m.c; mc < m.c + m.cs; mc++) {
                            if (mr !== m.r || mc !== m.c) {
                                skipCells[mr + '_' + mc] = true;
                            }
                        }
                    }
                });

                let row_index = -1;
                let currentY = 0;
                for (let r = 0; r < flowdata.length; r++) {
                    const height = rowlen[r] !== undefined ? rowlen[r] : defaultRowHeight;
                    if (y >= currentY && y < currentY + height) {
                        row_index = r;
                        break;
                    }
                    currentY += height;
                }

                if (row_index === -1) return null;

                let col_index = -1;
                let currentX = 0;
                const maxCols = Math.max(...flowdata.map(row => row ? row.length : 0));
                
                for (let c = 0; c < maxCols; c++) {
                    const cellKey = row_index + '_' + c;
                    
                    if (skipCells[cellKey]) {
                        continue;
                    }

                    const width = columnlen[c] !== undefined ? columnlen[c] : defaultColWidth;
                    const mergeInfo = mergeLookup[cellKey];
                    let totalWidth = width;
                    
                    if (mergeInfo) {
                        totalWidth = 0;
                        for (let mc = c; mc < c + mergeInfo.cs; mc++) {
                            totalWidth += columnlen[mc] !== undefined ? columnlen[mc] : defaultColWidth;
                        }
                    }
                    
                    if (x >= currentX && x < currentX + totalWidth) {
                        col_index = c;
                        break;
                    }
                    
                    currentX += totalWidth;
                }

                if (col_index === -1) return null;

                const cellKey = row_index + '_' + col_index;
                const mergeInfo = mergeLookup[cellKey];
                
                let cellX = 0;
                for (let c = 0; c < col_index; c++) {
                    const skipKey = row_index + '_' + c;
                    if (!skipCells[skipKey]) {
                        const checkMerge = mergeLookup[row_index + '_' + c];
                        if (checkMerge) {
                            for (let mc = c; mc < c + checkMerge.cs; mc++) {
                                cellX += columnlen[mc] !== undefined ? columnlen[mc] : defaultColWidth;
                            }
                        } else {
                            cellX += columnlen[c] !== undefined ? columnlen[c] : defaultColWidth;
                        }
                    }
                }

                let cellY = 0;
                for (let r = 0; r < row_index; r++) {
                    cellY += rowlen[r] !== undefined ? rowlen[r] : defaultRowHeight;
                }

                let cellWidth = columnlen[col_index] !== undefined ? columnlen[col_index] : defaultColWidth;
                let cellHeight = rowlen[row_index] !== undefined ? rowlen[row_index] : defaultRowHeight;

                if (mergeInfo) {
                    cellWidth = 0;
                    for (let mc = col_index; mc < col_index + mergeInfo.cs; mc++) {
                        cellWidth += columnlen[mc] !== undefined ? columnlen[mc] : defaultColWidth;
                    }
                    cellHeight = 0;
                    for (let mr = row_index; mr < row_index + mergeInfo.rs; mr++) {
                        cellHeight += rowlen[mr] !== undefined ? rowlen[mr] : defaultRowHeight;
                    }
                }

                return {
                    row: row_index,
                    col: col_index,
                    x: cellX,
                    y: cellY,
                    width: cellWidth,
                    height: cellHeight
                };
            }

            lucksheetContainer.addEventListener("dragover", function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = "copy";

                const rect = lucksheetContainer.getBoundingClientRect();
                const scrollLeft = lucksheetContainer.scrollLeft;
                const scrollTop = lucksheetContainer.scrollTop;
                
                const x = e.clientX - rect.left + scrollLeft;
                const y = e.clientY - rect.top + scrollTop;

                const cellPos = getCellPosition(x, y);
                
                if (cellPos && (cellPos.row !== lastRow || cellPos.col !== lastCol)) {
                    lastRow = cellPos.row;
                    lastCol = cellPos.col;
                    
                    highlightOverlay.style.left = cellPos.x + 'px';
                    highlightOverlay.style.top = cellPos.y + 'px';
                    highlightOverlay.style.width = cellPos.width + 'px';
                    highlightOverlay.style.height = cellPos.height + 'px';
                    highlightOverlay.style.display = 'block';
                } else if (!cellPos) {
                    highlightOverlay.style.display = 'none';
                    lastRow = -1;
                    lastCol = -1;
                }
            });

            lucksheetContainer.addEventListener("dragleave", function(e) {
                highlightOverlay.style.display = 'none';
                lastRow = -1;
                lastCol = -1;
            });

            lucksheetContainer.addEventListener("drop", function(e) {
                e.preventDefault();
                e.stopPropagation();
                highlightOverlay.style.display = 'none';
                
                if (!draggedField) return;

                const rect = lucksheetContainer.getBoundingClientRect();
                const scrollLeft = lucksheetContainer.scrollLeft;
                const scrollTop = lucksheetContainer.scrollTop;
                
                const x = e.clientX - rect.left + scrollLeft;
                const y = e.clientY - rect.top + scrollTop;

                const cellPos = getCellPosition(x, y);

                if (cellPos) {
                    showFieldDialog(draggedField, cellPos.row, cellPos.col);
                }
                
                lastRow = -1;
                lastCol = -1;
            });
        }

        function showFieldDialog(fieldType, row, col) {
            const fieldName = fieldType.match(/\[(.*?)\s/)[1];
            const defaultName = fieldName + '-field';
            const dialog = document.createElement('div');
            dialog.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:white;padding:20px;border-radius:8px;box-shadow:0 4px 6px rgba(0,0,0,0.3);z-index:999999;min-width:300px;';

            let html = '<h3 style="margin:0 0 15px 0;font-size:16px;font-weight:bold;">Configure Field</h3>';
            html += '<div style="margin-bottom:15px;"><label style="display:block;margin-bottom:5px;font-size:14px;">Field Name:</label><input type="text" id="field_name_input" value="' + defaultName + '" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;"></div>';
            html += '<div style="margin-bottom:15px;"><label style="display:flex;align-items:center;font-size:14px;cursor:pointer;"><input type="checkbox" id="field_required_input" style="margin-right:8px;"> Required field</label></div>';
            html += '<div style="display:flex;gap:10px;justify-content:flex-end;"><button id="cancel_btn" style="padding:8px 16px;background:#6B7280;color:white;border:none;border-radius:4px;cursor:pointer;">Cancel</button><button id="ok_btn" style="padding:8px 16px;background:#2563EB;color:white;border:none;border-radius:4px;cursor:pointer;">OK</button></div>';

            dialog.innerHTML = html;
            document.body.appendChild(dialog);

            const nameInput = dialog.querySelector('#field_name_input');
            const requiredInput = dialog.querySelector('#field_required_input');
            const okBtn = dialog.querySelector('#ok_btn');
            const cancelBtn = dialog.querySelector('#cancel_btn');

            nameInput.focus();
            nameInput.select();

            function closeDialog() {
                document.body.removeChild(dialog);
            }

            cancelBtn.onclick = closeDialog;

            okBtn.onclick = function() {
                const name = nameInput.value.trim() || defaultName;
                const required = requiredInput.checked;
                const shortcode = buildShortcode(fieldType, name, required);
                const range = [row, col, row, col];
                luckysheet.setCellValue(row, col, shortcode);
                setTimeout(function() {
                    luckysheet.setRangeFormat("ht", 0, range);
                    luckysheet.setRangeFormat("vt", 0, range);
                    luckysheet.setRangeFormat("tb", 2, range);
                    luckysheet.setRangeFormat("bl", 1, range);
                    luckysheet.setRangeFormat("fs", 12, range);
                    luckysheet.setRangeFormat("bg", "#F3F4F6", range);
                }, 50);
                closeDialog();
            };

            nameInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    okBtn.click();
                }
            });
        }

        function buildShortcode(fieldType, name, required) {
            const field = fieldType.match(/\[(.*?)\s/)[1];
            const requiredMark = required ? '*' : '';
            return '[' + field + requiredMark + ' ' + name + ']';
        }

        function toggleFullscreen() {
            const container = document.getElementById(containerId);
            if (!isFullscreen) {
                originalStyle = container.getAttribute("style");
                sidebarMenu = document.createElement("div");
                sidebarMenu.style.cssText = 'position:fixed;top:0;left:0;width:200px;height:100vh;background:#1f2937;z-index:99999;padding:20px;box-sizing:border-box;overflow-y:auto;';

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

                formFieldsSection.appendChild(createFormFieldButton("📝 Text", "[text* your-name]"));
                formFieldsSection.appendChild(createFormFieldButton("📧 Email", "[email* your-email]"));
                formFieldsSection.appendChild(createFormFieldButton("📱 Phone", "[tel your-phone]"));
                formFieldsSection.appendChild(createFormFieldButton("📄 Textarea", "[textarea your-message]"));
                formFieldsSection.appendChild(createFormFieldButton("📅 Date", "[date your-date]"));
                formFieldsSection.appendChild(createFormFieldButton("✍️ Signature", "[signature your-signature]"));

                sidebarMenu.appendChild(formFieldsSection);
                document.body.appendChild(sidebarMenu);

                container.style.cssText = 'position:fixed;top:0;left:200px;width:calc(100vw - 200px);height:100vh;z-index:99999;margin:0;border:none;background:#fff;';
                fullscreenBtn.textContent = "Exit Fullscreen";
                fullscreenBtn.style.backgroundColor = "#DC2626";
                fullscreenBtn.style.color = "#FFF";
                isFullscreen = true;
                setTimeout(function() {
                    luckysheet.resize();
                    setupDropZone();
                }, 100);
            } else {
                if (sidebarMenu) {
                    document.body.removeChild(sidebarMenu);
                    sidebarMenu = null;
                }
                container.setAttribute("style", originalStyle);
                fullscreenBtn.textContent = "Fullscreen";
                fullscreenBtn.style.backgroundColor = "#4B5563";
                fullscreenBtn.style.color = "#FFF";
                isFullscreen = false;
                setTimeout(function() {
                    luckysheet.resize();
                }, 100);
            }
        }

        fullscreenBtn.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleFullscreen();
        });
    });
}