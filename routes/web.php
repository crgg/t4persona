<?php

use Illuminate\Support\Facades\Route;
use Rap2hpoutre\LaravelLogViewer\LogViewerController;

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

//Route::view('/{any}', 'app')->where('any', '^(?!api).*$');


Route::get('/', function () {
    return view('welcome');
});

Route::group(['middleware' => 'basic_auth'], function() {
    Route::get('logs', [LogViewerController::class, 'index']);
});

