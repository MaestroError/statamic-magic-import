<?php

use Illuminate\Support\Facades\Route;
use maestroerror\StatamicMagicImport\Http\Controllers\ImportController;

Route::group(['prefix' => 'json-import'], function () {
    Route::get('/', [ImportController::class, "index"])->name('json-import.index');
    Route::post('/upload', [ImportController::class, "upload"])->name('json-import.upload');
    Route::get('/summary', [ImportController::class, "summary"])->name('json-import.summary');
    Route::post('/import', [ImportController::class, "import"])->name('json-import.import');
});
