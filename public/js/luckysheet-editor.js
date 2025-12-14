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

        function createFormFieldButton(label, fieldType) {
            const btn = document.createElement("button");
            btn.className = "form-field-btn";
            btn.textContent = label;
            btn.onclick = function() {
                insertField(fieldType);
            };
            return btn;
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

                // ใส่ shortcode ใน cell แรกของ selection
                luckysheet.setCellValue(row, col, shortcode);

                const range = [row, col, rowEnd, colEnd];
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

                formFieldsSection.appendChild(createFormFieldButton("📝 Text", "[text your-name]"));
                formFieldsSection.appendChild(createFormFieldButton("📧 Email", "[email your-email]"));
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