<?php

use App\Http\Controllers\Api\ApplicationController;
use Illuminate\Support\Facades\Route;

// Utilisé par le mode hors ligne de l'application téléphone / ordinateur (dossier application/)
Route::get('/ping', fn () => ['ok' => true, 'application' => 'saint-martin-toukra']);
Route::post('/connexion', [ApplicationController::class, 'connexion'])->middleware('throttle:10,1');

Route::middleware('appareil')->group(function () {
    Route::get('/donnees', [ApplicationController::class, 'donnees'])->middleware('throttle:60,1');
    Route::post('/envoyer', [ApplicationController::class, 'envoyer'])->middleware('throttle:120,1');
    Route::post('/deconnexion', [ApplicationController::class, 'deconnexion']);
});
