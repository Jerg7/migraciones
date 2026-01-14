<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CertificadosImportController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InmaController;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/inma/modelos', [InmaController::class, 'getModelos']);
Route::get('/inma/marcas', [InmaController::class, 'getMarcas']);
Route::get('/inma/versiones', [InmaController::class, 'getVersiones']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/importar-certificados', [CertificadosImportController::class, 'import']);
});
