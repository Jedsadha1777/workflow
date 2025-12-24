<?php

use App\Http\Controllers\DocumentExportController;

Route::middleware(['auth:web'])->group(function () {
    
    Route::get('/documents/{document}/export-pdf', [DocumentExportController::class, 'exportPdf'])
        ->name('documents.export-pdf');
    
    Route::get('/documents/{document}/export-excel', [DocumentExportController::class, 'exportExcel'])
        ->name('documents.export-excel');
});