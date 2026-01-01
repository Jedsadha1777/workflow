<?php

use App\Http\Controllers\DocumentExportController;
use App\Http\Controllers\TemplateDocumentExportController;

Route::middleware(['auth:web'])->group(function () {
    
    Route::get('/documents/{document}/export-pdf', [DocumentExportController::class, 'exportPdf'])
        ->name('documents.export-pdf');
    
    Route::get('/documents/{document}/export-excel', [DocumentExportController::class, 'exportExcel'])
        ->name('documents.export-excel');
});

// Template Documents Preview - ไม่ต้อง auth เพราะ Filament จัดการเอง
Route::get('/template-documents/{templateDocument}/preview-pdf', [TemplateDocumentExportController::class, 'previewPdf'])
    ->name('template-documents.preview-pdf');


Route::get('/debug-fields/{document}', function ($id) {
    $document = \App\Models\Document::findOrFail($id);
    dd([
        'template' => $document->template->name,
        'signature_fields' => $document->getSignatureFields(),
        'date_fields' => $document->getDateFields(),
        'count_sig' => count($document->getSignatureFields() ?? []),
        'count_date' => count($document->getDateFields() ?? []),
    ]);
});


Route::get('/test-accessor/{id}', function($id) {
    $document = \App\Models\Document::findOrFail($id);
    $template = $document->template;
    
    // ทดสอบ accessor
    $content = $template->content;
    
    // ทดสอบ getRawOriginal (ดูค่าจริงใน DB)
    $rawContent = $template->getRawOriginal('content');
    
    dd([
        'content_after_accessor_type' => gettype($content),
        'content_after_accessor_is_array' => is_array($content),
        'content_has_sheets' => isset($content['sheets']),
        'raw_db_type' => gettype($rawContent),
        'raw_db_sample' => is_string($rawContent) ? substr($rawContent, 0, 100) : 'not string',
        'getFormFields_result' => $template->getFormFields(),
        'getSignatureFields_result' => $template->getSignatureFields(),
    ]);
});


Route::get('/test-method/{id}', function($id) {
    $template = \App\Models\TemplateDocument::findOrFail(
        \App\Models\Document::findOrFail($id)->template_document_id
    );
    
    // ทดสอบเรียก method ตรงๆ
    $hasMethod = method_exists($template, 'getContentAttribute');
    
    // ทดสอบเรียก attribute ผ่าน magic method
    $contentViaAttribute = $template->getAttribute('content');
    
    // ทดสอบเรียก method โดยตรง
    $rawValue = $template->getRawOriginal('content');
    $directCall = method_exists($template, 'getContentAttribute') 
        ? $template->getContentAttribute($rawValue) 
        : 'method not found';
    
    dd([
        'has_getContentAttribute_method' => $hasMethod,
        'content_via_attribute' => gettype($contentViaAttribute),
        'direct_call_type' => gettype($directCall),
        'direct_call_is_array' => is_array($directCall),
        'direct_call_has_sheets' => is_array($directCall) && isset($directCall['sheets']),
    ]);
});



Route::get('/debug-json/{id}', function($id) {
    $template = \App\Models\TemplateDocument::findOrFail(
        \App\Models\Document::findOrFail($id)->template_document_id
    );
    
    $rawValue = $template->getRawOriginal('content');
    
    // ทดสอบ decode
    $decoded1 = json_decode($rawValue, true);
    $error1 = json_last_error_msg();
    
    // ทดสอบ stripslashes
    $unescaped = stripslashes($rawValue);
    $decoded2 = json_decode($unescaped, true);
    $error2 = json_last_error_msg();
    
    dd([
        'raw_first_100' => substr($rawValue, 0, 100),
        'raw_length' => strlen($rawValue),
        'decode_direct' => $decoded1 !== null ? 'SUCCESS' : 'FAILED',
        'decode_direct_error' => $error1,
        'after_stripslashes_first_100' => substr($unescaped, 0, 100),
        'decode_after_strip' => $decoded2 !== null ? 'SUCCESS' : 'FAILED',
        'decode_after_strip_error' => $error2,
    ]);
});



Route::get('/show-method/{id}', function($id) {
    $template = \App\Models\TemplateDocument::find(
        \App\Models\Document::findOrFail($id)->template_document_id
    );
    
    $reflection = new ReflectionClass($template);
    $method = $reflection->getMethod('getContentAttribute');
    
    // อ่าน source code ของ method
    $filename = $method->getFileName();
    $startLine = $method->getStartLine();
    $endLine = $method->getEndLine();
    
    $file = file($filename);
    $methodCode = implode('', array_slice($file, $startLine - 1, $endLine - $startLine + 1));
    
    dd([
        'file_path' => $filename,
        'method_code' => $methodCode,
    ]);
});



Route::get('/direct-test/{id}', function($id) {
    $template = \App\Models\TemplateDocument::find(
        \App\Models\Document::findOrFail($id)->template_document_id
    );
    
    $rawValue = $template->getRawOriginal('content');
    
    // เรียก method โดยตรง
    $result = $template->getContentAttribute($rawValue);
    
    dd([
        'raw_is_string' => is_string($rawValue),
        'result_type' => gettype($result),
        'result_is_array' => is_array($result),
        'result_has_sheets' => is_array($result) && isset($result['sheets']),
        'json_decode_test' => json_decode($rawValue, true) !== null,
    ]);
});



Route::get('/test-laravel-accessor/{id}', function($id) {
    $template = \App\Models\TemplateDocument::find(
        \App\Models\Document::findOrFail($id)->template_document_id
    );
    
    // Force Laravel เรียก accessor
    $content1 = $template->content; // ผ่าน magic method
    $content2 = $template->getAttributeValue('content'); // เรียกตรงๆ
    
    dd([
        'via_property' => gettype($content1),
        'via_getAttributeValue' => gettype($content2),
        'check_log' => 'See storage/logs/laravel.log for getContentAttribute logs'
    ]);
});


Route::get('/final-debug/{id}', function($id) {
    $template = \App\Models\TemplateDocument::findOrFail(
        \App\Models\Document::findOrFail($id)->template_document_id
    );
    
    $rawValue = $template->getRawOriginal('content');
    
    // Decode
    $decoded = json_decode($rawValue, true);
    
    // เช็คว่า decoded เป็นอะไร
    dd([
        'raw_first_char' => substr($rawValue, 0, 1),
        'decoded_type' => gettype($decoded),
        'decoded_is_string' => is_string($decoded),
        'decoded_is_array' => is_array($decoded),
        'decoded_value' => is_string($decoded) ? substr($decoded, 0, 200) : 'not string',
        'double_decode' => is_string($decoded) ? json_decode($decoded, true) : null,
    ]);
});
