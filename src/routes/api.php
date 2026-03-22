<?php

use App\Http\Controllers\ChunkedImportController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::apiResource('contacts', ContactController::class)->except(['show', 'create', 'edit']);
Route::post('import', [ImportController::class, 'store']);
Route::post('import/stream', [ImportController::class, 'stream']);
Route::get('import/rejected', [ImportController::class, 'rejected']);
Route::post('import/debug/truncate-contacts', [ImportController::class, 'truncateContacts']);
Route::post('import/chunked', [ChunkedImportController::class, 'store']);
Route::get('import/batch/{batchId}', [ChunkedImportController::class, 'status']);
