<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
 */

Route::get('/', function () {

    return response()->json([
        'status' => 'error',
        'message' => 'not found',
        'error_code' => 'NOT_FOUND',
    ], 500);
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('/chat', [App\Http\Controllers\PusherController::class, 'index']);
Route::get('/messages', [App\Http\Controllers\PusherController::class, 'fetchMessages']);
Route::post('/messages', [App\Http\Controllers\PusherController::class, 'sendMessage']);
