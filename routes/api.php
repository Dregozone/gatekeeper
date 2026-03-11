<?php

use App\Http\Controllers\Api\AgentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/agents', [AgentController::class, 'index'])->name('api.agents.index');
    Route::post('/agents/{agent}', [AgentController::class, 'prompt'])->name('api.agents.prompt');
});
