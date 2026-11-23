<?php

use App\Http\Controllers\SoalGeneratorController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SoalGeneratorController::class, 'index'])->name('generator.index');
Route::post('/generate', [SoalGeneratorController::class, 'generate'])->name('generator.generate');
Route::get('/generate', function () {
    return redirect()->route('generator.index');
});

Route::post('/export', [SoalGeneratorController::class, 'export'])->name('generator.export');
Route::get('/export', function () {
    return redirect()->route('generator.index');
});
