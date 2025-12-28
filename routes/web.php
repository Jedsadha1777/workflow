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