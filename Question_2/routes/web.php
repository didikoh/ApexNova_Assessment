<?php

use App\Http\Controllers\SwaggerController;
use Illuminate\Support\Facades\Route;

Route::view('/swagger', 'swagger')->name('swagger');
Route::get('/swagger/openapi.json', SwaggerController::class)->name('swagger.spec');

Route::get('/', function () {
    return view('welcome');
});
