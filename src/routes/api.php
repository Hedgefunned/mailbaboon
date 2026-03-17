<?php

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;

Route::apiResource('api_route', Controller::class)->only(['index', 'store', 'update']);
