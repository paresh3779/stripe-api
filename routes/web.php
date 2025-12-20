<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StripeWebhookController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhook/stripe', [StripeWebhookController::class, 'handle']);
