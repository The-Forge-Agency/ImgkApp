<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\TransformController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

// La racine est double : avec url= c'est l'API de transformation,
// sans rien c'est la landing. L'adresse imgk reste ainsi la plus courte possible.
Route::get('/', function () {
    if (request()->query('url') !== null || request()->query('preset') !== null) {
        return app()->call(TransformController::class);
    }

    return app(PageController::class)->landing();
})->middleware('throttle:transform')->name('home');

Route::get('/app', [PageController::class, 'studio'])->name('studio');
Route::get('/docs', [PageController::class, 'docs'])->name('docs');

Route::post('/api/upload', [UploadController::class, 'store'])
    ->middleware('throttle:uploads')
    ->name('upload');

Route::get('/u/{file}', [UploadController::class, 'show'])->name('uploads.show');
