<?php

use Illuminate\Http\Request;
use Webkul\Shop\Http\Controllers\MpesaController;
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


    Route::post('v1/lnmo_callback', [MpesaController::class, 'lnmo_callback']);
