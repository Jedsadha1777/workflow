function luckysheetToHtml() {
    console.log('=== luckysheetToHtml START ===');
    const sheets = luckysheet.getAllSheets();
    const result = [];
    
    sheets.forEach((sheet, sheetIndex) => {
        console.log('Processing sheet:', sheet.name, 'index:', sheetIndex);
        const data = sheet.data;
        
        // Switch ไปยัง sheet นี้เพื่อดึง config ล่าสุด
        const originalActiveIndex = luckysheet.getSheet().index;
        if (sheetIndex !== originalActiveIndex) {
            console.log('Switching to sheet index:', sheetIndex);
            luckysheet.setSheetActive(sheetIndex);
        }
        
        // ดึง config ล่าสุด
        const currentConfig = luckysheet.getConfig();
        console.log('Sheet:', sheet.name, 'columnlen:', currentConfig.columnlen);
        
        const columnlen = currentConfig.columnlen || {};
        const rowlen = currentConfig.rowlen || {};


        
        const merge = currentConfig.merge || {};
        
        const maxRow = data.length;
        const maxCol = Math.max(...data.map(row => row ? row.length : 0));
        
        const borderInfo = currentConfig.borderInfo || [];
        const condFormat = currentConfig.condFormat || [];
        
        // Build merge lookup table for performance
        const mergeLookup = buildMergeLookup(merge);
        
        // Apply conditional formatting
        const conditionalStyles = applyConditionalFormatting(data, condFormat, maxRow, maxCol);
        
        // คำนวณ total width
        let totalWidth = 0;
        for (let c = 0; c < maxCol; c++) {
            totalWidth += columnlen[c] !== undefined ? columnlen[c] : 73;
        }
        
        let html = '<table style="border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11pt; table-layout: fixed; width: ' + totalWidth + 'px;">';
        
        // Column widths
        html += '<colgroup>';
        for (let c = 0; c < maxCol; c++) {
            const colWidth = columnlen[c] !== undefined ? columnlen[c] : 73;
            if (c <= 5) console.log('Sheet:', sheet.name, 'Col', c, 'width:', colWidth, 'px');
            html += '<col style="width: ' + colWidth + 'px;">';
        }
        html += '</colgroup>';
        
        const skipCells = {};
        
       for (let r = 0; r < maxRow; r++) {
            const rowHeight = rowlen[r] !== undefined ? rowlen[r] : 19;
            html += '<tr style="height: ' + rowHeight + 'px;">';
            
            for (let c = 0; c < maxCol; c++) {
                const cellKey = r + '_' + c;
                
                if (skipCells[cellKey]) {
                    continue;
                }
                
                const cell = data[r] && data[r][c] ? data[r][c] : {};
                
                let colspan = 1;
                let rowspan = 1;
                
                // Check merge
                const mergeInfo = mergeLookup[cellKey];
                if (mergeInfo) {
                    rowspan = mergeInfo.rs;
                    colspan = mergeInfo.cs;
                    
                    for (let mr = r; mr < r + rowspan; mr++) {
                        for (let mc = c; mc < c + colspan; mc++) {
                            if (mr !== r || mc !== c) {
                                skipCells[mr + '_' + mc] = true;
                            }
                        }
                    }
                    
                    // Get borders from last column and last row of merged area
                    const lastCol = c + colspan - 1;
                    const lastRow = r + rowspan - 1;
                    
                    if (!cell.bd) cell.bd = {};
                    
                    borderInfo.forEach(border => {
                        if (border.rangeType === 'cell' && border.value) {
                            const bRow = border.value.row_index || 0;
                            const bCol = border.value.col_index || 0;
                            
                            // Right border from last column
                            if (bRow === r && bCol === lastCol && border.value.r && !cell.bd.r) {
                                cell.bd.r = {
                                    s: border.value.r.style,
                                    c: border.value.r.color
                                };
                            }
                            
                            // Bottom border from last row
                            if (bRow === lastRow && bCol === c && border.value.b && !cell.bd.b) {
                                cell.bd.b = {
                                    s: border.value.b.style,
                                    c: border.value.b.color
                                };
                            }
                        }
                    });
                }
                
                let style = '';
                style += 'padding: 2px 4px; ';
                style += 'box-sizing: border-box; ';
                
                // Background color
                if (cell.bg) {
                    style += 'background-color: ' + cell.bg + '; ';
                }
                
                // Apply conditional formatting background
                const condStyle = conditionalStyles[cellKey];
                if (condStyle && condStyle.bg) {
                    style += 'background-color: ' + condStyle.bg + '; ';
                }
                
                // Font color
                if (cell.fc) {
                    style += 'color: ' + cell.fc + '; ';
                }
                
                // Apply conditional formatting color
                if (condStyle && condStyle.fc) {
                    style += 'color: ' + condStyle.fc + '; ';
                }
                
                // Font size
                if (cell.fs) {
                    style += 'font-size: ' + cell.fs + 'pt; ';
                }
                
                // Font family
                if (cell.ff) {
                    style += 'font-family: ' + cell.ff + '; ';
                }
                
                // Bold
                if (cell.bl === 1) {
                    style += 'font-weight: bold; ';
                }
                
                // Italic
                if (cell.it === 1) {
                    style += 'font-style: italic; ';
                }
                
                // Underline types
                if (cell.un) {
                    if (cell.un === 1) {
                        style += 'text-decoration: underline; ';
                    } else if (cell.un === 2) {
                        style += 'text-decoration: underline double; ';
                    } else if (cell.un === 3) {
                        style += 'text-decoration: underline; text-underline-position: under; ';
                    }
                }
                
                // Strikethrough
                if (cell.cl === 1) {
                    style += 'text-decoration: line-through; ';
                }
                
                // Horizontal alignment
                if (cell.ht === 0) {
                    style += 'text-align: center; ';
                } else if (cell.ht === 2) {
                    style += 'text-align: right; ';
                } else if (cell.ht === 1) {
                    style += 'text-align: left; ';
                }
                
                // Vertical alignment
                if (cell.vt === 0) {
                    style += 'vertical-align: middle; ';
                } else if (cell.vt === 2) {
                    style += 'vertical-align: bottom; ';
                } else if (cell.vt === 1) {
                    style += 'vertical-align: top; ';
                }
                                
                // Text wrap and overflow
                if (cell.tb === 2) {
                    style += 'white-space: pre-wrap; word-wrap: break-word; ';
                } else if (cell.tb === 3) {
                    style += 'overflow: visible; white-space: nowrap; ';
                } else {
                    style += 'white-space: nowrap; overflow: hidden; text-overflow: ellipsis; ';
                }
                                
                // Text rotation
                if (cell.tr && cell.tr.t) {
                    const angle = cell.tr.a || 0;
                    if (angle !== 0) {
                        style += 'transform: rotate(' + angle + 'deg); ';
                        style += 'transform-origin: center; ';
                        style += 'display: inline-block; ';
                    }
                }
                
                // Borders
                const borderResult = getCellBorders(r, c, borderInfo, cell);
                style += borderResult.borderStyle;
                
                // Get cell content (handle rich text)
                let cellContent = '';
                
                if (cell.ct && cell.ct.t === 'inlineStr' && cell.ct.s) {
                    cellContent = convertRichText(cell.ct.s);
                } else if (cell.f) {
                    cellContent = formatCellValue(cell.v, cell.fa, cell.m);
                } else if (cell.m !== undefined) {
                    cellContent = escapeHtml(cell.m);
                } else if (cell.v !== undefined) {
                    cellContent = formatCellValue(cell.v, cell.fa, cell.m);
                } else {
                    cellContent = '';
                }
                
                // Hyperlink
                if (cell.hl) {
                    cellContent = '<a href="' + escapeHtml(cell.hl) + '" target="_blank" style="color: #0066cc; text-decoration: underline;">' + cellContent + '</a>';
                }
                
                // Comments/Notes indicator
                let commentIndicator = '';
                if (cell.ps || cell.comment) {
                    const commentText = cell.ps ? cell.ps.value : (cell.comment ? cell.comment.value : '');
                    commentIndicator = '<span title="' + escapeHtml(commentText) + '" style="position: absolute; top: 0; right: 0; width: 0; height: 0; border-left: 6px solid transparent; border-top: 6px solid #ff0000;"></span>';
                }
                
                // Conditional formatting icons
                let iconContent = '';
                if (condStyle && condStyle.icon) {
                    iconContent = '<span style="margin-right: 4px;">' + condStyle.icon + '</span>';
                }
                
                // Diagonal border SVG
                let diagonalSVG = '';
                if (borderResult.diagonalBorder) {
                    diagonalSVG = createDiagonalBorderSVG(borderResult.diagonalBorder, columnlen[c] || 73, rowlen[r] || 19);
                }
                
                const colspanAttr = colspan > 1 ? ' colspan="' + colspan + '"' : '';
                const rowspanAttr = rowspan > 1 ? ' rowspan="' + rowspan + '"' : '';
                
                html += '<td' + colspanAttr + rowspanAttr + ' style="' + style + ' position: relative;">' + diagonalSVG + commentIndicator + iconContent + cellContent + '</td>';
            }
            
            html += '</tr>';
        }
        
        html += '</table>';
        
        result.push({
            name: sheet.name,
            html: html
        });
    });
    
    return result;
}

function buildMergeLookup(merge) {
    const lookup = {};
    Object.keys(merge).forEach(key => {
        const m = merge[key];
        const cellKey = m.r + '_' + m.c;
        lookup[cellKey] = m;
    });
    return lookup;
}

function convertRichText(richTextArray) {
    let html = '';
    
    richTextArray.forEach(segment => {
        let text = escapeHtml(segment.v || '');
        let style = '';
        
        if (segment.bl === 1) style += 'font-weight: bold; ';
        if (segment.it === 1) style += 'font-style: italic; ';
        if (segment.un === 1) style += 'text-decoration: underline; ';
        if (segment.cl === 1) style += 'text-decoration: line-through; ';
        if (segment.fc) style += 'color: ' + segment.fc + '; ';
        if (segment.fs) style += 'font-size: ' + segment.fs + 'pt; ';
        if (segment.ff) style += 'font-family: ' + segment.ff + '; ';
        
        if (style) {
            html += '<span style="' + style + '">' + text + '</span>';
        } else {
            html += text;
        }
    });
    
    return html;
}

function formatCellValue(value, format, formattedValue) {
    if (formattedValue !== undefined && formattedValue !== null) {
        return escapeHtml(String(formattedValue));
    }
    
    if (!format || format === 'General') {
        return escapeHtml(String(value));
    }
    
    if (typeof value === 'number') {
        if (format.includes('yyyy') || format.includes('mm') || format.includes('dd')) {
            const date = excelDateToJSDate(value);
            return escapeHtml(formatDate(date, format));
        }
        
        if (format.includes('%')) {
            return escapeHtml((value * 100).toFixed(2) + '%');
        }
        
        if (format.includes('$') || format.includes('฿')) {
            return escapeHtml(formatCurrency(value, format));
        }
        
        const decimalMatch = format.match(/0\.(0+)/);
        if (decimalMatch) {
            const decimals = decimalMatch[1].length;
            return escapeHtml(value.toFixed(decimals));
        }
        
        if (format.includes('#,##0')) {
            return escapeHtml(value.toLocaleString());
        }
    }
    
    return escapeHtml(String(value));
}

function excelDateToJSDate(excelDate) {
    const daysFrom1900 = excelDate - 25569;
    const millisecondsPerDay = 86400000;
    return new Date(daysFrom1900 * millisecondsPerDay);
}

function formatDate(date, format) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    
    return format
        .replace('yyyy', year)
        .replace('mm', month)
        .replace('dd', day);
}

function formatCurrency(value, format) {
    const formatted = value.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    
    if (format.includes('$')) {
        return '$' + formatted;
    } else if (format.includes('฿')) {
        return '฿' + formatted;
    }
    
    return formatted;
}

function applyConditionalFormatting(data, condFormat, maxRow, maxCol) {
    const styles = {};
    
    condFormat.forEach(rule => {
        const type = rule.type;
        const cellrange = rule.cellrange || [];
        
        cellrange.forEach(range => {
            const rows = range.row || [0, maxRow - 1];
            const cols = range.column || [0, maxCol - 1];
            
            for (let r = rows[0]; r <= rows[1]; r++) {
                for (let c = cols[0]; c <= cols[1]; c++) {
                    const cell = data[r] && data[r][c] ? data[r][c] : {};
                    const cellKey = r + '_' + c;
                    const value = cell.v;
                    
                    if (type === 'dataBar') {
                        const percent = calculateDataBarPercent(value, rule);
                        styles[cellKey] = {
                            bg: 'linear-gradient(to right, ' + (rule.format || '#63c3f5') + ' ' + percent + '%, transparent ' + percent + '%)'
                        };
                    } else if (type === 'colorScale') {
                        const color = calculateColorScale(value, rule);
                        styles[cellKey] = { bg: color };
                    } else if (type === 'icons') {
                        const icon = getIconForValue(value, rule);
                        styles[cellKey] = { icon: icon };
                    } else if (type === 'highlightCell') {
                        if (evaluateCondition(value, rule)) {
                            styles[cellKey] = {
                                bg: rule.format && rule.format.bg ? rule.format.bg : undefined,
                                fc: rule.format && rule.format.fc ? rule.format.fc : undefined
                            };
                        }
                    }
                }
            }
        });
    });
    
    return styles;
}

function calculateDataBarPercent(value, rule) {
    if (typeof value !== 'number') return 0;
    const min = rule.minValue || 0;
    const max = rule.maxValue || 100;
    const percent = ((value - min) / (max - min)) * 100;
    return Math.max(0, Math.min(100, percent));
}

function calculateColorScale(value, rule) {
    if (typeof value !== 'number') return '#ffffff';
    
    const colors = rule.colors || ['#f8696b', '#ffeb84', '#63be7b'];
    const min = rule.minValue || 0;
    const max = rule.maxValue || 100;
    
    const percent = (value - min) / (max - min);
    
    if (percent <= 0.5) {
        return interpolateColor(colors[0], colors[1], percent * 2);
    } else {
        return interpolateColor(colors[1], colors[2], (percent - 0.5) * 2);
    }
}

function interpolateColor(color1, color2, factor) {
    const c1 = hexToRgb(color1);
    const c2 = hexToRgb(color2);
    
    const r = Math.round(c1.r + (c2.r - c1.r) * factor);
    const g = Math.round(c1.g + (c2.g - c1.g) * factor);
    const b = Math.round(c1.b + (c2.b - c1.b) * factor);
    
    return rgbToHex(r, g, b);
}

function hexToRgb(hex) {
    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? {
        r: parseInt(result[1], 16),
        g: parseInt(result[2], 16),
        b: parseInt(result[3], 16)
    } : { r: 0, g: 0, b: 0 };
}

function rgbToHex(r, g, b) {
    return '#' + [r, g, b].map(x => {
        const hex = x.toString(16);
        return hex.length === 1 ? '0' + hex : hex;
    }).join('');
}

function getIconForValue(value, rule) {
    const icons = rule.icons || ['⬇️', '➡️', '⬆️'];
    const thresholds = rule.thresholds || [33, 66];
    
    if (value < thresholds[0]) return icons[0];
    if (value < thresholds[1]) return icons[1];
    return icons[2];
}

function evaluateCondition(value, rule) {
    const operator = rule.operator;
    const compareValue = rule.value;
    
    switch (operator) {
        case 'greaterThan':
            return value > compareValue;
        case 'lessThan':
            return value < compareValue;
        case 'equal':
            return value == compareValue;
        case 'between':
            return value >= rule.value[0] && value <= rule.value[1];
        case 'notBetween':
            return value < rule.value[0] || value > rule.value[1];
        case 'containsText':
            return String(value).includes(compareValue);
        default:
            return false;
    }
}

function getCellBorders(r, c, borderInfo, cell) {
    let borderStyle = '';
    const borders = {
        top: null,
        bottom: null,
        left: null,
        right: null,
        diagonal: null
    };
    
    borderInfo.forEach(border => {
        const rangeType = border.rangeType;
        const value = border.value || {};
        
        if (rangeType === 'cell') {
            const row_index = value.row_index || 0;
            const col_index = value.col_index || 0;
            
            if (row_index === r && col_index === c) {
                if (value.t) borders.top = getBorderString(value.t.style, value.t.color);
                if (value.b) borders.bottom = getBorderString(value.b.style, value.b.color);
                if (value.l) borders.left = getBorderString(value.l.style, value.l.color);
                if (value.r) borders.right = getBorderString(value.r.style, value.r.color);
            }
        } else if (rangeType === 'range') {
            const borderType = border.borderType;
            const style = border.style;
            const color = border.color;
            const row = value.row || [r, r];
            const column = value.column || [c, c];
            
            if (r >= row[0] && r <= row[1] && c >= column[0] && c <= column[1]) {
                const borderStr = getBorderString(style, color);
                
                if (borderType === 'border-all') {
                    borders.top = borderStr;
                    borders.bottom = borderStr;
                    borders.left = borderStr;
                    borders.right = borderStr;
                } else if (borderType === 'border-top' && r === row[0]) {
                    borders.top = borderStr;
                } else if (borderType === 'border-bottom' && r === row[1]) {
                    borders.bottom = borderStr;
                } else if (borderType === 'border-left' && c === column[0]) {
                    borders.left = borderStr;
                } else if (borderType === 'border-right' && c === column[1]) {
                    borders.right = borderStr;
                }
            }
        }
    });
    
    let diagonalBorder = null;
    if (cell.bd) {
        if (cell.bd.t) borders.top = getBorderString(cell.bd.t.s, cell.bd.t.c);
        if (cell.bd.b) borders.bottom = getBorderString(cell.bd.b.s, cell.bd.b.c);
        if (cell.bd.l) borders.left = getBorderString(cell.bd.l.s, cell.bd.l.c);
        if (cell.bd.r) borders.right = getBorderString(cell.bd.r.s, cell.bd.r.c);
        
        if (cell.bd.d) {
            diagonalBorder = {
                type: cell.bd.d.t,
                style: cell.bd.d.s,
                color: cell.bd.d.c
            };
        }
    }
    
    if (borders.top) borderStyle += 'border-top: ' + borders.top + '; ';
    if (borders.bottom) borderStyle += 'border-bottom: ' + borders.bottom + '; ';
    if (borders.left) borderStyle += 'border-left: ' + borders.left + '; ';
    if (borders.right) borderStyle += 'border-right: ' + borders.right + '; ';
    
    return { borderStyle: borderStyle, diagonalBorder: diagonalBorder };
}

function getBorderString(style, color) {
    const width = getBorderStyleWidth(style);
    return width + ' solid ' + color;
}

function getBorderStyleWidth(style) {
    if (style === 1 || style === '1') return '1px';
    if (style === 2 || style === '2') return '2px';
    if (style === 3 || style === '3') return '3px';
    if (style === 13 || style === '13') return '3px';
    return '1px';
}

function createDiagonalBorderSVG(diagonalBorder, width, height) {
    const type = diagonalBorder.type;
    const strokeWidth = parseInt(getBorderStyleWidth(diagonalBorder.style)) || 1;
    const color = diagonalBorder.color || '#000000';
    
    let lines = '';
    
    if (type === 1 || type === 3) {
        lines += '<line x1="0" y1="0" x2="100%" y2="100%" stroke="' + color + '" stroke-width="' + strokeWidth + '" />';
    }
    
    if (type === 2 || type === 3) {
        lines += '<line x1="0" y1="100%" x2="100%" y2="0" stroke="' + color + '" stroke-width="' + strokeWidth + '" />';
    }
    
    return '<svg style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;">' + lines + '</svg>';
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}