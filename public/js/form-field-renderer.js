function renderFormFields(html) {
    console.log('renderFormFields called with html length:', html.length);
    console.log('Sample html:', html.substring(0, 500));
    
    // Select with cell attribute
    html = html.replace(/\[select(\*?)\s+([^\s]+)\s+(.*?)\]/gi, function(match, required, name, restStr) {
        console.log('Select matched:', match);
        const hasFirstAsLabel = /first_as_label/.test(restStr);
        const cellMatch = restStr.match(/cell="([^"]+)"/);
        const cellAttr = cellMatch ? ' data-cell-field="' + cellMatch[1] + '"' : '';
        const optionsMatch = restStr.match(/(?:"([^"]*)"|&quot;([^&]*?)&quot;)/gi);
        
        if (!optionsMatch) return match;
        
        const options = optionsMatch.map(opt => opt.replace(/^(?:"|&quot;)|(?:"|&quot;)$/gi, ''));
        let selectHtml = '<select' + cellAttr + ' style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;box-sizing:border-box;background:white;">';
        
        options.forEach((option, index) => {
            if (index === 0 && hasFirstAsLabel) {
                selectHtml += '<option value="" disabled selected>' + option + '</option>';
            } else {
                selectHtml += '<option value="' + option + '">' + option + '</option>';
            }
        });
        
        selectHtml += '</select>';
        return selectHtml;
    });
    
    // Textarea
    html = html.replace(/\[textarea(\*?)\s+([^\]]+)\]/gi, function(match, required, restStr) {
        console.log('Textarea matched:', match);
        const cellMatch = restStr.match(/cell="([^"]+)"/);
        const cellAttr = cellMatch ? ' data-cell-field="' + cellMatch[1] + '"' : '';
        return '<textarea' + cellAttr + ' placeholder="Enter text..." rows="4" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;box-sizing:border-box;resize:vertical;min-height:80px;font-family:inherit;"></textarea>';
    });
    
    // Text input
    html = html.replace(/\[text(\*?)\s+([^\]]+)\]/gi, function(match, required, restStr) {
        console.log('Text matched:', match);
        const cellMatch = restStr.match(/cell="([^"]+)"/);
        const cellAttr = cellMatch ? ' data-cell-field="' + cellMatch[1] + '"' : '';
        return '<input type="text"' + cellAttr + ' placeholder="Text" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;box-sizing:border-box;" />';
    });
    
    // Email
    html = html.replace(/\[email(\*?)\s+([^\]]+)\]/gi, function(match, required, restStr) {
        console.log('Email matched:', match);
        const cellMatch = restStr.match(/cell="([^"]+)"/);
        const cellAttr = cellMatch ? ' data-cell-field="' + cellMatch[1] + '"' : '';
        return '<input type="email"' + cellAttr + ' placeholder="Email" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;box-sizing:border-box;" />';
    });
    
    // Other fields...
    html = html.replace(/\[tel(\*?)\s+([^\]]+)\]/gi, function(match, required, restStr) {
        const cellMatch = restStr.match(/cell="([^"]+)"/);
        const cellAttr = cellMatch ? ' data-cell-field="' + cellMatch[1] + '"' : '';
        return '<input type="tel"' + cellAttr + ' placeholder="Phone" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;box-sizing:border-box;" />';
    });
    
    html = html.replace(/\[number(\*?)\s+([^\]]+)\]/gi, function(match, required, restStr) {
        const cellMatch = restStr.match(/cell="([^"]+)"/);
        const cellAttr = cellMatch ? ' data-cell-field="' + cellMatch[1] + '"' : '';
        return '<input type="number"' + cellAttr + ' placeholder="Number" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;box-sizing:border-box;" />';
    });
    
    html = html.replace(/\[date(\*?)\s+([^\]]+)\]/gi, function(match, required, restStr) {
        const cellMatch = restStr.match(/cell="([^"]+)"/);
        const cellAttr = cellMatch ? ' data-cell-field="' + cellMatch[1] + '"' : '';
        return '<input type="date"' + cellAttr + ' style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;box-sizing:border-box;" />';
    });
    
    html = html.replace(/\[checkbox(\*?)\s+([^\]]+)\]/gi, function(match, required, restStr) {
        const cellMatch = restStr.match(/cell="([^"]+)"/);
        const cellAttr = cellMatch ? ' data-cell-field="' + cellMatch[1] + '"' : '';
        return '<input type="checkbox"' + cellAttr + ' style="width:18px;height:18px;cursor:pointer;" />';
    });
    
    html = html.replace(/\[signature(\*?)\s+([^\]]+)\]/gi, function(match, required, restStr) {
        const cellMatch = restStr.match(/cell="([^"]+)"/);
        const cellAttr = cellMatch ? ' data-cell-field="' + cellMatch[1] + '"' : '';
        return '<div' + cellAttr + ' style="border:2px dashed #d1d5db;padding:20px;text-align:center;background:#f9fafb;min-height:80px;display:flex;align-items:center;justify-content:center;font-size:14px;color:#6b7280;font-style:italic;border-radius:6px;">Signature Field</div>';
    });
    
    console.log('After replacement, html length:', html.length);
    return html;
}