<?php

use Illuminate\Support\Facades\Route;

// API-only: the UI lives in the Next.js app (/web).
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api/v1'),
]));
