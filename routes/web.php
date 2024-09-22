<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmailController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/fetch-emails', [EmailController::class, 'fetchAndStoreEmails']);
Route::get('/emails', [EmailController::class, 'getEmails']);
