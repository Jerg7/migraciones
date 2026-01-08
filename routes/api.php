<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CertificadosImportController;
use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/importar-certificados', [CertificadosImportController::class, 'import']);
});
