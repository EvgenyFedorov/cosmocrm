<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(array('prefix' => 'v1'), function () {

    Route::group(array('prefix' => 'users'), function () {

        //Route::get('new', [\App\Http\Controllers\Api\AuthController::class, 'registration']);
        Route::post('new', [\App\Http\Controllers\Api\AuthController::class, 'registration']);
        //Route::get('auth', [\App\Http\Controllers\Api\AuthController::class, 'authorization']);
        Route::post('auth', [\App\Http\Controllers\Api\AuthController::class, 'authorization']);

    });

});

//Route::middleware('auth:api')->get('/user', function (Request $request) {
//    return $request->user();
//});
